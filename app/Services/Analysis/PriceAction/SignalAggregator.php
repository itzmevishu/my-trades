<?php

namespace App\Services\Analysis\PriceAction;

use App\Services\Analysis\PriceAction\Support\AnalyzerResult;
use App\Services\Analysis\PriceAction\Support\MarketContext;

/**
 * SignalAggregator - Combines independent analyzer observations into a single,
 * confluence-based trading assessment.
 *
 * Philosophy:
 *  - No single factor generates a signal. Confidence is a weighted blend of
 *    agreeing evidence across trend, structure, location, momentum, events and
 *    (lightly) candlestick confirmation.
 *  - Context gates everything: trend and market structure set the frame; a
 *    factor that fights that frame is discounted, one that aligns is rewarded.
 *  - Candlestick patterns contribute only a small slice and can never, on
 *    their own, manufacture a trade.
 *
 * The output is a human-explainable narrative plus a structured payload ready
 * for downstream AI scoring.
 */
class SignalAggregator
{
    /**
     * Relative weight of each analyzer in the confluence blend.
     * Structure and trend dominate; candlesticks are intentionally tiny.
     */
    private array $weights;

    public function __construct(array $config = [])
    {
        $this->weights = array_merge([
            'trend' => 0.24,
            'market_structure' => 0.24,
            'price_action' => 0.18,
            'support_resistance' => 0.12,
            'momentum' => 0.11,
            'ema' => 0.06,
            'volatility' => 0.02,
            'candlestick_confirmation' => 0.03,
        ], $config['weights'] ?? []);
    }

    /**
     * @param array<string,AnalyzerResult> $results Keyed by analyzer key.
     */
    public function aggregate(array $results, MarketContext $ctx): array
    {
        // --- 1. Directional vote (weighted, signed) ---------------------
        $bull = 0.0;
        $bear = 0.0;

        foreach ($results as $key => $res) {
            $w = $this->weights[$key] ?? 0.0;
            $contribution = $w * $res->strength;

            if ($res->direction === AnalyzerResult::BULLISH) {
                $bull += $contribution;
            } elseif ($res->direction === AnalyzerResult::BEARISH) {
                $bear += $contribution;
            }
        }

        $net = $bull - $bear;
        $direction = AnalyzerResult::NEUTRAL;
        if ($net > 0.0001) {
            $direction = AnalyzerResult::BULLISH;
        } elseif ($net < -0.0001) {
            $direction = AnalyzerResult::BEARISH;
        }

        // --- 2. Context gating ------------------------------------------
        // The trend + structure frame either endorses or contradicts the vote.
        $trend = $results['trend'] ?? null;
        $structure = $results['market_structure'] ?? null;

        $agreement = $this->frameAgreement($direction, $trend, $structure);

        // --- 3. Raw confidence ------------------------------------------
        // Base is the winning side's weighted mass, scaled by how much of the
        // total possible weight actually agreed (confluence density), then
        // adjusted by the trend/structure frame.
        $winningMass = $direction === AnalyzerResult::BULLISH ? $bull : ($direction === AnalyzerResult::BEARISH ? $bear : 0.0);
        $totalWeight = array_sum($this->weights);
        $confluenceDensity = $totalWeight > 0 ? $winningMass / $totalWeight : 0.0;

        // Blend absolute conviction and confluence density.
        $rawConfidence = (($winningMass * 1.4) + ($confluenceDensity)) / 2;
        $rawConfidence *= $agreement['multiplier'];

        $confidence = (int) round(max(0, min(100, $rawConfidence * 100)));

        // A neutral/ranging market caps confidence: no clean edge to trade.
        if ($direction === AnalyzerResult::NEUTRAL) {
            $confidence = min($confidence, 35);
        }

        // --- 4. Collect events & structured sections --------------------
        $priceActionEvents = $this->collectEvents($results, ['price_action', 'momentum', 'ema', 'volatility', 'trend', 'market_structure']);
        $candlestick = $results['candlestick_confirmation']->observations ?? [];

        $grade = $this->grade($confidence);
        $recommendation = $this->recommendation($confidence, $direction);

        $reasoning = $this->buildNarrative($direction, $confidence, $results, $agreement, $ctx);

        return [
            'direction' => $direction,
            'confidence' => $confidence,
            'grade' => $grade,
            'recommendation' => $recommendation,
            'bias_scores' => [
                'bullish' => round($bull, 3),
                'bearish' => round($bear, 3),
                'net' => round($net, 3),
            ],
            'frame_agreement' => $agreement['label'],
            'trend' => $this->trendSection($results['trend'] ?? null),
            'market_structure' => $this->structureSection($results['market_structure'] ?? null),
            'price_action' => $priceActionEvents,
            'support_resistance' => $this->srSection($results['support_resistance'] ?? null),
            'candlestick_confirmation' => $candlestick,
            'momentum' => $this->simpleSection($results['momentum'] ?? null),
            'volatility' => $results['volatility']->data['regime'] ?? 'unknown',
            'reasoning' => $reasoning,
        ];
    }

    // ------------------------------------------------------------------
    // Framing / gating
    // ------------------------------------------------------------------

    private function frameAgreement(string $direction, ?AnalyzerResult $trend, ?AnalyzerResult $structure): array
    {
        if ($direction === AnalyzerResult::NEUTRAL) {
            return ['multiplier' => 0.6, 'label' => 'no_clear_frame'];
        }

        $trendAgrees = $trend && $trend->direction === $direction;
        $trendFights = $trend && $trend->direction !== AnalyzerResult::NEUTRAL && $trend->direction !== $direction;
        $structAgrees = $structure && $structure->direction === $direction;
        $structFights = $structure && $structure->direction !== AnalyzerResult::NEUTRAL && $structure->direction !== $direction;

        // Both the trend and structure endorse the signal -> full confluence.
        if ($trendAgrees && $structAgrees) {
            return ['multiplier' => 1.15, 'label' => 'trend_and_structure_aligned'];
        }
        if ($trendAgrees || $structAgrees) {
            if (!$trendFights && !$structFights) {
                return ['multiplier' => 1.0, 'label' => 'partially_aligned'];
            }
        }
        // Signal fights the dominant frame -> heavy discount (counter-trend).
        if ($trendFights || $structFights) {
            return ['multiplier' => 0.55, 'label' => 'counter_trend'];
        }

        return ['multiplier' => 0.85, 'label' => 'weak_frame'];
    }

    // ------------------------------------------------------------------
    // Section builders
    // ------------------------------------------------------------------

    private function trendSection(?AnalyzerResult $trend): array
    {
        if (!$trend) {
            return ['direction' => 'neutral', 'strength' => 'unknown', 'state' => 'undefined'];
        }

        return [
            'direction' => $trend->direction,
            'strength' => $trend->data['strength_label'] ?? 'unknown',
            'state' => $trend->data['state'] ?? 'unknown',
        ];
    }

    private function structureSection(?AnalyzerResult $s): array
    {
        if (!$s) {
            return ['state' => 'undefined', 'bos' => false, 'choch' => false];
        }

        return [
            'state' => $s->data['state'] ?? 'undefined',
            'bos' => (bool) ($s->data['bos'] ?? false),
            'choch' => (bool) ($s->data['choch'] ?? false),
            'pullback' => (bool) ($s->data['is_pullback'] ?? false),
            'retest' => (bool) ($s->data['is_retest'] ?? false),
        ];
    }

    private function srSection(?AnalyzerResult $sr): array
    {
        if (!$sr) {
            return ['support' => null, 'resistance' => null, 'reaction' => 'none'];
        }

        return [
            'support' => $sr->data['support'] ?? null,
            'resistance' => $sr->data['resistance'] ?? null,
            'reaction' => $sr->data['reaction'] ?? 'none',
        ];
    }

    private function simpleSection(?AnalyzerResult $r): array
    {
        if (!$r) {
            return ['direction' => 'neutral'];
        }

        return [
            'direction' => $r->direction,
            'observations' => $r->observations,
        ];
    }

    /** Gather unique behavioural events, trend/structure tags filtered out of noise. */
    private function collectEvents(array $results, array $keys): array
    {
        $events = [];
        foreach ($keys as $k) {
            if (!isset($results[$k])) {
                continue;
            }
            foreach ($results[$k]->observations as $obs) {
                $events[] = $obs;
            }
        }

        return array_values(array_unique($events));
    }

    // ------------------------------------------------------------------
    // Grading / narrative
    // ------------------------------------------------------------------

    private function grade(int $confidence): string
    {
        return match (true) {
            $confidence >= 90 => 'A+',
            $confidence >= 80 => 'A',
            $confidence >= 70 => 'B',
            $confidence >= 60 => 'C',
            default => 'D',
        };
    }

    private function recommendation(int $confidence, string $direction): string
    {
        if ($direction === AnalyzerResult::NEUTRAL) {
            return 'Stand aside — no clear directional edge.';
        }

        $dir = ucfirst($direction);

        return match (true) {
            $confidence >= 90 => "Excellent {$dir} setup — strong confluence, high-probability entry.",
            $confidence >= 80 => "High-probability {$dir} setup — take the trade.",
            $confidence >= 70 => "Good {$dir} setup — consider entry with disciplined risk.",
            $confidence >= 60 => "Marginal {$dir} setup — watchlist only.",
            default => 'Skip — insufficient confluence.',
        };
    }

    /**
     * Compose a professional-trader narrative from the strongest, most relevant
     * reasoning sentences across the analyzers, ordered the way a human frames
     * a market: trend -> structure -> location -> reaction -> momentum -> confirmation.
     */
    private function buildNarrative(string $direction, int $confidence, array $results, array $agreement, MarketContext $ctx): string
    {
        $order = ['trend', 'market_structure', 'support_resistance', 'price_action', 'momentum', 'ema', 'volatility', 'candlestick_confirmation'];
        $sentences = [];

        foreach ($order as $key) {
            if (!isset($results[$key])) {
                continue;
            }
            $res = $results[$key];

            // Only include analyzers that are directionally relevant or emit an
            // explicitly contextual (neutral but informative) reasoning line.
            $relevant = $res->direction === $direction
                || $key === 'volatility'
                || ($key === 'market_structure' && !empty($res->reasoning))
                || ($key === 'trend');

            if (!$relevant) {
                continue;
            }

            foreach ($res->reasoning as $line) {
                $sentences[] = $line;
            }
        }

        // De-duplicate while preserving order, then cap length for readability.
        $sentences = array_values(array_unique($sentences));
        $sentences = array_slice($sentences, 0, 6);

        if (empty($sentences)) {
            return 'No coherent price-action story is present; the market lacks a tradable edge right now.';
        }

        $header = match (true) {
            $direction === AnalyzerResult::NEUTRAL => 'The market offers no clear edge right now. ',
            $agreement['label'] === 'counter_trend' => 'A counter-trend opportunity is forming, which demands caution. ',
            $agreement['label'] === 'trend_and_structure_aligned' => 'Trend and structure are in full agreement. ',
            default => '',
        };

        return trim($header . implode(' ', $sentences));
    }
}

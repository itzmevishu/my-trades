<?php

namespace App\Services\Analysis\PriceAction\Analyzers;

use App\Services\Analysis\PriceAction\Support\AnalyzerResult;
use App\Services\Analysis\PriceAction\Support\MarketContext;

/**
 * MomentumAnalyzer - Is momentum increasing or decreasing?
 *
 * Instead of a bounded oscillator, this reads momentum the way a price-action
 * trader does: through the size and direction of recent candle bodies relative
 * to the rolling average body, plus the sequence of closes.
 *
 * It surfaces behavioural events:
 *  - momentum_candle      : a decisive expansion candle in one direction
 *  - exhaustion_candle    : an oversized candle with a rejecting wick (climax)
 *  - momentum_building    : successive closes accelerating in one direction
 *  - momentum_fading      : contracting bodies / stalling closes
 */
class MomentumAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'momentum';
    }

    public function analyze(MarketContext $ctx): AnalyzerResult
    {
        if ($ctx->count < 4) {
            return $this->neutral('Not enough candles to gauge momentum.');
        }

        $current = $ctx->current;
        $body = MarketContext::body($current);
        $range = max(MarketContext::range($current), 1e-9);
        $avgBody = max($ctx->avgBody, 1e-9);
        $bodyRatio = $body / $avgBody; // >1 = bigger than usual
        $bodyToRange = $body / $range;

        $result = new AnalyzerResult();

        // Direction of the driving candle.
        $dir = MarketContext::isBull($current)
            ? AnalyzerResult::BULLISH
            : (MarketContext::isBear($current) ? AnalyzerResult::BEARISH : AnalyzerResult::NEUTRAL);

        // Compare last 3 bodies to detect acceleration / deceleration.
        $recent = array_slice($ctx->candles, -3);
        $bodies = array_map(static fn ($c) => MarketContext::body($c), $recent);
        $accelerating = $bodies[2] > $bodies[1] && $bodies[1] >= $bodies[0] * 0.8;
        $fading = $bodies[2] < $bodies[1] * 0.7;

        $momentumMult = (float) $this->cfg('momentum_body_mult', 1.5);
        $exhaustionMult = (float) $this->cfg('exhaustion_body_mult', 2.2);
        $wickRejection = (float) $this->cfg('exhaustion_wick_ratio', 0.45);

        $upperWick = MarketContext::upperWick($current) / $range;
        $lowerWick = MarketContext::lowerWick($current) / $range;

        $result->data = [
            'body_ratio' => round($bodyRatio, 2),
            'body_to_range' => round($bodyToRange, 2),
            'direction' => $dir,
            'accelerating' => $accelerating,
            'fading' => $fading,
        ];

        // --- Exhaustion (climax) ----------------------------------------
        if ($bodyRatio >= $exhaustionMult && (($dir === AnalyzerResult::BULLISH && $upperWick >= $wickRejection) || ($dir === AnalyzerResult::BEARISH && $lowerWick >= $wickRejection))) {
            $result->direction = $dir === AnalyzerResult::BULLISH ? AnalyzerResult::BEARISH : AnalyzerResult::BULLISH;
            $result->strength = 0.55;
            $result->addObservation('exhaustion_candle');
            $result->addReasoning('An oversized candle closed with a rejecting wick — a possible exhaustion/climax where the driving side is running out of steam.');

            return $result;
        }

        // --- Momentum candle --------------------------------------------
        if ($bodyRatio >= $momentumMult && $bodyToRange >= 0.6 && $dir !== AnalyzerResult::NEUTRAL) {
            $result->direction = $dir;
            $result->strength = $accelerating ? 0.85 : 0.7;
            $result->addObservation('momentum_candle');
            $result->addReasoning('A decisive momentum candle (body well above the recent average) closed strongly in the ' . ($dir === AnalyzerResult::BULLISH ? 'bullish' : 'bearish') . ' direction.');
            if ($accelerating) {
                $result->addObservation('momentum_building');
                $result->addReasoning('Successive candle bodies are expanding — momentum is building, not fading.');
            }

            return $result;
        }

        // --- Building / fading without a single dominant candle ---------
        if ($accelerating && $dir !== AnalyzerResult::NEUTRAL) {
            $result->direction = $dir;
            $result->strength = 0.5;
            $result->addObservation('momentum_building');
            $result->addReasoning('Momentum is quietly building as candle bodies expand in the ' . ($dir === AnalyzerResult::BULLISH ? 'bullish' : 'bearish') . ' direction.');

            return $result;
        }

        if ($fading) {
            $result->direction = AnalyzerResult::NEUTRAL;
            $result->strength = 0.3;
            $result->addObservation('momentum_fading');
            $result->addReasoning('Candle bodies are contracting — momentum is fading and the current push is losing conviction.');

            return $result;
        }

        $result->addReasoning('Momentum is neutral — no dominant expansion candle and closes are not accelerating.');

        return $result;
    }
}

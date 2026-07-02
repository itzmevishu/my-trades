<?php

namespace App\Services\Analysis\PriceAction\Analyzers;

use App\Services\Analysis\PriceAction\Support\AnalyzerResult;
use App\Services\Analysis\PriceAction\Support\MarketContext;

/**
 * SupportResistanceAnalyzer - Where is price, and who is defending it?
 *
 * Builds a set of candidate levels from swing points and the EMAs, then
 * checks whether price is currently reacting at one of them and how strong
 * that reaction is. Answers:
 *  - Is this an attractive location (at value / at a defended level)?
 *  - Are buyers or sellers defending the area?
 *  - How strong is the reaction (rejection wick, follow-through)?
 *
 * Levels are clustered adaptively using ATR so nearby swings merge into a
 * single zone rather than a noisy list of prices.
 */
class SupportResistanceAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'support_resistance';
    }

    public function analyze(MarketContext $ctx): AnalyzerResult
    {
        $levels = $this->buildLevels($ctx);
        $price = $ctx->lastPrice;

        $nearZoneAtr = (float) $this->cfg('sr_zone_atr', 0.4);
        $nearest = null;
        $nearestDist = PHP_FLOAT_MAX;

        foreach ($levels as $level) {
            $dist = abs($price - $level['price']);
            if ($dist < $nearestDist) {
                $nearestDist = $dist;
                $nearest = $level;
            }
        }

        $result = new AnalyzerResult();
        $result->data = [
            'levels' => $levels,
            'nearest_level' => $nearest,
            'distance_atr' => $ctx->atr > 0 ? round($nearestDist / $ctx->atr, 2) : null,
            'support' => null,
            'resistance' => null,
            'reaction' => 'none',
        ];

        if ($nearest === null || $ctx->inAtr($nearestDist) > $nearZoneAtr) {
            $result->addReasoning('Price is trading in open space, not at any obvious support or resistance level.');

            return $result;
        }

        // Price is interacting with a level. Assess the reaction.
        $current = $ctx->current;
        $lowerWick = MarketContext::lowerWick($current);
        $upperWick = MarketContext::upperWick($current);
        $range = max(MarketContext::range($current), 1e-9);
        $isSupport = $nearest['price'] <= $price + $ctx->atr * $nearZoneAtr && $nearest['type'] !== 'resistance_only';

        // Determine whether the level acts as support (below) or resistance (above).
        $actsAsSupport = $nearest['price'] <= $price;

        $strongRejection = false;
        if ($actsAsSupport) {
            $result->data['support'] = $nearest['label'];
            // Long lower wick = buyers defending support.
            if ($lowerWick / $range >= (float) $this->cfg('rejection_wick_ratio', 0.5) && MarketContext::isBull($current)) {
                $strongRejection = true;
                $result->direction = AnalyzerResult::BULLISH;
                $result->strength = 0.7;
                $result->data['reaction'] = 'strong_bounce';
                $result->addObservation('support_defended');
                $result->addReasoning("Buyers are defending {$nearest['label']} — a long lower wick shows demand absorbing the sell-off.");
            } else {
                $result->direction = AnalyzerResult::BULLISH;
                $result->strength = 0.4;
                $result->data['reaction'] = 'testing';
                $result->addObservation('at_support');
                $result->addReasoning("Price is testing support at {$nearest['label']}; reaction not yet decisive.");
            }
        } else {
            $result->data['resistance'] = $nearest['label'];
            if ($upperWick / $range >= (float) $this->cfg('rejection_wick_ratio', 0.5) && MarketContext::isBear($current)) {
                $strongRejection = true;
                $result->direction = AnalyzerResult::BEARISH;
                $result->strength = 0.7;
                $result->data['reaction'] = 'strong_rejection';
                $result->addObservation('resistance_defended');
                $result->addReasoning("Sellers are defending {$nearest['label']} — a long upper wick shows supply rejecting the rally.");
            } else {
                $result->direction = AnalyzerResult::BEARISH;
                $result->strength = 0.4;
                $result->data['reaction'] = 'testing';
                $result->addObservation('at_resistance');
                $result->addReasoning("Price is testing resistance at {$nearest['label']}; reaction not yet decisive.");
            }
        }

        $result->data['strong_reaction'] = $strongRejection;

        return $result;
    }

    /**
     * Build candidate levels from EMAs and clustered swing points.
     *
     * @return array<int,array{price:float,label:string,type:string,touches:int}>
     */
    private function buildLevels(MarketContext $ctx): array
    {
        $levels = [];

        if ($ctx->emaFast !== null) {
            $levels[] = ['price' => $ctx->emaFast, 'label' => "{$ctx->emaFastPeriod} EMA", 'type' => 'dynamic', 'touches' => 0];
        }
        if ($ctx->emaMid !== null) {
            $levels[] = ['price' => $ctx->emaMid, 'label' => "{$ctx->emaMidPeriod} EMA", 'type' => 'dynamic', 'touches' => 0];
        }
        if ($ctx->emaSlow !== null) {
            $levels[] = ['price' => $ctx->emaSlow, 'label' => "{$ctx->emaSlowPeriod} EMA", 'type' => 'dynamic', 'touches' => 0];
        }

        // Cluster swing highs/lows into horizontal zones.
        $swingPrices = array_merge(
            array_map(static fn ($s) => $s['price'], $ctx->recentSwingHighs(6)),
            array_map(static fn ($s) => $s['price'], $ctx->recentSwingLows(6))
        );

        $tol = $ctx->atr * (float) $this->cfg('sr_cluster_atr', 0.5);
        $clusters = [];

        foreach ($swingPrices as $p) {
            $merged = false;
            foreach ($clusters as &$cluster) {
                if (abs($cluster['sum'] / $cluster['count'] - $p) <= $tol) {
                    $cluster['sum'] += $p;
                    $cluster['count']++;
                    $merged = true;
                    break;
                }
            }
            unset($cluster);
            if (!$merged) {
                $clusters[] = ['sum' => $p, 'count' => 1];
            }
        }

        foreach ($clusters as $cluster) {
            $avg = round($cluster['sum'] / $cluster['count'], 2);
            $levels[] = [
                'price' => $avg,
                'label' => 'swing level ' . $avg,
                'type' => 'horizontal',
                'touches' => $cluster['count'],
            ];
        }

        return $levels;
    }
}

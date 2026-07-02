<?php

namespace App\Services\Analysis\PriceAction\Analyzers;

use App\Services\Analysis\PriceAction\Support\AnalyzerResult;
use App\Services\Analysis\PriceAction\Support\MarketContext;

/**
 * EMAAnalyzer - Reads the 20 / 100 / 2000 EMA relationship.
 *
 * EMAs are treated as dynamic trend + support/resistance tools, not signals
 * on their own. This analyzer evaluates:
 *  - Alignment (are the EMAs fanned in one direction?)
 *  - Slope (are they rising / falling / flat?)
 *  - Distance of price from the fast EMA (stretched vs at value)
 *  - Whether price is currently interacting with an EMA (pullback zone)
 *
 * All thresholds are ATR-relative so the read adapts to volatility.
 */
class EMAAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'ema';
    }

    public function analyze(MarketContext $ctx): AnalyzerResult
    {
        if ($ctx->emaFast === null || $ctx->emaMid === null) {
            return $this->neutral('Not enough data to compute the trend EMAs.');
        }

        $price = $ctx->lastPrice;
        $fast = $ctx->emaFast;
        $mid = $ctx->emaMid;
        $slow = $ctx->emaSlow;

        $result = new AnalyzerResult();

        // --- Alignment --------------------------------------------------
        $bullAligned = $slow !== null
            ? ($price > $fast && $fast > $mid && $mid > $slow)
            : ($price > $fast && $fast > $mid);
        $bearAligned = $slow !== null
            ? ($price < $fast && $fast < $mid && $mid < $slow)
            : ($price < $fast && $fast < $mid);

        $partialBull = !$bullAligned && $price > $fast && $fast > $mid;
        $partialBear = !$bearAligned && $price < $fast && $fast < $mid;

        // --- Distance from fast EMA (ATR units) -------------------------
        $distFastAtr = $ctx->inAtr(abs($price - $fast));
        $atNearTolerance = (float) $this->cfg('ema_touch_atr', 0.35);

        $atFast = $distFastAtr <= $atNearTolerance;
        $atMid = $ctx->inAtr(abs($price - $mid)) <= $atNearTolerance;

        $result->data = [
            'ema_fast' => $fast,
            'ema_mid' => $mid,
            'ema_slow' => $slow,
            'ema_fast_period' => $ctx->emaFastPeriod,
            'ema_mid_period' => $ctx->emaMidPeriod,
            'ema_slow_period' => $ctx->emaSlowPeriod,
            'fast_slope' => round($ctx->emaFastSlope, 4),
            'mid_slope' => round($ctx->emaMidSlope, 4),
            'slow_slope' => round($ctx->emaSlowSlope, 4),
            'price_vs_fast' => $price > $fast ? 'above' : 'below',
            'distance_from_fast_atr' => round($distFastAtr, 2),
            'at_fast_ema' => $atFast,
            'at_mid_ema' => $atMid,
            'aligned' => $bullAligned ? 'bullish' : ($bearAligned ? 'bearish' : ($partialBull ? 'partial_bullish' : ($partialBear ? 'partial_bearish' : 'mixed'))),
        ];

        // --- Directional read -------------------------------------------
        if ($bullAligned) {
            $result->direction = AnalyzerResult::BULLISH;
            $result->strength = $ctx->emaFastSlope > 0 ? 0.9 : 0.7;
            $result->addObservation('ema_stacked_bullish');
            $result->addReasoning('EMAs are fanned bullishly (price > 20 > 100' . ($slow !== null ? ' > long-term EMA' : '') . '), the classic trending-up configuration.');
            if ($ctx->emaFastSlope > 0) {
                $result->addReasoning('The fast EMA is rising, confirming momentum is with the buyers.');
            }
        } elseif ($bearAligned) {
            $result->direction = AnalyzerResult::BEARISH;
            $result->strength = $ctx->emaFastSlope < 0 ? 0.9 : 0.7;
            $result->addObservation('ema_stacked_bearish');
            $result->addReasoning('EMAs are fanned bearishly (price < 20 < 100' . ($slow !== null ? ' < long-term EMA' : '') . '), the classic trending-down configuration.');
            if ($ctx->emaFastSlope < 0) {
                $result->addReasoning('The fast EMA is falling, confirming momentum is with the sellers.');
            }
        } elseif ($partialBull) {
            $result->direction = AnalyzerResult::BULLISH;
            $result->strength = 0.45;
            $result->addObservation('ema_partial_bullish');
            $result->addReasoning('Short and mid EMAs lean bullish but the long-term EMA has not confirmed — an emerging up-trend.');
        } elseif ($partialBear) {
            $result->direction = AnalyzerResult::BEARISH;
            $result->strength = 0.45;
            $result->addObservation('ema_partial_bearish');
            $result->addReasoning('Short and mid EMAs lean bearish but the long-term EMA has not confirmed — an emerging down-trend.');
        } else {
            $result->direction = AnalyzerResult::NEUTRAL;
            $result->strength = 0.15;
            $result->addObservation('ema_mixed');
            $result->addReasoning('EMAs are intertwined — price is not respecting a clean directional EMA structure.');
        }

        // --- Dynamic support / resistance interaction -------------------
        if ($atFast && $result->direction !== AnalyzerResult::NEUTRAL) {
            if ($result->isBullish() && $price >= $fast) {
                $result->addObservation('at_dynamic_support');
                $result->addReasoning('Price has pulled back into the rising 20 EMA, which is acting as dynamic support.');
            } elseif ($result->isBearish() && $price <= $fast) {
                $result->addObservation('at_dynamic_resistance');
                $result->addReasoning('Price has rallied back into the falling 20 EMA, which is acting as dynamic resistance.');
            }
        } elseif ($distFastAtr >= (float) $this->cfg('ema_stretched_atr', 2.5)) {
            $result->addObservation('stretched_from_ema');
            $result->addReasoning('Price is stretched well away from the 20 EMA — extended, so chasing here carries poor risk/reward.');
            // Being stretched reduces conviction to *initiate* in the trend direction.
            $result->strength = max(0.2, $result->strength - 0.2);
        }

        return $result;
    }
}

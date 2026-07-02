<?php

namespace App\Services\Analysis\PriceAction\Analyzers;

use App\Services\Analysis\PriceAction\Support\AnalyzerResult;
use App\Services\Analysis\PriceAction\Support\MarketContext;

/**
 * PriceActionEventAnalyzer - Reads meaningful trading EVENTS, not shapes.
 *
 * This is the heart of the "think in events, not patterns" philosophy. It
 * inspects the most recent price behaviour in the context of nearby swing
 * levels and EMAs to detect events such as:
 *  - strong_bullish_close / strong_bearish_close
 *  - bullish_rejection / bearish_rejection
 *  - ema_bounce / ema_rejection
 *  - breakout / failed_breakout / failed_breakdown
 *  - liquidity_sweep
 *
 * Each event is only emitted when the surrounding context makes it
 * meaningful — a rejection at a level matters, a rejection in open air does
 * not. Context over patterns.
 */
class PriceActionEventAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'price_action';
    }

    public function analyze(MarketContext $ctx): AnalyzerResult
    {
        $result = new AnalyzerResult();

        if ($ctx->count < 3) {
            return $this->neutral('Not enough candles to read price-action events.');
        }

        $c = $ctx->current;
        $range = max(MarketContext::range($c), 1e-9);
        $body = MarketContext::body($c);
        $bodyToRange = $body / $range;
        $upperWick = MarketContext::upperWick($c) / $range;
        $lowerWick = MarketContext::lowerWick($c) / $range;

        $events = [];
        $bullScore = 0.0;
        $bearScore = 0.0;

        $strongBodyRatio = (float) $this->cfg('strong_close_ratio', 0.65);
        $rejectionWick = (float) $this->cfg('rejection_wick_ratio', 0.5);
        $atFastEma = $ctx->inAtr(abs($ctx->lastPrice - ($ctx->emaFast ?? $ctx->lastPrice))) <= (float) $this->cfg('ema_touch_atr', 0.35);

        // --- Strong directional close -----------------------------------
        if (MarketContext::isBull($c) && $bodyToRange >= $strongBodyRatio) {
            $events[] = 'strong_bullish_close';
            $bullScore += 0.6;
            $result->addReasoning('The candle closed strongly near its high with a dominant body — buyers were in control into the close.');
        } elseif (MarketContext::isBear($c) && $bodyToRange >= $strongBodyRatio) {
            $events[] = 'strong_bearish_close';
            $bearScore += 0.6;
            $result->addReasoning('The candle closed weakly near its low with a dominant body — sellers were in control into the close.');
        }

        // --- Rejections (wick-driven) -----------------------------------
        if ($lowerWick >= $rejectionWick && $lowerWick > $upperWick) {
            $events[] = 'bullish_rejection';
            $bullScore += 0.55;
            $result->addReasoning('A long lower wick shows sellers were rejected and buyers stepped in from below.');
            if ($atFastEma) {
                $events[] = 'ema_bounce';
                $bullScore += 0.2;
                $result->addReasoning('That rejection happened right at the fast EMA — a dynamic-support bounce.');
            }
        }
        if ($upperWick >= $rejectionWick && $upperWick > $lowerWick) {
            $events[] = 'bearish_rejection';
            $bearScore += 0.55;
            $result->addReasoning('A long upper wick shows buyers were rejected and sellers stepped in from above.');
            if ($atFastEma) {
                $events[] = 'ema_rejection';
                $bearScore += 0.2;
                $result->addReasoning('That rejection happened right at the fast EMA — a dynamic-resistance rejection.');
            }
        }

        // --- Breakout / failed breakout vs recent swing levels ----------
        $lastHigh = $ctx->lastSwingHigh()['price'] ?? null;
        $lastLow = $ctx->lastSwingLow()['price'] ?? null;
        $buffer = $ctx->atr * (float) $this->cfg('breakout_buffer_atr', 0.1);
        $high = (float) $c['high'];
        $low = (float) $c['low'];
        $close = (float) $c['close'];

        if ($lastHigh !== null) {
            if ($close > $lastHigh + $buffer) {
                $events[] = 'breakout';
                $bullScore += 0.5;
                $result->addReasoning('Price closed above the last swing high — a genuine breakout with acceptance above the level.');
            } elseif ($high > $lastHigh + $buffer && $close < $lastHigh) {
                // Poked above then closed back inside -> trap.
                $events[] = 'failed_breakout';
                $events[] = 'liquidity_sweep';
                $bearScore += 0.6;
                $result->addReasoning('Price spiked above the swing high then closed back below it — a failed breakout / liquidity sweep that trapped breakout buyers.');
            }
        }

        if ($lastLow !== null) {
            if ($close < $lastLow - $buffer) {
                $events[] = 'breakdown';
                $bearScore += 0.5;
                $result->addReasoning('Price closed below the last swing low — a genuine breakdown with acceptance below the level.');
            } elseif ($low < $lastLow - $buffer && $close > $lastLow) {
                $events[] = 'failed_breakdown';
                $events[] = 'liquidity_sweep';
                $bullScore += 0.6;
                $result->addReasoning('Price spiked below the swing low then closed back above it — a failed breakdown / liquidity sweep that trapped breakout sellers.');
            }
        }

        // --- Resolve direction ------------------------------------------
        foreach (array_unique($events) as $e) {
            $result->addObservation($e);
        }

        if ($bullScore == 0.0 && $bearScore == 0.0) {
            $result->addReasoning('No decisive price-action event on the latest candle.');

            return $result;
        }

        if ($bullScore >= $bearScore) {
            $result->direction = AnalyzerResult::BULLISH;
            $result->strength = min(1.0, $bullScore);
        } else {
            $result->direction = AnalyzerResult::BEARISH;
            $result->strength = min(1.0, $bearScore);
        }

        $result->data = [
            'bull_score' => round($bullScore, 2),
            'bear_score' => round($bearScore, 2),
            'body_to_range' => round($bodyToRange, 2),
        ];

        return $result;
    }
}

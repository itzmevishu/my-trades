<?php

namespace App\Services\Analysis\PriceAction\Analyzers;

use App\Services\Analysis\PriceAction\Support\AnalyzerResult;
use App\Services\Analysis\PriceAction\Support\MarketContext;

/**
 * CandlestickConfirmationAnalyzer - Confirmation ONLY.
 *
 * Traditional candlestick patterns (pinbar, engulfing, doji, inside bar,
 * morning/evening star) are recognised here purely as *confirmation*. They
 * carry a deliberately small weight in the aggregator:
 *   - Their ABSENCE must never invalidate an otherwise high-quality setup.
 *   - Their PRESENCE must never create a trade without supporting context.
 *
 * This analyzer therefore only reports which patterns are present and a
 * gentle directional lean; it never dominates the final decision.
 */
class CandlestickConfirmationAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'candlestick_confirmation';
    }

    public function analyze(MarketContext $ctx): AnalyzerResult
    {
        $result = new AnalyzerResult();

        if ($ctx->count < 2) {
            return $result;
        }

        $patterns = [];
        $bull = 0;
        $bear = 0;

        $c = $ctx->current;
        $p = $ctx->previous;

        $body = MarketContext::body($c);
        $range = max(MarketContext::range($c), 1e-9);
        $upper = MarketContext::upperWick($c);
        $lower = MarketContext::lowerWick($c);
        $bodyToRange = $body / $range;

        // --- Doji --------------------------------------------------------
        if ($bodyToRange <= (float) $this->cfg('doji_body_ratio', 0.1)) {
            $patterns[] = 'doji';
        }

        // --- Pinbar (hammer / shooting star) -----------------------------
        if ($lower >= $body * 2 && $upper <= $body && $body > 0) {
            $patterns[] = 'bullish_pinbar';
            $bull++;
        } elseif ($upper >= $body * 2 && $lower <= $body && $body > 0) {
            $patterns[] = 'bearish_pinbar';
            $bear++;
        }

        // --- Engulfing ---------------------------------------------------
        if ($p) {
            $cTop = max((float) $c['open'], (float) $c['close']);
            $cBot = min((float) $c['open'], (float) $c['close']);
            $pTop = max((float) $p['open'], (float) $p['close']);
            $pBot = min((float) $p['open'], (float) $p['close']);

            if (MarketContext::isBear($p) && MarketContext::isBull($c) && $cTop >= $pTop && $cBot <= $pBot) {
                $patterns[] = 'bullish_engulfing';
                $bull++;
            } elseif (MarketContext::isBull($p) && MarketContext::isBear($c) && $cTop >= $pTop && $cBot <= $pBot) {
                $patterns[] = 'bearish_engulfing';
                $bear++;
            }

            // --- Inside bar ---------------------------------------------
            if ((float) $c['high'] <= (float) $p['high'] && (float) $c['low'] >= (float) $p['low']) {
                $patterns[] = 'inside_bar';
            }
        }

        // --- Morning / evening star (3-candle) ---------------------------
        if ($ctx->count >= 3) {
            $a = $ctx->candles[$ctx->count - 3];
            $b = $ctx->candles[$ctx->count - 2];
            $bBody = MarketContext::body($b);
            $aBody = MarketContext::body($a);
            $smallMiddle = $bBody <= $aBody * 0.5;

            if ($smallMiddle && MarketContext::isBear($a) && MarketContext::isBull($c) && (float) $c['close'] > ((float) $a['open'] + (float) $a['close']) / 2) {
                $patterns[] = 'morning_star';
                $bull++;
            } elseif ($smallMiddle && MarketContext::isBull($a) && MarketContext::isBear($c) && (float) $c['close'] < ((float) $a['open'] + (float) $a['close']) / 2) {
                $patterns[] = 'evening_star';
                $bear++;
            }
        }

        $result->observations = array_values(array_unique($patterns));
        $result->data = ['patterns' => $result->observations];

        if ($bull > $bear) {
            $result->direction = AnalyzerResult::BULLISH;
            $result->strength = min(0.6, 0.3 * $bull);
            $result->addReasoning('Bullish candlestick confirmation present (' . implode(', ', $result->observations) . '), supporting — but not driving — the read.');
        } elseif ($bear > $bull) {
            $result->direction = AnalyzerResult::BEARISH;
            $result->strength = min(0.6, 0.3 * $bear);
            $result->addReasoning('Bearish candlestick confirmation present (' . implode(', ', $result->observations) . '), supporting — but not driving — the read.');
        } elseif (!empty($patterns)) {
            $result->addReasoning('Candlestick shapes present (' . implode(', ', $result->observations) . ') but directionally neutral.');
        }

        return $result;
    }
}

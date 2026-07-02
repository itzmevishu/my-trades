<?php

namespace App\Services\Analysis\PriceAction\Analyzers;

use App\Services\Analysis\PriceAction\Support\AnalyzerResult;
use App\Services\Analysis\PriceAction\Support\MarketContext;

/**
 * MarketStructureAnalyzer - The foundation of every decision.
 *
 * Turns raw swing points into structural events:
 *  - Break of Structure (BOS): trend continuation confirmed by breaking the
 *    prior protected swing in the direction of trend.
 *  - Change of Character (CHoCH): the first counter-trend break, i.e. an
 *    early warning that control is shifting.
 *  - Pullback vs Reversal: is price merely retracing inside intact structure,
 *    or has structure actually broken the other way?
 *  - Retest: price returning to a broken level to confirm it.
 *
 * Structure is king: no signal should ignore what this analyzer reports.
 */
class MarketStructureAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'market_structure';
    }

    public function analyze(MarketContext $ctx): AnalyzerResult
    {
        $highs = $ctx->swingHighs;
        $lows = $ctx->swingLows;

        if (count($highs) < 2 || count($lows) < 2) {
            $r = $this->neutral('Insufficient confirmed swings to establish market structure.');
            $r->data = ['state' => 'undefined', 'bos' => false, 'choch' => false];

            return $r;
        }

        $lastTwoHighs = array_slice($highs, -2);
        $lastTwoLows = array_slice($lows, -2);

        $hh = $lastTwoHighs[1]['price'] > $lastTwoHighs[0]['price'];
        $lh = $lastTwoHighs[1]['price'] < $lastTwoHighs[0]['price'];
        $hl = $lastTwoLows[1]['price'] > $lastTwoLows[0]['price'];
        $ll = $lastTwoLows[1]['price'] < $lastTwoLows[0]['price'];

        // Establish the prevailing structural state.
        $state = 'complex';
        if ($hh && $hl) {
            $state = 'HH_HL';
        } elseif ($lh && $ll) {
            $state = 'LH_LL';
        } elseif ($hl && $lh) {
            $state = 'contracting'; // higher lows + lower highs = triangle
        } elseif ($hh && $ll) {
            $state = 'expanding'; // broadening
        }

        $priorHigh = $lastTwoHighs[1]['price'];
        $priorLow = $lastTwoLows[1]['price'];
        $close = $ctx->lastPrice;
        $high = (float) $ctx->current['high'];
        $low = (float) $ctx->current['low'];

        $bos = false;
        $choch = false;
        $direction = AnalyzerResult::NEUTRAL;
        $strength = 0.3;
        $observations = [];
        $reasoning = [];

        $breakBufferAtr = (float) $this->cfg('bos_buffer_atr', 0.1);
        $buffer = $ctx->atr * $breakBufferAtr;

        // --- Break of structure / change of character -------------------
        if ($close > $priorHigh + $buffer) {
            // Broke above the last swing high.
            if ($state === 'LH_LL' || $state === 'contracting') {
                $choch = true;
                $direction = AnalyzerResult::BULLISH;
                $strength = 0.75;
                $observations[] = 'choch_bullish';
                $reasoning[] = 'Price broke above the most recent lower-high — a bullish Change of Character warning that the down-move may be ending.';
            } else {
                $bos = true;
                $direction = AnalyzerResult::BULLISH;
                $strength = 0.85;
                $observations[] = 'bos_bullish';
                $reasoning[] = 'Price broke above the prior swing high, confirming a bullish Break of Structure and trend continuation.';
            }
        } elseif ($close < $priorLow - $buffer) {
            if ($state === 'HH_HL' || $state === 'contracting') {
                $choch = true;
                $direction = AnalyzerResult::BEARISH;
                $strength = 0.75;
                $observations[] = 'choch_bearish';
                $reasoning[] = 'Price broke below the most recent higher-low — a bearish Change of Character warning that the up-move may be ending.';
            } else {
                $bos = true;
                $direction = AnalyzerResult::BEARISH;
                $strength = 0.85;
                $observations[] = 'bos_bearish';
                $reasoning[] = 'Price broke below the prior swing low, confirming a bearish Break of Structure and trend continuation.';
            }
        } else {
            // No fresh break -> classify the current position within structure.
            if ($state === 'HH_HL') {
                $direction = AnalyzerResult::BULLISH;
                $strength = 0.6;
                $observations[] = 'bullish_structure_intact';
                $reasoning[] = 'Bullish structure (higher highs and higher lows) remains intact; price is working inside the trend.';
                if ($low <= $priorLow + $ctx->atr && $close > $priorLow) {
                    $observations[] = 'pullback';
                    $reasoning[] = 'The recent dip is holding above the last higher-low, so this reads as a pullback rather than a reversal.';
                }
            } elseif ($state === 'LH_LL') {
                $direction = AnalyzerResult::BEARISH;
                $strength = 0.6;
                $observations[] = 'bearish_structure_intact';
                $reasoning[] = 'Bearish structure (lower highs and lower lows) remains intact; price is working inside the trend.';
                if ($high >= $priorHigh - $ctx->atr && $close < $priorHigh) {
                    $observations[] = 'pullback';
                    $reasoning[] = 'The recent bounce is failing below the last lower-high, so this reads as a pullback rather than a reversal.';
                }
            } elseif ($state === 'contracting') {
                $observations[] = 'compression';
                $reasoning[] = 'Higher lows into lower highs — structure is compressing into a triangle, coiling for a breakout.';
            } elseif ($state === 'expanding') {
                $observations[] = 'range_expansion';
                $reasoning[] = 'Broadening structure — volatility is expanding with an unstable, news-like character.';
            }
        }

        // --- Retest detection -------------------------------------------
        // Price tapping back into a freshly broken level from the other side.
        if (!$bos && !$choch) {
            if ($direction === AnalyzerResult::BULLISH && abs($low - $priorHigh) <= $ctx->atr * 0.5 && $close > $priorHigh) {
                $observations[] = 'retest';
                $reasoning[] = 'Price is retesting the broken swing high from above, a classic continuation setup.';
            } elseif ($direction === AnalyzerResult::BEARISH && abs($high - $priorLow) <= $ctx->atr * 0.5 && $close < $priorLow) {
                $observations[] = 'retest';
                $reasoning[] = 'Price is retesting the broken swing low from below, a classic continuation setup.';
            }
        }

        $result = new AnalyzerResult($direction, $strength, $observations, $reasoning);
        $result->data = [
            'state' => $state,
            'bos' => $bos,
            'choch' => $choch,
            'prior_swing_high' => $priorHigh,
            'prior_swing_low' => $priorLow,
            'is_pullback' => in_array('pullback', $observations, true),
            'is_retest' => in_array('retest', $observations, true),
        ];

        return $result;
    }
}

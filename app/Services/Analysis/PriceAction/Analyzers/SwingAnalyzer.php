<?php

namespace App\Services\Analysis\PriceAction\Analyzers;

use App\Services\Analysis\PriceAction\Support\AnalyzerResult;
use App\Services\Analysis\PriceAction\Support\MarketContext;

/**
 * SwingAnalyzer - Reads the sequence of swing highs and lows.
 *
 * This is the raw material for market structure. It answers:
 *  - Are we making higher highs / higher lows (uptrend skeleton)?
 *  - Lower highs / lower lows (downtrend skeleton)?
 *  - Where are the most recent protected swing points that, if broken,
 *    would signal a Break of Structure?
 *
 * It deliberately produces a soft bias only; MarketStructureAnalyzer turns
 * these swings into BOS / CHoCH events.
 */
class SwingAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'swings';
    }

    public function analyze(MarketContext $ctx): AnalyzerResult
    {
        $highs = $ctx->recentSwingHighs(3);
        $lows = $ctx->recentSwingLows(3);

        if (count($highs) < 2 || count($lows) < 2) {
            return $this->neutral('Not enough confirmed swing points to read structure yet.');
        }

        $highPrices = array_map(static fn ($s) => $s['price'], $highs);
        $lowPrices = array_map(static fn ($s) => $s['price'], $lows);

        $higherHighs = $this->isRising($highPrices);
        $higherLows = $this->isRising($lowPrices);
        $lowerHighs = $this->isFalling($highPrices);
        $lowerLows = $this->isFalling($lowPrices);

        $result = new AnalyzerResult();
        $result->data = [
            'higher_highs' => $higherHighs,
            'higher_lows' => $higherLows,
            'lower_highs' => $lowerHighs,
            'lower_lows' => $lowerLows,
            'last_swing_high' => $ctx->lastSwingHigh()['price'] ?? null,
            'last_swing_low' => $ctx->lastSwingLow()['price'] ?? null,
        ];

        if ($higherHighs && $higherLows) {
            $result->direction = AnalyzerResult::BULLISH;
            $result->strength = 0.8;
            $result->addObservation('higher_high');
            $result->addObservation('higher_low');
            $result->addReasoning('Price is carving higher highs and higher lows — a healthy bullish swing structure.');
        } elseif ($lowerHighs && $lowerLows) {
            $result->direction = AnalyzerResult::BEARISH;
            $result->strength = 0.8;
            $result->addObservation('lower_high');
            $result->addObservation('lower_low');
            $result->addReasoning('Price is carving lower highs and lower lows — a healthy bearish swing structure.');
        } elseif ($higherLows && !$lowerHighs) {
            $result->direction = AnalyzerResult::BULLISH;
            $result->strength = 0.45;
            $result->addObservation('higher_low');
            $result->addReasoning('Buyers are stepping in earlier each time (higher lows) even though highs are capped.');
        } elseif ($lowerHighs && !$higherLows) {
            $result->direction = AnalyzerResult::BEARISH;
            $result->strength = 0.45;
            $result->addObservation('lower_high');
            $result->addReasoning('Sellers are capping rallies earlier each time (lower highs).');
        } else {
            $result->direction = AnalyzerResult::NEUTRAL;
            $result->strength = 0.2;
            $result->addObservation('swing_conflict');
            $result->addReasoning('Swing highs and lows are mixed — no clean directional skeleton.');
        }

        return $result;
    }

    private function isRising(array $values): bool
    {
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i] <= $values[$i - 1]) {
                return false;
            }
        }

        return count($values) >= 2;
    }

    private function isFalling(array $values): bool
    {
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i] >= $values[$i - 1]) {
                return false;
            }
        }

        return count($values) >= 2;
    }
}

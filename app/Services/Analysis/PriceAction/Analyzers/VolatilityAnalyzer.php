<?php

namespace App\Services\Analysis\PriceAction\Analyzers;

use App\Services\Analysis\PriceAction\Support\AnalyzerResult;
use App\Services\Analysis\PriceAction\Support\MarketContext;

/**
 * VolatilityAnalyzer - Is the market compressing or expanding?
 *
 * Volatility regime tells the trader whether to expect breakouts (after
 * compression) or mean-reversion/exhaustion (after expansion). It is a
 * context modifier rather than a directional signal, so it usually stays
 * neutral but emits behavioural events:
 *  - compression     : recent ranges shrinking vs the longer average (coiling)
 *  - range_expansion : recent ranges blowing out vs the longer average
 *  - normal_volatility
 *
 * Everything is measured against ATR and rolling ranges — fully adaptive.
 */
class VolatilityAnalyzer extends AbstractAnalyzer
{
    public function key(): string
    {
        return 'volatility';
    }

    public function analyze(MarketContext $ctx): AnalyzerResult
    {
        if ($ctx->count < 10) {
            return $this->neutral('Not enough candles to judge the volatility regime.');
        }

        // Short-window range vs long-window range.
        $shortWin = (int) $this->cfg('vol_short_window', 5);
        $longWin = (int) $this->cfg('vol_long_window', 20);

        $shortRanges = $this->avgRange(array_slice($ctx->candles, -$shortWin));
        $longRanges = $this->avgRange(array_slice($ctx->candles, -$longWin));

        $ratio = $longRanges > 0 ? $shortRanges / $longRanges : 1.0;

        $result = new AnalyzerResult();
        $result->data = [
            'atr' => round($ctx->atr, 2),
            'short_avg_range' => round($shortRanges, 2),
            'long_avg_range' => round($longRanges, 2),
            'expansion_ratio' => round($ratio, 2),
        ];

        $compressionMax = (float) $this->cfg('compression_ratio', 0.7);
        $expansionMin = (float) $this->cfg('expansion_ratio', 1.4);

        if ($ratio <= $compressionMax) {
            $result->data['regime'] = 'compression';
            $result->addObservation('compression');
            $result->addReasoning('Recent candle ranges have contracted well below their longer-term average — the market is coiling and energy is building for an expansion move.');
        } elseif ($ratio >= $expansionMin) {
            $result->data['regime'] = 'expansion';
            $result->addObservation('range_expansion');
            $result->addReasoning('Recent ranges have blown out above their longer-term average — volatility is expanding, so moves are fast but chase-risk is high.');
        } else {
            $result->data['regime'] = 'normal';
            $result->addReasoning('Volatility is around its normal level for this market.');
        }

        return $result;
    }

    private function avgRange(array $candles): float
    {
        if (empty($candles)) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($candles as $c) {
            $sum += MarketContext::range($c);
        }

        return $sum / count($candles);
    }
}

<?php

namespace App\Services\Analysis;

use App\Services\BaseService;

/**
 * EMA Calculator Service
 * 
 * Calculates Exponential Moving Averages (20, 100, 200)
 * from candle data.
 * 
 * Phase: Week 4 - Technical Analysis Engine
 */
class EMACalculator extends BaseService
{
    /**
     * Calculate EMA for given period
     */
    public function calculateEMA(array $candles, int $period): array
    {
        // TODO: Implement EMA calculation algorithm
        $this->logInfo("Calculating EMA {$period} for " . count($candles) . " candles");
        return [];
    }

    /**
     * Calculate multiple EMAs at once
     */
    public function calculateMultipleEMAs(array $candles, array $periods = [20, 100, 200]): array
    {
        $results = [];
        
        foreach ($periods as $period) {
            $results[$period] = $this->calculateEMA($candles, $period);
        }
        
        return $results;
    }

    /**
     * Get latest EMA value
     */
    public function getLatestEMA(array $candles, int $period): ?float
    {
        $emaValues = $this->calculateEMA($candles, $period);
        return $emaValues[0] ?? null;
    }
}

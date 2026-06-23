<?php

namespace App\Services\Analysis;

use App\Services\BaseService;

/**
 * EMACalculator - Calculate Exponential Moving Averages
 * 
 * Calculates 20, 100, and 200 period EMAs for technical analysis.
 * Used for detecting EMA confluence in trade setups.
 * 
 * Formula: EMA = (Close - EMA_prev) * multiplier + EMA_prev
 * Where multiplier = 2 / (period + 1)
 */
class EMACalculator extends BaseService
{
    /**
     * Calculate a single EMA for given period
     * 
     * @param array $candles Array of candles with 'close' prices
     * @param int $period EMA period (20, 100, 200, etc.)
     * @return float|null EMA value or null if insufficient data
     */
    public function calculateEMA(array $candles, int $period): ?float
    {
        if (count($candles) < $period) {
            $this->logWarning("Insufficient candles for EMA{$period}: " . count($candles) . " provided, {$period} needed");
            return null;
        }

        $closes = array_column($candles, 'close');
        
        // Calculate initial SMA for first EMA value
        $sma = array_sum(array_slice($closes, 0, $period)) / $period;
        
        $multiplier = 2 / ($period + 1);
        $ema = $sma;
        
        // Calculate EMA for remaining candles
        for ($i = $period; $i < count($closes); $i++) {
            $ema = (($closes[$i] - $ema) * $multiplier) + $ema;
        }
        
        return round($ema, 2);
    }

    /**
     * Calculate multiple EMAs at once (20, 100, 200)
     * 
     * @param array $candles Array of candles
     * @return array ['ema_20' => float, 'ema_100' => float, 'ema_200' => float]
     */
    public function calculateMultipleEMAs(array $candles): array
    {
        $this->logInfo("Calculating EMAs", [
            'candle_count' => count($candles),
            'periods' => [20, 100, 200]
        ]);

        return [
            'ema_20' => $this->calculateEMA($candles, 20),
            'ema_100' => $this->calculateEMA($candles, 100),
            'ema_200' => $this->calculateEMA($candles, 200),
        ];
    }

    /**
     * Get latest EMA value from array of candles
     * 
     * @param array $candles Candles array
     * @param int $period EMA period
     * @return float|null Latest EMA value
     */
    public function getLatestEMA(array $candles, int $period): ?float
    {
        return $this->calculateEMA($candles, $period);
    }

    /**
     * Calculate EMA series (all EMA values, not just the last)
     * Useful for plotting or detailed analysis
     * 
     * @param array $candles Array of candles
     * @param int $period EMA period
     * @return array Array of EMA values
     */
    public function calculateEMASeries(array $candles, int $period): array
    {
        if (count($candles) < $period) {
            return [];
        }

        $closes = array_column($candles, 'close');
        $multiplier = 2 / ($period + 1);
        $emaValues = [];
        
        // Initial SMA
        $sma = array_sum(array_slice($closes, 0, $period)) / $period;
        $ema = $sma;
        $emaValues[] = round($ema, 2);
        
        // Calculate EMA for each subsequent candle
        for ($i = $period; $i < count($closes); $i++) {
            $ema = (($closes[$i] - $ema) * $multiplier) + $ema;
            $emaValues[] = round($ema, 2);
        }
        
        return $emaValues;
    }

    /**
     * Check if price is near an EMA (within tolerance)
     * 
     * As per PRD: Price within 0.3% of EMA is considered "near"
     * 
     * @param float $price Current price
     * @param float $ema EMA value
     * @param float $tolerancePercent Tolerance as percentage (default 0.3%)
     * @return bool True if price is near EMA
     */
    public function isPriceNearEMA(float $price, float $ema, float $tolerancePercent = 0.3): bool
    {
        $tolerance = $ema * ($tolerancePercent / 100);
        $upperBound = $ema + $tolerance;
        $lowerBound = $ema - $tolerance;
        
        return $price >= $lowerBound && $price <= $upperBound;
    }

    /**
     * Check if price is near multiple EMAs (confluence)
     * 
     * Returns which EMAs the price is near
     * 
     * @param float $price Current price
     * @param array $emas ['ema_20' => float, 'ema_100' => float, 'ema_200' => float]
     * @param float $tolerancePercent Tolerance percentage
     * @return array ['ema_20' => bool, 'ema_100' => bool, 'ema_200' => bool]
     */
    public function checkEMAConfluence(float $price, array $emas, float $tolerancePercent = 0.3): array
    {
        $confluence = [];
        
        foreach ($emas as $key => $emaValue) {
            if ($emaValue === null) {
                $confluence[$key] = false;
                continue;
            }
            
            $confluence[$key] = $this->isPriceNearEMA($price, $emaValue, $tolerancePercent);
        }
        
        return $confluence;
    }

    /**
     * Count number of EMAs in confluence
     * 
     * @param float $price Current price
     * @param array $emas EMA values
     * @param float $tolerancePercent Tolerance
     * @return int Number of EMAs price is near (0-3)
     */
    public function countEMAConfluence(float $price, array $emas, float $tolerancePercent = 0.3): int
    {
        $confluence = $this->checkEMAConfluence($price, $emas, $tolerancePercent);
        return count(array_filter($confluence));
    }

    /**
     * Get EMA configuration from settings
     * 
     * Returns which EMAs to use and tolerance from settings
     * 
     * @return array Configuration
     */
    public function getEMAConfiguration(): array
    {
        return [
            'periods' => [20, 100, 200],
            'tolerance_percent' => setting('ema_proximity_tolerance', 0.3),
            'required_confluence' => 2, // At least 2 EMAs for good setup
        ];
    }

    /**
     * Validate EMA data quality
     * 
     * Checks if EMAs are calculated correctly and make sense
     * 
     * @param array $emas EMA values
     * @param float $currentPrice Current market price
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateEMAData(array $emas, float $currentPrice): array
    {
        $errors = [];
        
        // Check if EMAs exist
        if (!isset($emas['ema_20'], $emas['ema_100'], $emas['ema_200'])) {
            $errors[] = 'Missing EMA values';
        }
        
        // Check if EMAs are null
        foreach ($emas as $key => $value) {
            if ($value === null) {
                $errors[] = "{$key} is null - insufficient data";
            }
        }
        
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check if EMAs are in reasonable range (within 30% of current price)
        foreach ($emas as $key => $value) {
            $deviation = abs(($value - $currentPrice) / $currentPrice) * 100;
            if ($deviation > 30) {
                $errors[] = "{$key} is {$deviation}% away from current price - seems incorrect";
            }
        }
        
        // EMA 20 should typically be closer to price than EMA 200
        // (not always true in strong trends, but generally)
        $ema20Distance = abs($emas['ema_20'] - $currentPrice);
        $ema200Distance = abs($emas['ema_200'] - $currentPrice);
        
        if ($ema20Distance > $ema200Distance * 3) {
            $this->logWarning("EMA 20 is unusually far from price compared to EMA 200", [
                'ema_20_distance' => $ema20Distance,
                'ema_200_distance' => $ema200Distance
            ]);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

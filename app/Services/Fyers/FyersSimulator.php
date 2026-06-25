<?php

namespace App\Services\Fyers;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * FyersSimulator - Simulates Fyers API responses for local development
 * 
 * This class generates realistic Bank Nifty market data without needing
 * a static IP or real Fyers API credentials. Perfect for development!
 * 
 * Features:
 * - Realistic Bank Nifty candle data with proper OHLC relationships
 * - Simulates intraday volatility patterns
 * - Generates option premiums based on spot price
 * - Simulates paper order execution with slippage
 */
class FyersSimulator
{
    /**
     * Base price for Bank Nifty (typical range: 48,000-52,000)
     */
    private static int $basePrice = 50000;

    /**
     * Volatility percentage (1-3% typical intraday movement)
     */
    private static float $volatility = 0.015; // 1.5%

    /**
     * Generate simulated Bank Nifty candles
     * 
     * @param string $symbol Symbol (e.g., 'NSE:NIFTYBANK-INDEX')
     * @param string $timeframe Timeframe ('15m', '1D', '1W', '1M')
     * @param int $limit Number of candles to generate
     * @param Carbon|null $endTime End time (defaults to now)
     * @return array Array of candle data
     */
    public static function generateCandles(
        string $symbol,
        string $timeframe,
        int $limit,
        ?Carbon $endTime = null
    ): array {
        $endTime = $endTime ?? now();
        $candles = [];
        
        // Calculate interval in minutes
        $intervalMinutes = match($timeframe) {
            '15m' => 15,
            '1H' => 60,
            '1D' => 1440,
            '1W' => 10080,
            '1M' => 43200,
            default => 15
        };
        
        // Generate candles backwards from end time
        $currentPrice = static::$basePrice;
        
        for ($i = $limit - 1; $i >= 0; $i--) {
            $timestamp = $endTime->copy()->subMinutes($intervalMinutes * $i);
            
            // Add some randomness to price movement
            $priceChange = $currentPrice * static::$volatility * (rand(-100, 100) / 100);
            $currentPrice += $priceChange;
            
            // Ensure price stays in reasonable range
            $currentPrice = max(45000, min(55000, $currentPrice));
            
            // Generate OHLC with proper relationships
            $open = round($currentPrice, 2);
            $close = round($open + (rand(-200, 200)), 2);
            $high = round(max($open, $close) + rand(0, 100), 2);
            $low = round(min($open, $close) - rand(0, 100), 2);
            $volume = rand(1000000, 3000000);
            
            $candles[] = [
                'timestamp' => $timestamp->timestamp,
                'datetime' => $timestamp->format('Y-m-d H:i:s'),
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => $volume,
            ];
            
            // Update current price for next candle
            $currentPrice = $close;
        }
        
        return $candles;
    }

    /**
     * Generate a specific candle pattern for testing
     * 
     * @param string $pattern Pattern name ('bullish_engulfing', 'bearish_engulfing', 'pinbar', etc.)
     * @param float $basePrice Base price for the pattern
     * @return array Single candle data
     */
    public static function generatePattern(string $pattern, float $basePrice = 50000): array
    {
        $timestamp = now();
        
        return match($pattern) {
            'bullish_engulfing' => [
                'timestamp' => $timestamp->timestamp,
                'datetime' => $timestamp->format('Y-m-d H:i:s'),
                'open' => $basePrice - 100,
                'high' => $basePrice + 150,
                'low' => $basePrice - 120,
                'close' => $basePrice + 140,
                'volume' => 2000000,
            ],
            'bearish_engulfing' => [
                'timestamp' => $timestamp->timestamp,
                'datetime' => $timestamp->format('Y-m-d H:i:s'),
                'open' => $basePrice + 100,
                'high' => $basePrice + 120,
                'low' => $basePrice - 150,
                'close' => $basePrice - 140,
                'volume' => 2000000,
            ],
            'bullish_pinbar' => [
                'timestamp' => $timestamp->timestamp,
                'datetime' => $timestamp->format('Y-m-d H:i:s'),
                'open' => $basePrice - 50,
                'high' => $basePrice + 30,
                'low' => $basePrice - 200, // Long lower wick
                'close' => $basePrice + 20,
                'volume' => 1800000,
            ],
            'bearish_pinbar' => [
                'timestamp' => $timestamp->timestamp,
                'datetime' => $timestamp->format('Y-m-d H:i:s'),
                'open' => $basePrice + 50,
                'high' => $basePrice + 200, // Long upper wick
                'low' => $basePrice - 30,
                'close' => $basePrice - 20,
                'volume' => 1800000,
            ],
            default => static::generateCandles('NSE:NIFTYBANK-INDEX', '15m', 1)[0]
        };
    }

    /**
     * Get simulated Bank Nifty spot price
     * 
     * @return float Current simulated spot price
     */
    public static function getSpotPrice(): float
    {
        // Add intraday randomness
        $variance = static::$basePrice * 0.002; // 0.2% variance
        return round(static::$basePrice + rand(-$variance, $variance), 2);
    }

    /**
     * Calculate simulated option premium
     * 
     * Uses simplified Black-Scholes-like calculation for realistic premiums
     * 
     * @param float $spotPrice Current Bank Nifty spot price
     * @param float $strikePrice Option strike price
     * @param string $optionType 'CE' or 'PE'
     * @param int $daysToExpiry Days remaining until expiry
     * @return float Simulated premium
     */
    public static function getOptionPremium(
        float $spotPrice,
        float $strikePrice,
        string $optionType,
        int $daysToExpiry = 5
    ): float {
        $moneyness = $strikePrice - $spotPrice;
        
        // Base intrinsic value
        if ($optionType === 'CE') {
            $intrinsic = max(0, $spotPrice - $strikePrice);
        } else {
            $intrinsic = max(0, $strikePrice - $spotPrice);
        }
        
        // Time value (decreases as expiry approaches)
        $distanceFromSpot = abs($moneyness);
        $timeValue = ($daysToExpiry / 30) * (300 - ($distanceFromSpot * 0.2));
        $timeValue = max(5, $timeValue); // Min 5 points
        
        // Total premium
        $premium = $intrinsic + $timeValue;
        
        // Add some randomness (±5 points)
        $premium += rand(-500, 500) / 100;
        
        return round(max(5, $premium), 2);
    }

    /**
     * Simulate paper order execution
     * 
     * @param string $direction 'CALL' or 'PUT'
     * @param float $strikePrice Strike price
     * @param float $premium Expected premium
     * @param int $lots Number of lots (15 qty each)
     * @param string $orderType 'ENTRY' or 'EXIT'
     * @return array Order execution result
     */
    public static function simulatePaperOrder(
        string $direction,
        float $strikePrice,
        float $premium,
        int $lots,
        string $orderType = 'ENTRY'
    ): array {
        // Simulate slippage based on settings
        $slippagePercent = $orderType === 'ENTRY' 
            ? setting('entry_slippage_pct', 0.2)
            : setting('sl_slippage_pct', 1.0);
        
        $slippage = $premium * ($slippagePercent / 100);
        $filledPremium = round($premium + $slippage, 2);
        
        // Simulate execution delay (100-500ms)
        usleep(rand(100000, 500000));
        
        return [
            'status' => 'success',
            'order_id' => 'PAPER_' . time() . '_' . rand(1000, 9999),
            'symbol' => "NSE:BANKNIFTY26JUN{$strikePrice}{$direction}",
            'strike_price' => $strikePrice,
            'direction' => $direction,
            'quantity' => $lots * setting('banknifty_lot_size', 15),
            'order_type' => 'MARKET',
            'requested_premium' => $premium,
            'filled_premium' => $filledPremium,
            'slippage' => $slippage,
            'slippage_percent' => $slippagePercent,
            'timestamp' => now()->toDateTimeString(),
            'execution_time_ms' => rand(100, 500),
        ];
    }

    /**
     * Generate realistic historical candles for testing EMA/patterns
     * 
     * Creates a trending market with pullbacks for realistic testing
     * 
     * @param string $trendType 'bullish', 'bearish', or 'sideways'
     * @param int $count Number of candles
     * @return array Array of candles
     */
    public static function generateHistoricalCandles(string $trendType, int $count = 100): array
    {
        $candles = [];
        $currentPrice = static::$basePrice;
        $timestamp = now()->subMinutes(15 * $count);
        
        for ($i = 0; $i < $count; $i++) {
            // Trend bias
            $trendBias = match($trendType) {
                'bullish' => rand(0, 150),
                'bearish' => rand(-150, 0),
                'sideways' => rand(-50, 50),
                default => 0
            };
            
            // Add trend with noise
            $priceChange = $trendBias + rand(-100, 100);
            $currentPrice += $priceChange;
            
            // Keep in range
            $currentPrice = max(45000, min(55000, $currentPrice));
            
            // Generate OHLC
            $open = round($currentPrice, 2);
            $close = round($open + rand(-80, 80), 2);
            $high = round(max($open, $close) + rand(0, 60), 2);
            $low = round(min($open, $close) - rand(0, 60), 2);
            
            $candles[] = [
                'timestamp' => $timestamp->copy()->addMinutes(15 * $i)->timestamp,
                'datetime' => $timestamp->copy()->addMinutes(15 * $i)->format('Y-m-d H:i:s'),
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => rand(1000000, 3000000),
            ];
            
            $currentPrice = $close;
        }
        
        return $candles;
    }

    /**
     * Simulate daily candles for higher timeframe analysis
     * 
     * @param int $days Number of days to generate
     * @return array Array of daily candles
     */
    public static function generateDailyCandles(int $days = 30): array
    {
        $candles = [];
        $currentPrice = static::$basePrice;
        $startDate = now()->subDays($days);
        
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            
            // Skip weekends
            if ($date->isWeekend()) {
                continue;
            }
            
            // Daily range (typically 500-1500 points)
            $range = rand(500, 1500);
            $open = $currentPrice;
            $close = round($open + rand(-$range/2, $range/2), 2);
            $high = round(max($open, $close) + rand(0, $range/3), 2);
            $low = round(min($open, $close) - rand(0, $range/3), 2);
            
            $candles[] = [
                'timestamp' => $date->setTime(9, 15)->timestamp,
                'datetime' => $date->format('Y-m-d H:i:s'),
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => rand(50000000, 150000000),
            ];
            
            $currentPrice = $close;
        }
        
        return $candles;
    }

    /**
     * Set base price for simulation (useful for testing specific scenarios)
     * 
     * @param int $price New base price
     */
    public static function setBasePrice(int $price): void
    {
        static::$basePrice = $price;
    }

    /**
     * Set volatility for simulation
     * 
     * @param float $volatility Volatility as decimal (0.01 = 1%)
     */
    public static function setVolatility(float $volatility): void
    {
        static::$volatility = $volatility;
    }

    /**
     * Reset to default values
     */
    public static function reset(): void
    {
        static::$basePrice = 50000;
        static::$volatility = 0.015;
    }
}

<?php

namespace App\Services\Fyers;

use App\Services\BaseService;
use App\Models\CandleCache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

/**
 * Fyers Data Service
 * 
 * Fetches market data including candles, option chain, and real-time prices
 * from Fyers API.
 * 
 * Phase: Week 3 - Data Pipeline
 */
class FyersDataService extends BaseService
{
    private string $baseUrl = 'https://api-t1.fyers.in/data';
    private FyersAuthService $authService;
    private RateLimiter $rateLimiter;
    
    public function __construct()
    {
        $this->authService = new FyersAuthService();
        $this->rateLimiter = new RateLimiter();
    }
    
    /**
     * Fetch historical candles from Fyers API (with caching)
     */
    public function fetchCandles(string $symbol, string $timeframe, int $limit = 100): array
    {
        // Try to get from cache first
        $cachedCandles = $this->getCachedCandles($symbol, $timeframe, $limit);
        
        if (!empty($cachedCandles)) {
            $this->logInfo('Using cached candles', [
                'symbol' => $symbol,
                'timeframe' => $timeframe,
                'count' => count($cachedCandles),
            ]);
            return $cachedCandles;
        }
        
        // Cache miss - fetch from API
        $this->logInfo('Cache miss - fetching from Fyers API', [
            'symbol' => $symbol,
            'timeframe' => $timeframe,
        ]);
        
        $accessToken = $this->authService->getAccessToken();
        
        if (!$accessToken) {
            $this->logError('No Fyers access token configured');
            throw new \Exception('Fyers API access token not available. Please authenticate via /fyers/auth');
        }
        
        try {
            // Wait for rate limiter
            $this->rateLimiter->waitForSlot();
            
            // Convert timeframe to Fyers format
            $resolution = $this->convertTimeframe($timeframe);
            
            // Calculate date range
            $rangeTo = Carbon::now()->timestamp;
            $rangeFrom = Carbon::now()->subDays($this->calculateDays($timeframe, $limit))->timestamp;
            
            // Fyers API requires Authorization header in format: {app_id}:{access_token}
            $appId = setting('fyers_client_id');
            
            $this->logInfo('Fetching from Fyers API', [
                'url' => "{$this->baseUrl}/history",
                'symbol' => $this->convertSymbol($symbol),
                'resolution' => $resolution,
                'app_id' => $appId,
                'has_token' => !empty($accessToken),
            ]);
            
            $response = Http::withHeaders([
                'Authorization' => "{$appId}:{$accessToken}",
            ])->get("{$this->baseUrl}/history", [
                'symbol' => $this->convertSymbol($symbol),
                'resolution' => $resolution,
                'date_format' => '0', // Unix timestamp
                'range_from' => $rangeFrom,
                'range_to' => $rangeTo,
                'cont_flag' => '1',
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['candles']) && is_array($data['candles'])) {
                    $formattedCandles = $this->formatCandles($data['candles']);
                    
                    // Store in cache
                    $this->cacheCandles($symbol, $timeframe, $formattedCandles);
                    
                    return $formattedCandles;
                }
            }
            
            $this->logError('Fyers API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => "{$this->baseUrl}/history",
                'symbol' => $this->convertSymbol($symbol),
                'resolution' => $resolution,
            ]);
            throw new \Exception('Fyers API returned error: ' . $response->body());
            
        } catch (\Exception $e) {
            $this->logError('Fyers fetch candles exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get Bank Nifty spot price from Fyers
     */
    public function getBankNiftySpotPrice(): float
    {
        $accessToken = $this->authService->getAccessToken();
        
        if (!$accessToken) {
            throw new \Exception('Fyers API access token not available');
        }
        
        try {
            $this->rateLimiter->waitForSlot();
            
            $appId = setting('fyers_client_id');
            
            $response = Http::withHeaders([
                'Authorization' => "{$appId}:{$accessToken}",
            ])->get("{$this->baseUrl}/quotes", [
                'symbols' => 'NSE:NIFTYBANK-INDEX',
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['d'][0]['v']['lp'])) {
                    return (float) $data['d'][0]['v']['lp'];
                }
            }
            
            $this->logError('Fyers spot price error: ' . $response->body());
            throw new \Exception('Fyers API error getting spot price: ' . $response->body());
            
        } catch (\Exception $e) {
            $this->logError('Fyers spot price exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get option premium (LTP) from Fyers
     */
    public function getOptionLTP(string $symbol): float
    {
        $accessToken = $this->authService->getAccessToken();
        
        if (!$accessToken) {
            throw new \Exception('Fyers API access token not available');
        }
        
        try {
            $this->rateLimiter->waitForSlot();
            
            $appId = setting('fyers_client_id');
            
            $response = Http::withHeaders([
                'Authorization' => "{$appId}:{$accessToken}",
            ])->get("{$this->baseUrl}/quotes", [
                'symbols' => $symbol,
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['d'][0]['v']['lp'])) {
                    return (float) $data['d'][0]['v']['lp'];
                }
            }
            
            $this->logError('Fyers option LTP error: ' . $response->body());
            throw new \Exception('Fyers API error getting option LTP: ' . $response->body());
            
        } catch (\Exception $e) {
            $this->logError('Fyers option LTP exception: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Convert timeframe to Fyers resolution format
     */
    private function convertTimeframe(string $timeframe): string
    {
        return match($timeframe) {
            '1m', '1' => '1',
            '5m', '5' => '5',
            '15m', '15' => '15',
            '30m', '30' => '30',
            '1H', '60' => '60',
            '1D', 'D' => 'D',
            '1W', 'W' => 'W',
            '1M', 'M' => 'M',
            default => '15'
        };
    }
    
    /**
     * Convert symbol to Fyers format
     */
    private function convertSymbol(string $symbol): string
    {
        // Already in correct format if contains NSE:
        if (str_contains($symbol, 'NSE:')) {
            return $symbol;
        }
        
        return "NSE:{$symbol}";
    }
    
    /**
     * Calculate days needed for candle limit
     */
    private function calculateDays(string $timeframe, int $limit): int
    {
        // Double the buffer to ensure we get enough historical data
        $multiplier = 2;
        
        return match($timeframe) {
            '1m', '1' => ceil($limit / (60 * 6.5)) * $multiplier,
            '5m', '5' => ceil($limit / (12 * 6.5)) * $multiplier,
            '15m', '15' => ceil($limit / (4 * 6.5)) * $multiplier,
            '30m', '30' => ceil($limit / (2 * 6.5)) * $multiplier,
            '1H', '60' => ceil($limit / 6.5) * $multiplier,
            '1D', 'D' => $limit * $multiplier,
            default => ceil($limit / (4 * 6.5)) * $multiplier
        };
    }
    
    /**
     * Format Fyers candles to standard format
     */
    private function formatCandles(array $candles): array
    {
        $formatted = [];
        
        foreach ($candles as $candle) {
            if (count($candle) >= 5) {
                $timestamp = $candle[0];
                $formatted[] = [
                    'timestamp' => $timestamp,
                    'datetime' => Carbon::createFromTimestamp($timestamp)->format('Y-m-d H:i:s'),
                    'open' => (float) $candle[1],
                    'high' => (float) $candle[2],
                    'low' => (float) $candle[3],
                    'close' => (float) $candle[4],
                    'volume' => isset($candle[5]) ? (int) $candle[5] : 0,
                ];
            }
        }
        
        return $formatted;
    }
    
    /**
     * Simulate option LTP when API unavailable
     */
    private function simulateOptionLTP(string $symbol): float
    {
        // Extract strike and type from symbol
        // Example: NSE:BANKNIFTY26JUN50000CE
        preg_match('/(\d{5,6})(CE|PE)/', $symbol, $matches);
        
        if (count($matches) >= 3) {
            $strike = (float) $matches[1];
            $type = $matches[2];
            $spot = $this->getBankNiftySpotPrice();
            
            return FyersSimulator::getOptionPremium($spot, $strike, $type, 5);
        }
        
        return 50.0; // Default fallback
    }
    
    /**
     * Validate candle data
     */
    protected function validateCandles(array $candles): bool
    {
        if (empty($candles)) {
            return false;
        }
        
        foreach ($candles as $candle) {
            if (!isset($candle['open'], $candle['high'], $candle['low'], $candle['close'])) {
                return false;
            }
            
            // High should be highest
            if ($candle['high'] < $candle['open'] || $candle['high'] < $candle['close']) {
                return false;
            }
            
            // Low should be lowest
            if ($candle['low'] > $candle['open'] || $candle['low'] > $candle['close']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get cached candles from database
     */
    private function getCachedCandles(string $symbol, string $timeframe, int $limit): array
    {
        // Determine cache TTL based on timeframe
        $cacheTTL = $this->getCacheTTL($timeframe);
        $cutoffTime = Carbon::now()->subMinutes($cacheTTL);
        
        // Get cached candles
        $cached = CandleCache::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->where('candle_timestamp', '>=', $cutoffTime)
            ->orderBy('candle_timestamp', 'desc')
            ->limit($limit)
            ->get();
        
        // Need at least 90% of requested candles for valid cache
        if ($cached->count() < ($limit * 0.9)) {
            return [];
        }
        
        // Convert to array format
        return $cached->map(function ($candle) {
            return [
                'timestamp' => $candle->candle_timestamp->timestamp,
                'datetime' => $candle->candle_timestamp->format('Y-m-d H:i:s'),
                'open' => (float) $candle->open,
                'high' => (float) $candle->high,
                'low' => (float) $candle->low,
                'close' => (float) $candle->close,
                'volume' => (int) $candle->volume,
            ];
        })->toArray();
    }
    
    /**
     * Store candles in cache
     */
    private function cacheCandles(string $symbol, string $timeframe, array $candles): void
    {
        try {
            foreach ($candles as $candle) {
                CandleCache::updateOrCreate(
                    [
                        'symbol' => $symbol,
                        'timeframe' => $timeframe,
                        'candle_timestamp' => Carbon::createFromTimestamp($candle['timestamp']),
                    ],
                    [
                        'open' => $candle['open'],
                        'high' => $candle['high'],
                        'low' => $candle['low'],
                        'close' => $candle['close'],
                        'volume' => $candle['volume'] ?? 0,
                    ]
                );
            }
            
            $this->logInfo('Cached candles', [
                'symbol' => $symbol,
                'timeframe' => $timeframe,
                'count' => count($candles),
            ]);
            
            // Clean old cache entries (older than 7 days)
            $this->cleanOldCache($symbol, $timeframe);
            
        } catch (\Exception $e) {
            $this->logError('Failed to cache candles: ' . $e->getMessage());
        }
    }
    
    /**
     * Get cache TTL (time-to-live) in minutes based on timeframe
     */
    private function getCacheTTL(string $timeframe): int
    {
        return match($timeframe) {
            '1m', '1' => 1,      // 1 minute for 1m candles
            '5m', '5' => 5,      // 5 minutes for 5m candles
            '15m', '15' => 15,   // 15 minutes for 15m candles
            '30m', '30' => 30,   // 30 minutes for 30m candles
            '1H', '60' => 60,    // 1 hour for hourly candles
            '1D', 'D' => 1440,   // 24 hours for daily candles
            '1W', 'W' => 10080,  // 7 days for weekly candles
            '1M', 'M' => 43200,  // 30 days for monthly candles
            default => 15
        };
    }
    
    /**
     * Clean old cached candles (older than 7 days)
     */
    private function cleanOldCache(string $symbol, string $timeframe): void
    {
        try {
            // Keep candles for 1 month (configurable via settings)
            $retentionDays = setting('candle_cache_retention_days', 30);
            $cutoff = Carbon::now()->subDays($retentionDays);
            
            $deleted = CandleCache::where('symbol', $symbol)
                ->where('timeframe', $timeframe)
                ->where('candle_timestamp', '<', $cutoff)
                ->delete();
            
            if ($deleted > 0) {
                $this->logInfo('Cleaned old cache', [
                    'symbol' => $symbol,
                    'timeframe' => $timeframe,
                    'deleted_count' => $deleted,
                    'older_than_days' => $retentionDays,
                ]);
            }
                
        } catch (\Exception $e) {
            $this->logError('Failed to clean old cache: ' . $e->getMessage());
        }
    }
}

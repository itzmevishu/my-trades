<?php

namespace App\Services\Fyers;

use Illuminate\Support\Facades\Redis;
use App\Services\BaseService;

/**
 * Fyers Rate Limiter Service
 * 
 * Implements SEBI's 10 orders/second rate limit requirement
 * Effective April 1, 2026
 * 
 * Usage:
 *   $rateLimiter = new RateLimiter();
 *   $rateLimiter->waitForSlot(); // Blocks until slot available
 *   // Then place your order
 */
class RateLimiter extends BaseService
{
    /**
     * SEBI mandated limit: 10 orders per second
     */
    const MAX_ORDERS_PER_SECOND = 10;
    
    /**
     * Redis key prefix for rate limiting
     */
    const REDIS_KEY_PREFIX = 'fyers:orders:';
    
    /**
     * Check if we're within rate limit
     */
    public function checkLimit(): bool
    {
        $key = $this->getCurrentSecondKey();
        $count = Redis::incr($key);
        
        // Set expiry on first increment
        if ($count === 1) {
            Redis::expire($key, 2); // 2 second TTL for safety margin
        }
        
        $withinLimit = $count <= self::MAX_ORDERS_PER_SECOND;
        
        if (!$withinLimit) {
            $this->logWarning("Rate limit hit: {$count} orders in current second");
        }
        
        return $withinLimit;
    }
    
    /**
     * Wait for an available slot (blocking)
     */
    public function waitForSlot(): void
    {
        $attempts = 0;
        $maxAttempts = 50; // Max 5 seconds wait
        
        while (!$this->checkLimit()) {
            if ($attempts >= $maxAttempts) {
                $this->logError('Rate limiter timeout after 5 seconds');
                throw new \Exception('Rate limiter timeout - too many orders queued');
            }
            
            usleep(100000); // Wait 100ms
            $attempts++;
        }
        
        if ($attempts > 0) {
            $this->logInfo("Waited {$attempts}00ms for rate limit slot");
        }
    }
    
    /**
     * Get current order count for this second
     */
    public function getCurrentCount(): int
    {
        $key = $this->getCurrentSecondKey();
        return (int) Redis::get($key) ?: 0;
    }
    
    /**
     * Reset rate limiter (for testing only)
     */
    public function reset(): void
    {
        $key = $this->getCurrentSecondKey();
        Redis::del($key);
        $this->logInfo('Rate limiter reset');
    }
    
    /**
     * Get Redis key for current second
     */
    protected function getCurrentSecondKey(): string
    {
        return self::REDIS_KEY_PREFIX . now()->format('Y-m-d-H-i-s');
    }
}

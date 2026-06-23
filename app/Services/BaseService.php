<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Base Service Class
 * 
 * Provides common functionality for all services including
 * logging, error handling, and retry logic.
 */
abstract class BaseService
{
    /**
     * Log an info message
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info("[{$this->getServiceName()}] {$message}", $context);
    }

    /**
     * Log a warning
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning("[{$this->getServiceName()}] {$message}", $context);
    }

    /**
     * Log an error
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error("[{$this->getServiceName()}] {$message}", $context);
    }

    /**
     * Get the service name for logging
     */
    protected function getServiceName(): string
    {
        return class_basename($this);
    }

    /**
     * Retry a callback with exponential backoff
     */
    protected function retry(callable $callback, int $maxAttempts = 3, int $delaySeconds = 2)
    {
        $attempt = 1;

        while ($attempt <= $maxAttempts) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $this->logWarning("Attempt {$attempt}/{$maxAttempts} failed: {$e->getMessage()}");

                if ($attempt === $maxAttempts) {
                    throw $e;
                }

                sleep($delaySeconds * $attempt); // Exponential backoff
                $attempt++;
            }
        }
    }
}

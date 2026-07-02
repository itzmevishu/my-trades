<?php

namespace App\Services\Analysis\PriceAction\Analyzers;

use App\Services\Analysis\PriceAction\Support\AnalyzerResult;
use App\Services\Analysis\PriceAction\Support\MarketContext;

/**
 * AbstractAnalyzer - Base contract for every independent analyzer.
 *
 * Each analyzer:
 *  - Receives the shared, pre-computed MarketContext
 *  - Produces observations (behavioural events)
 *  - Assigns a directional bias + strength (confidence)
 *  - Explains its reasoning in plain English
 *  - Operates independently and is trivially testable
 */
abstract class AbstractAnalyzer
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Run the analyzer against the shared market context.
     */
    abstract public function analyze(MarketContext $ctx): AnalyzerResult;

    /**
     * A short machine key identifying this analyzer (e.g. "trend").
     */
    abstract public function key(): string;

    /** Read a config value with a default. */
    protected function cfg(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /** Convenience factory for a neutral/empty result. */
    protected function neutral(string $reason = ''): AnalyzerResult
    {
        $result = new AnalyzerResult(AnalyzerResult::NEUTRAL, 0.0);
        if ($reason !== '') {
            $result->addReasoning($reason);
        }

        return $result;
    }
}

<?php

namespace App\Services\Analysis;

use App\Services\BaseService;
use App\Services\Analysis\PriceAction\Analyzers\CandlestickConfirmationAnalyzer;
use App\Services\Analysis\PriceAction\Analyzers\EMAAnalyzer;
use App\Services\Analysis\PriceAction\Analyzers\MarketStructureAnalyzer;
use App\Services\Analysis\PriceAction\Analyzers\MomentumAnalyzer;
use App\Services\Analysis\PriceAction\Analyzers\PriceActionEventAnalyzer;
use App\Services\Analysis\PriceAction\Analyzers\SupportResistanceAnalyzer;
use App\Services\Analysis\PriceAction\Analyzers\SwingAnalyzer;
use App\Services\Analysis\PriceAction\Analyzers\TrendAnalyzer;
use App\Services\Analysis\PriceAction\Analyzers\VolatilityAnalyzer;
use App\Services\Analysis\PriceAction\SignalAggregator;
use App\Services\Analysis\PriceAction\Support\MarketContext;

/**
 * PriceActionAnalyzer - The market-behaviour engine.
 *
 * This replaces the old candlestick-pattern detector entirely. Rather than
 * classifying candle shapes, it reasons about the *story of the market* the
 * way an experienced discretionary price-action trader does:
 *
 *   1. Establish the trend (20 / 100 / 2000 EMAs, slope, spacing).
 *   2. Read market structure (swings, BOS, CHoCH, pullback vs reversal).
 *   3. Judge location (support/resistance, dynamic EMAs, who is defending).
 *   4. Weigh momentum, volatility and behavioural events.
 *   5. Treat candlestick patterns as light confirmation only.
 *   6. Combine everything through confluence into an explainable assessment.
 *
 * Output is structured JSON suitable for downstream AI scoring, with a
 * human-readable `reasoning` narrative explaining *why* the signal exists.
 *
 * The engine is adaptive: thresholds are derived from ATR, rolling averages,
 * EMA spacing and relative volume rather than hardcoded point values, and all
 * key parameters are configurable via settings.
 */
class PriceActionAnalyzer extends BaseService
{
    /** @var array<string, \App\Services\Analysis\PriceAction\Analyzers\AbstractAnalyzer> */
    private array $analyzers;

    private SignalAggregator $aggregator;

    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $this->resolveConfig($config);

        $this->analyzers = [
            'trend' => new TrendAnalyzer($this->config),
            'ema' => new EMAAnalyzer($this->config),
            'swings' => new SwingAnalyzer($this->config),
            'market_structure' => new MarketStructureAnalyzer($this->config),
            'support_resistance' => new SupportResistanceAnalyzer($this->config),
            'momentum' => new MomentumAnalyzer($this->config),
            'volatility' => new VolatilityAnalyzer($this->config),
            'price_action' => new PriceActionEventAnalyzer($this->config),
            'candlestick_confirmation' => new CandlestickConfirmationAnalyzer($this->config),
        ];

        $this->aggregator = new SignalAggregator($this->config);
    }

    /**
     * Analyze a series of candles and return the full structured assessment.
     *
     * @param array $candles Candles (oldest first), each with open/high/low/close[/volume].
     * @return array Structured assessment (see class docblock / PRD example).
     */
    public function analyze(array $candles): array
    {
        $minCandles = (int) ($this->config['min_candles'] ?? 30);

        if (count($candles) < $minCandles) {
            $this->logWarning('Insufficient candles for price-action analysis', [
                'provided' => count($candles),
                'required' => $minCandles,
            ]);

            return $this->insufficientDataResult(count($candles), $minCandles);
        }

        $ctx = new MarketContext($candles, $this->config);

        // Run every analyzer independently against the shared context.
        $results = [];
        foreach ($this->analyzers as $key => $analyzer) {
            $results[$key] = $analyzer->analyze($ctx);
        }

        $assessment = $this->aggregator->aggregate($results, $ctx);

        // Attach per-analyzer detail for transparency / debugging.
        $assessment['analyzers'] = [];
        foreach ($results as $key => $res) {
            $assessment['analyzers'][$key] = $res->toArray();
        }

        $assessment['meta'] = [
            'candles_analyzed' => $ctx->count,
            'last_price' => $ctx->lastPrice,
            'atr' => round($ctx->atr, 2),
            'ema_fast' => $ctx->emaFast,
            'ema_mid' => $ctx->emaMid,
            'ema_slow' => $ctx->emaSlow,
            'ema_slow_period_used' => $ctx->emaSlowPeriod,
            'relative_volume' => $ctx->relativeVolume !== null ? round($ctx->relativeVolume, 2) : null,
        ];

        $this->logInfo('Price action analysis complete', [
            'direction' => $assessment['direction'],
            'confidence' => $assessment['confidence'],
            'grade' => $assessment['grade'],
            'events' => $assessment['price_action'],
        ]);

        return $assessment;
    }

    /**
     * Resolve configuration, layering caller overrides on top of settings.
     */
    private function resolveConfig(array $overrides): array
    {
        $fromSettings = [
            'ema_fast' => (int) $this->setting('pa_ema_fast', 20),
            'ema_mid' => (int) $this->setting('pa_ema_mid', 100),
            'ema_slow' => (int) $this->setting('pa_ema_slow', 2000),
            'atr_period' => (int) $this->setting('pa_atr_period', 14),
            'swing_lookback' => (int) $this->setting('pa_swing_lookback', 2),
            'min_candles' => (int) $this->setting('pa_min_candles', 30),
        ];

        return array_merge($fromSettings, $overrides);
    }

    /**
     * Safe settings accessor (the global helper may be unavailable in tests).
     */
    private function setting(string $key, $default)
    {
        if (function_exists('setting')) {
            try {
                return setting($key, $default);
            } catch (\Throwable $e) {
                return $default;
            }
        }

        return $default;
    }

    private function insufficientDataResult(int $provided, int $required): array
    {
        return [
            'direction' => 'neutral',
            'confidence' => 0,
            'grade' => 'D',
            'recommendation' => 'Skip — insufficient data for analysis.',
            'bias_scores' => ['bullish' => 0, 'bearish' => 0, 'net' => 0],
            'frame_agreement' => 'no_clear_frame',
            'trend' => ['direction' => 'neutral', 'strength' => 'unknown', 'state' => 'undefined'],
            'market_structure' => ['state' => 'undefined', 'bos' => false, 'choch' => false],
            'price_action' => [],
            'support_resistance' => ['support' => null, 'resistance' => null, 'reaction' => 'none'],
            'candlestick_confirmation' => [],
            'momentum' => ['direction' => 'neutral'],
            'volatility' => 'unknown',
            'reasoning' => "Not enough candles to analyze (have {$provided}, need {$required}).",
            'analyzers' => [],
            'meta' => ['candles_analyzed' => $provided],
        ];
    }
}

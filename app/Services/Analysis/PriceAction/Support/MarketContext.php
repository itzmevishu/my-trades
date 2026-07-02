<?php

namespace App\Services\Analysis\PriceAction\Support;

/**
 * MarketContext - Shared, pre-computed market state.
 *
 * The engine avoids re-deriving the same numbers inside every analyzer.
 * MarketContext computes the adaptive building blocks once (EMAs, ATR,
 * rolling ranges, relative volume, swing points) and exposes them to all
 * analyzers so every observation is interpreted against the *same* picture.
 *
 * Everything here is adaptive: thresholds are expressed relative to ATR,
 * EMA spacing and rolling averages rather than hardcoded point values, so
 * the engine self-adjusts to changing volatility and instruments.
 */
class MarketContext
{
    /** @var array<int,array> Normalised candles (oldest first). */
    public array $candles;

    public int $count;

    /** Latest candle (most recent). */
    public array $current;

    /** Previous candle. */
    public ?array $previous;

    public float $lastPrice;

    /** @var array<int,float> Full EMA series aligned to the last N candles. */
    public array $emaFastSeries = [];
    public array $emaMidSeries = [];
    public array $emaSlowSeries = [];

    public ?float $emaFast = null;   // e.g. 20
    public ?float $emaMid = null;    // e.g. 100
    public ?float $emaSlow = null;   // e.g. 2000 (or adaptive fallback)

    public int $emaFastPeriod = 20;
    public int $emaMidPeriod = 100;
    public int $emaSlowPeriod = 200;
    public int $emaSlowRequested = 2000;

    /** Slopes normalised as pct-of-price change per candle. */
    public float $emaFastSlope = 0.0;
    public float $emaMidSlope = 0.0;
    public float $emaSlowSlope = 0.0;

    /** Average True Range over $atrPeriod candles. */
    public float $atr = 0.0;
    public int $atrPeriod = 14;

    /** Rolling average candle body / range, used to gauge conviction. */
    public float $avgBody = 0.0;
    public float $avgRange = 0.0;

    /** Rolling average volume + latest relative volume (latest / average). */
    public ?float $avgVolume = null;
    public ?float $relativeVolume = null;
    public bool $hasVolume = false;

    /** @var array<int,array> Detected swing highs: ['index','price']. */
    public array $swingHighs = [];
    /** @var array<int,array> Detected swing lows: ['index','price']. */
    public array $swingLows = [];

    public function __construct(array $candles, array $config = [])
    {
        $this->candles = array_values($candles);
        $this->count = count($this->candles);

        $this->emaFastPeriod = (int) ($config['ema_fast'] ?? 20);
        $this->emaMidPeriod = (int) ($config['ema_mid'] ?? 100);
        $this->emaSlowRequested = (int) ($config['ema_slow'] ?? 2000);
        $this->atrPeriod = (int) ($config['atr_period'] ?? 14);

        $this->current = $this->candles[$this->count - 1];
        $this->previous = $this->count >= 2 ? $this->candles[$this->count - 2] : null;
        $this->lastPrice = (float) $this->current['close'];

        $this->computeEmas();
        $this->computeAtr();
        $this->computeRollingAverages();
        $this->computeVolume();
        $this->computeSwings((int) ($config['swing_lookback'] ?? 2));
    }

    // ---------------------------------------------------------------------
    // Adaptive helpers
    // ---------------------------------------------------------------------

    /** Body size of a candle. */
    public static function body(array $c): float
    {
        return abs((float) $c['close'] - (float) $c['open']);
    }

    public static function range(array $c): float
    {
        return (float) $c['high'] - (float) $c['low'];
    }

    public static function upperWick(array $c): float
    {
        return (float) $c['high'] - max((float) $c['open'], (float) $c['close']);
    }

    public static function lowerWick(array $c): float
    {
        return min((float) $c['open'], (float) $c['close']) - (float) $c['low'];
    }

    public static function isBull(array $c): bool
    {
        return (float) $c['close'] > (float) $c['open'];
    }

    public static function isBear(array $c): bool
    {
        return (float) $c['close'] < (float) $c['open'];
    }

    /** Express a price distance as a multiple of ATR (adaptive units). */
    public function inAtr(float $distance): float
    {
        return $this->atr > 0 ? $distance / $this->atr : 0.0;
    }

    /** How tightly the EMAs are stacked, in ATR units (compression gauge). */
    public function emaSpacingAtr(): float
    {
        if ($this->emaFast === null || $this->emaSlow === null) {
            return 0.0;
        }

        return $this->inAtr(abs($this->emaFast - $this->emaSlow));
    }

    // ---------------------------------------------------------------------
    // Computation
    // ---------------------------------------------------------------------

    private function computeEmas(): void
    {
        $closes = array_map(static fn ($c) => (float) $c['close'], $this->candles);

        // Slow EMA adapts: if we lack enough candles for the requested (2000)
        // period, fall back to the largest sensible period we can support so
        // the engine still produces a long-term trend reference.
        $this->emaSlowPeriod = $this->resolveSlowPeriod();

        $this->emaFastSeries = $this->emaSeries($closes, $this->emaFastPeriod);
        $this->emaMidSeries = $this->emaSeries($closes, $this->emaMidPeriod);
        $this->emaSlowSeries = $this->emaSeries($closes, $this->emaSlowPeriod);

        $this->emaFast = $this->lastOf($this->emaFastSeries);
        $this->emaMid = $this->lastOf($this->emaMidSeries);
        $this->emaSlow = $this->lastOf($this->emaSlowSeries);

        $this->emaFastSlope = $this->normalisedSlope($this->emaFastSeries);
        $this->emaMidSlope = $this->normalisedSlope($this->emaMidSeries);
        $this->emaSlowSlope = $this->normalisedSlope($this->emaSlowSeries);
    }

    private function resolveSlowPeriod(): int
    {
        if ($this->count >= $this->emaSlowRequested) {
            return $this->emaSlowRequested;
        }

        // Use as much history as we have while staying above the mid EMA so
        // the "slow" reference remains meaningfully slower than the mid one.
        $candidate = (int) floor($this->count * 0.8);

        return max($this->emaMidPeriod + 20, min($this->emaSlowRequested, $candidate));
    }

    /** @return array<int,float> */
    private function emaSeries(array $closes, int $period): array
    {
        $n = count($closes);
        if ($n < $period || $period < 1) {
            return [];
        }

        $multiplier = 2 / ($period + 1);
        $ema = array_sum(array_slice($closes, 0, $period)) / $period;
        $series = [$ema];

        for ($i = $period; $i < $n; $i++) {
            $ema = (($closes[$i] - $ema) * $multiplier) + $ema;
            $series[] = $ema;
        }

        return $series;
    }

    private function lastOf(array $series): ?float
    {
        return empty($series) ? null : round(end($series), 2);
    }

    /**
     * Slope normalised as average pct-of-price change per candle over the
     * recent window. Positive = rising, negative = falling. Adaptive because
     * it is scale-free (works on any instrument / price level).
     */
    private function normalisedSlope(array $series, int $lookback = 5): float
    {
        $len = count($series);
        if ($len < 2) {
            return 0.0;
        }

        $lookback = min($lookback, $len - 1);
        $recent = $series[$len - 1];
        $past = $series[$len - 1 - $lookback];

        if ($past == 0.0) {
            return 0.0;
        }

        return (($recent - $past) / $past) / $lookback * 100;
    }

    private function computeAtr(): void
    {
        if ($this->count < 2) {
            $this->atr = self::range($this->current);

            return;
        }

        $period = min($this->atrPeriod, $this->count - 1);
        $trs = [];

        for ($i = $this->count - $period; $i < $this->count; $i++) {
            $c = $this->candles[$i];
            $prevClose = (float) $this->candles[$i - 1]['close'];
            $tr = max(
                (float) $c['high'] - (float) $c['low'],
                abs((float) $c['high'] - $prevClose),
                abs((float) $c['low'] - $prevClose)
            );
            $trs[] = $tr;
        }

        $this->atr = empty($trs) ? self::range($this->current) : array_sum($trs) / count($trs);
    }

    private function computeRollingAverages(int $window = 20): void
    {
        $window = min($window, $this->count);
        $slice = array_slice($this->candles, -$window);

        $bodies = array_map(static fn ($c) => self::body($c), $slice);
        $ranges = array_map(static fn ($c) => self::range($c), $slice);

        $this->avgBody = $bodies ? array_sum($bodies) / count($bodies) : 0.0;
        $this->avgRange = $ranges ? array_sum($ranges) / count($ranges) : 0.0;
    }

    private function computeVolume(int $window = 20): void
    {
        $volumes = array_filter(
            array_map(static fn ($c) => $c['volume'] ?? null, $this->candles),
            static fn ($v) => $v !== null
        );

        // Require real, non-zero volume to consider it usable.
        $nonZero = array_filter($volumes, static fn ($v) => $v > 0);
        if (count($nonZero) < 5) {
            $this->hasVolume = false;

            return;
        }

        $this->hasVolume = true;
        $recent = array_slice(array_values($volumes), -$window);
        $this->avgVolume = array_sum($recent) / count($recent);

        $latest = (float) ($this->current['volume'] ?? 0);
        $this->relativeVolume = $this->avgVolume > 0 ? $latest / $this->avgVolume : null;
    }

    /**
     * Detect swing highs/lows using a fractal window: a swing high is a candle
     * whose high is greater than the $left candles before and $right after it.
     */
    private function computeSwings(int $wing = 2): void
    {
        $wing = max(1, $wing);
        $highs = [];
        $lows = [];

        for ($i = $wing; $i < $this->count - $wing; $i++) {
            $isHigh = true;
            $isLow = true;
            $h = (float) $this->candles[$i]['high'];
            $l = (float) $this->candles[$i]['low'];

            for ($j = $i - $wing; $j <= $i + $wing; $j++) {
                if ($j === $i) {
                    continue;
                }
                if ((float) $this->candles[$j]['high'] >= $h) {
                    $isHigh = false;
                }
                if ((float) $this->candles[$j]['low'] <= $l) {
                    $isLow = false;
                }
            }

            if ($isHigh) {
                $highs[] = ['index' => $i, 'price' => $h];
            }
            if ($isLow) {
                $lows[] = ['index' => $i, 'price' => $l];
            }
        }

        $this->swingHighs = $highs;
        $this->swingLows = $lows;
    }

    /** Most recent N swing highs (newest last). */
    public function recentSwingHighs(int $n = 3): array
    {
        return array_slice($this->swingHighs, -$n);
    }

    public function recentSwingLows(int $n = 3): array
    {
        return array_slice($this->swingLows, -$n);
    }

    public function lastSwingHigh(): ?array
    {
        return empty($this->swingHighs) ? null : end($this->swingHighs);
    }

    public function lastSwingLow(): ?array
    {
        return empty($this->swingLows) ? null : end($this->swingLows);
    }
}

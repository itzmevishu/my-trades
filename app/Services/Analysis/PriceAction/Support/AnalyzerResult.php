<?php

namespace App\Services\Analysis\PriceAction\Support;

/**
 * AnalyzerResult - Standard output contract for every analyzer.
 *
 * Each independent analyzer produces:
 *  - A directional bias (bullish / bearish / neutral)
 *  - A strength (0.0 - 1.0) describing how strongly it supports that bias
 *  - A list of behavioural observations (events, not candle names)
 *  - Human-readable reasoning sentences explaining "why"
 *  - A structured data payload for downstream JSON / AI scoring
 *
 * The SignalAggregator consumes these results and combines them via
 * confluence into the final trading assessment.
 */
class AnalyzerResult
{
    public const BULLISH = 'bullish';
    public const BEARISH = 'bearish';
    public const NEUTRAL = 'neutral';

    /** @var string One of BULLISH|BEARISH|NEUTRAL */
    public string $direction = self::NEUTRAL;

    /** @var float 0.0 - 1.0 conviction that supports $direction */
    public float $strength = 0.0;

    /** @var string[] Behavioural observations / events */
    public array $observations = [];

    /** @var string[] Human-readable reasoning sentences */
    public array $reasoning = [];

    /** @var array Structured data payload (analyzer specific) */
    public array $data = [];

    public function __construct(
        string $direction = self::NEUTRAL,
        float $strength = 0.0,
        array $observations = [],
        array $reasoning = [],
        array $data = []
    ) {
        $this->direction = $direction;
        $this->strength = max(0.0, min(1.0, $strength));
        $this->observations = $observations;
        $this->reasoning = $reasoning;
        $this->data = $data;
    }

    public function addObservation(string $event): self
    {
        if ($event !== '' && !in_array($event, $this->observations, true)) {
            $this->observations[] = $event;
        }

        return $this;
    }

    public function addReasoning(string $sentence): self
    {
        if ($sentence !== '') {
            $this->reasoning[] = $sentence;
        }

        return $this;
    }

    public function isBullish(): bool
    {
        return $this->direction === self::BULLISH;
    }

    public function isBearish(): bool
    {
        return $this->direction === self::BEARISH;
    }

    public function toArray(): array
    {
        return [
            'direction' => $this->direction,
            'strength' => round($this->strength, 3),
            'observations' => $this->observations,
            'reasoning' => $this->reasoning,
            'data' => $this->data,
        ];
    }
}

<?php

namespace App\Services\Analysis\PriceAction\Analyzers;

use App\Services\Analysis\PriceAction\Support\AnalyzerResult;
use App\Services\Analysis\PriceAction\Support\MarketContext;

/**
 * TrendAnalyzer - Answers "who controls the market and how strongly?".
 *
 * It fuses EMA alignment, EMA slope, EMA spacing (fan width) and swing
 * structure into a single trend classification:
 *   - trending_strong
 *   - trending_weak
 *   - transitioning
 *   - consolidating
 *   - ranging
 *
 * Trend is established BEFORE any entry logic, exactly how a discretionary
 * trader frames the market first and only then looks for a location.
 */
class TrendAnalyzer extends AbstractAnalyzer
{
    private EMAAnalyzer $ema;
    private SwingAnalyzer $swing;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->ema = new EMAAnalyzer($config);
        $this->swing = new SwingAnalyzer($config);
    }

    public function key(): string
    {
        return 'trend';
    }

    public function analyze(MarketContext $ctx): AnalyzerResult
    {
        $ema = $this->ema->analyze($ctx);
        $swing = $this->swing->analyze($ctx);

        $result = new AnalyzerResult();

        // Agreement between EMA read and swing read is the core signal.
        $emaDir = $ema->direction;
        $swingDir = $swing->direction;

        $spacingAtr = $ctx->emaSpacingAtr();
        $wideFan = $spacingAtr >= (float) $this->cfg('trend_fan_atr', 3.0);
        $tightFan = $spacingAtr <= (float) $this->cfg('range_fan_atr', 1.0);

        $slopeAlive = abs($ctx->emaFastSlope) >= (float) $this->cfg('trend_slope_min', 0.02);

        $direction = AnalyzerResult::NEUTRAL;
        $state = 'ranging';
        $strength = 0.2;

        if ($emaDir !== AnalyzerResult::NEUTRAL && $emaDir === $swingDir) {
            // EMA and structure agree -> a genuine trend.
            $direction = $emaDir;
            if ($wideFan && $slopeAlive && $ema->strength >= 0.7) {
                $state = 'trending_strong';
                $strength = 0.9;
            } else {
                $state = 'trending_weak';
                $strength = 0.6;
            }
        } elseif ($emaDir !== AnalyzerResult::NEUTRAL && $swingDir === AnalyzerResult::NEUTRAL) {
            // EMA leans but structure has not confirmed -> forming / transitioning.
            $direction = $emaDir;
            $state = 'transitioning';
            $strength = 0.45;
        } elseif ($emaDir !== AnalyzerResult::NEUTRAL && $swingDir !== AnalyzerResult::NEUTRAL && $emaDir !== $swingDir) {
            // EMA and structure disagree -> character may be changing.
            $direction = AnalyzerResult::NEUTRAL;
            $state = 'transitioning';
            $strength = 0.35;
        } elseif ($tightFan) {
            $direction = AnalyzerResult::NEUTRAL;
            $state = 'consolidating';
            $strength = 0.2;
        } else {
            $direction = AnalyzerResult::NEUTRAL;
            $state = 'ranging';
            $strength = 0.2;
        }

        $result->direction = $direction;
        $result->strength = $strength;
        $result->data = [
            'state' => $state,
            'ema_direction' => $emaDir,
            'swing_direction' => $swingDir,
            'ema_spacing_atr' => round($spacingAtr, 2),
            'fast_slope' => round($ctx->emaFastSlope, 4),
            'strength_label' => $this->strengthLabel($state),
        ];

        // Merge child observations so downstream sees the full behaviour set.
        foreach ([$ema, $swing] as $child) {
            foreach ($child->observations as $obs) {
                $result->addObservation($obs);
            }
        }

        $result->addObservation('trend_' . $state);
        $result->addReasoning($this->describe($state, $direction, $ctx));

        return $result;
    }

    private function strengthLabel(string $state): string
    {
        return match ($state) {
            'trending_strong' => 'strong',
            'trending_weak' => 'weak',
            'transitioning' => 'transitioning',
            'consolidating' => 'flat',
            default => 'flat',
        };
    }

    private function describe(string $state, string $direction, MarketContext $ctx): string
    {
        $dirWord = $direction === AnalyzerResult::BULLISH ? 'bullish' : ($direction === AnalyzerResult::BEARISH ? 'bearish' : 'directionless');

        return match ($state) {
            'trending_strong' => "The market is in a strong {$dirWord} trend — EMAs are widely fanned in one direction, sloping firmly, and swing structure agrees.",
            'trending_weak' => "The market is in a weak {$dirWord} trend — direction is clear but momentum and fan width are modest, so pullbacks may be deep.",
            'transitioning' => 'The market is transitioning — the EMA lean and the swing structure do not fully agree, so control is changing hands.',
            'consolidating' => 'The market is consolidating — EMAs are tightly compressed with little slope, signalling energy building for an expansion move.',
            default => 'The market is ranging — no side is in control and price is rotating without trend.',
        };
    }
}

<?php

namespace App\Services\Analysis;

use App\Services\BaseService;

/**
 * PatternDetector - Detect 15-minute candle patterns
 * 
 * Detects high-probability reversal and continuation patterns:
 * - Bullish Engulfing (bullish reversal)
 * - Bearish Engulfing (bearish reversal)
 * - Bullish Pinbar/Hammer (bullish reversal)
 * - Bearish Pinbar/Shooting Star (bearish reversal)
 * - Inside Bar Breakout (continuation)
 * 
 * As per PRD: All patterns detected on 15-minute timeframe
 */
class PatternDetector extends BaseService
{
    /**
     * Store rejection reasons for debugging
     */
    protected array $rejectionReasons = [];

    /**
     * Detect pattern from recent candles
     * 
     * @param array $candles Array of at least 2-3 candles (most recent last)
     * @return string|null Pattern name or null if no pattern
     */
    public function detectPattern(array $candles): ?string
    {
        // Reset rejection reasons
        $this->rejectionReasons = [];

        if (count($candles) < 2) {
            $this->logWarning('Need at least 2 candles for pattern detection', [
                'provided' => count($candles)
            ]);
            $this->rejectionReasons[] = "Insufficient candles: " . count($candles) . " (need 2+)";
            return null;
        }

        $this->logInfo('Detecting candle pattern', [
            'candles' => count($candles)
        ]);

        // Check patterns in order of priority
        if ($this->isBullishEngulfing($candles)) {
            return 'bullish_engulfing';
        }

        if ($this->isBearishEngulfing($candles)) {
            return 'bearish_engulfing';
        }

        if ($this->isBullishPinbar($candles)) {
            return 'bullish_pinbar';
        }

        if ($this->isBearishPinbar($candles)) {
            return 'bearish_pinbar';
        }

        if ($this->isInsideBarBreakout($candles)) {
            return 'inside_bar_breakout';
        }

        return null;
    }

    /**
     * Get rejection reasons from last detection attempt
     */
    public function getRejectionReasons(): array
    {
        return $this->rejectionReasons;
    }

    /**
     * Get formatted rejection summary
     */
    public function getRejectionSummary(): string
    {
        if (empty($this->rejectionReasons)) {
            return "No patterns detected (all criteria checks failed)";
        }
        
        return "Pattern checks failed:\n" . implode("\n", $this->rejectionReasons);
    }

    /**
     * Check for bullish engulfing pattern
     * 
     * Criteria:
     * - Previous candle is bearish (red)
     * - Current candle is bullish (green)
     * - Current candle's body completely engulfs previous candle's body
     * 
     * @param array $candles Recent candles (at least 2)
     * @return bool True if bullish engulfing detected
     */
    protected function isBullishEngulfing(array $candles): bool
    {
        $current = end($candles);
        $previous = $candles[count($candles) - 2];

        $reasons = [];

        // Previous must be bearish
        if ($previous['close'] >= $previous['open']) {
            $reasons[] = "Bullish Engulfing: Previous candle not bearish (C:" . round($previous['close'], 2) . " >= O:" . round($previous['open'], 2) . ")";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        // Current must be bullish
        if ($current['close'] <= $current['open']) {
            $reasons[] = "Bullish Engulfing: Current candle not bullish (C:" . round($current['close'], 2) . " <= O:" . round($current['open'], 2) . ")";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        // Current body must engulf previous body
        $currentBodyTop = max($current['open'], $current['close']);
        $currentBodyBottom = min($current['open'], $current['close']);
        $previousBodyTop = max($previous['open'], $previous['close']);
        $previousBodyBottom = min($previous['open'], $previous['close']);

        $engulfs = $currentBodyTop > $previousBodyTop && 
                   $currentBodyBottom < $previousBodyBottom;

        if (!$engulfs) {
            $reasons[] = "Bullish Engulfing: Current body doesn't engulf previous (Cur:" . round($currentBodyBottom, 2) . "-" . round($currentBodyTop, 2) . " vs Prev:" . round($previousBodyBottom, 2) . "-" . round($previousBodyTop, 2) . ")";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        if ($engulfs) {
            $this->logInfo('Bullish engulfing detected', [
                'previous' => "O:{$previous['open']} C:{$previous['close']}",
                'current' => "O:{$current['open']} C:{$current['close']}"
            ]);
        }

        return $engulfs;
    }

    /**
     * Check for bearish engulfing pattern
     * 
     * Criteria:
     * - Previous candle is bullish (green)
     * - Current candle is bearish (red)
     * - Current candle's body completely engulfs previous candle's body
     * 
     * @param array $candles Recent candles (at least 2)
     * @return bool True if bearish engulfing detected
     */
    protected function isBearishEngulfing(array $candles): bool
    {
        $current = end($candles);
        $previous = $candles[count($candles) - 2];

        $reasons = [];

        // Previous must be bullish
        if ($previous['close'] <= $previous['open']) {
            $reasons[] = "Bearish Engulfing: Previous candle not bullish (C:" . round($previous['close'], 2) . " <= O:" . round($previous['open'], 2) . ")";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        // Current must be bearish
        if ($current['close'] >= $current['open']) {
            $reasons[] = "Bearish Engulfing: Current candle not bearish (C:" . round($current['close'], 2) . " >= O:" . round($current['open'], 2) . ")";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        // Current body must engulf previous body
        $currentBodyTop = max($current['open'], $current['close']);
        $currentBodyBottom = min($current['open'], $current['close']);
        $previousBodyTop = max($previous['open'], $previous['close']);
        $previousBodyBottom = min($previous['open'], $previous['close']);

        $engulfs = $currentBodyTop > $previousBodyTop && 
                   $currentBodyBottom < $previousBodyBottom;

        if (!$engulfs) {
            $reasons[] = "Bearish Engulfing: Current body doesn't engulf previous (Cur:" . round($currentBodyBottom, 2) . "-" . round($currentBodyTop, 2) . " vs Prev:" . round($previousBodyBottom, 2) . "-" . round($previousBodyTop, 2) . ")";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        if ($engulfs) {
            $this->logInfo('Bearish engulfing detected', [
                'previous' => "O:{$previous['open']} C:{$previous['close']}",
                'current' => "O:{$current['open']} C:{$current['close']}"
            ]);
        }

        return $engulfs;
    }

    /**
     * Check for bullish pinbar (hammer)
     * 
     * Criteria:
     * - Long lower wick (at least 2x the body size)
     * - Small upper wick (less than 0.5x body)
     * - Bullish close (close higher than open)
     * - Body in upper 1/3 of candle range
     * 
     * @param array $candles Recent candles
     * @return bool True if bullish pinbar detected
     */
    protected function isBullishPinbar(array $candles): bool
    {
        $current = end($candles);

        $body = abs($current['close'] - $current['open']);
        $upperWick = $current['high'] - max($current['open'], $current['close']);
        $lowerWick = min($current['open'], $current['close']) - $current['low'];
        $totalRange = $current['high'] - $current['low'];

        $reasons = [];

        // Must be bullish
        if ($current['close'] <= $current['open']) {
            $reasons[] = "Bullish Pinbar: Not bullish candle (C:" . round($current['close'], 2) . " <= O:" . round($current['open'], 2) . ")";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        // Body must not be too small
        $bodyPct = ($body / $totalRange) * 100;
        if ($body < $totalRange * 0.1) {
            $reasons[] = "Bullish Pinbar: Body too small (" . round($bodyPct, 1) . "% of range, need >10%)";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        // Long lower wick (at least 2x body)
        $lowerWickRatio = $body > 0 ? $lowerWick / $body : 0;
        if ($lowerWick < $body * 2) {
            $reasons[] = "Bullish Pinbar: Lower wick too short (" . round($lowerWickRatio, 2) . "x body, need 2x)";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        // Small upper wick
        $upperWickRatio = $body > 0 ? $upperWick / $body : 0;
        if ($upperWick > $body * 0.5) {
            $reasons[] = "Bullish Pinbar: Upper wick too long (" . round($upperWickRatio, 2) . "x body, need <0.5x)";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        // Body should be in upper portion
        $bodyPosition = (max($current['open'], $current['close']) - $current['low']) / $totalRange;
        if ($bodyPosition < 0.66) {
            $reasons[] = "Bullish Pinbar: Body not in upper range (" . round($bodyPosition * 100, 1) . "%, need >66%)";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        $this->logInfo('Bullish pinbar detected', [
            'body' => round($body, 2),
            'lower_wick' => round($lowerWick, 2),
            'upper_wick' => round($upperWick, 2),
            'ratio' => round($lowerWick / $body, 2)
        ]);

        return true;
    }

    /**
     * Check for bearish pinbar (shooting star)
     * 
     * Criteria:
     * - Long upper wick (at least 2x the body size)
     * - Small lower wick (less than 0.5x body)
     * - Bearish close (close lower than open)
     * - Body in lower 1/3 of candle range
     * 
     * @param array $candles Recent candles
     * @return bool True if bearish pinbar detected
     */
    protected function isBearishPinbar(array $candles): bool
    {
        $current = end($candles);

        $body = abs($current['close'] - $current['open']);
        $upperWick = $current['high'] - max($current['open'], $current['close']);
        $lowerWick = min($current['open'], $current['close']) - $current['low'];
        $totalRange = $current['high'] - $current['low'];

        $reasons = [];

        // Must be bearish
        if ($current['close'] >= $current['open']) {
            $reasons[] = "Bearish Pinbar: Not bearish candle (C:" . round($current['close'], 2) . " >= O:" . round($current['open'], 2) . ")";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        // Body must not be too small
        $bodyPct = ($body / $totalRange) * 100;
        if ($body < $totalRange * 0.1) {
            $reasons[] = "Bearish Pinbar: Body too small (" . round($bodyPct, 1) . "% of range, need >10%)";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        // Long upper wick (at least 2x body)
        $upperWickRatio = $body > 0 ? $upperWick / $body : 0;
        if ($upperWick < $body * 2) {
            $reasons[] = "Bearish Pinbar: Upper wick too short (" . round($upperWickRatio, 2) . "x body, need 2x)";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        // Small lower wick
        $lowerWickRatio = $body > 0 ? $lowerWick / $body : 0;
        if ($lowerWick > $body * 0.5) {
            $reasons[] = "Bearish Pinbar: Lower wick too long (" . round($lowerWickRatio, 2) . "x body, need <0.5x)";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        // Body should be in lower portion
        $bodyPosition = (max($current['open'], $current['close']) - $current['low']) / $totalRange;
        if ($bodyPosition > 0.33) {
            $reasons[] = "Bearish Pinbar: Body not in lower range (" . round($bodyPosition * 100, 1) . "%, need <33%)";
            $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
            return false;
        }

        $this->logInfo('Bearish pinbar detected', [
            'body' => round($body, 2),
            'upper_wick' => round($upperWick, 2),
            'lower_wick' => round($lowerWick, 2),
            'ratio' => round($upperWick / $body, 2)
        ]);

        return true;
    }

    /**
     * Check for inside bar breakout
     * 
     * Criteria:
     * - Previous candle (mother bar) has a defined range
     * - Current candle breaks above or below mother bar's range
     * - Breakout is significant (at least 20 points)
     * 
     * @param array $candles Recent candles (at least 2)
     * @return bool True if inside bar breakout detected
     */
    protected function isInsideBarBreakout(array $candles): bool
    {
        $current = end($candles);
        $motherBar = $candles[count($candles) - 2];

        $motherHigh = $motherBar['high'];
        $motherLow = $motherBar['low'];
        $currentClose = $current['close'];

        $breakoutThreshold = 20; // Minimum 20 points for significant breakout

        $reasons = [];

        // Bullish breakout (close above mother bar high)
        if ($currentClose > $motherHigh && ($currentClose - $motherHigh) >= $breakoutThreshold) {
            $this->logInfo('Inside bar breakout (bullish)', [
                'mother_high' => $motherHigh,
                'current_close' => $currentClose,
                'breakout_points' => round($currentClose - $motherHigh, 2)
            ]);
            return true;
        }

        // Bearish breakout (close below mother bar low)
        if ($currentClose < $motherLow && ($motherLow - $currentClose) >= $breakoutThreshold) {
            $this->logInfo('Inside bar breakout (bearish)', [
                'mother_low' => $motherLow,
                'current_close' => $currentClose,
                'breakout_points' => round($motherLow - $currentClose, 2)
            ]);
            return true;
        }

        // Check if it's close but didn't meet threshold
        $bullishBreakoutPoints = $currentClose > $motherHigh ? $currentClose - $motherHigh : 0;
        $bearishBreakoutPoints = $currentClose < $motherLow ? $motherLow - $currentClose : 0;

        if ($bullishBreakoutPoints > 0 && $bullishBreakoutPoints < $breakoutThreshold) {
            $reasons[] = "Inside Bar Breakout: Bullish breakout too small (" . round($bullishBreakoutPoints, 2) . " pts, need " . $breakoutThreshold . "+)";
        } elseif ($bearishBreakoutPoints > 0 && $bearishBreakoutPoints < $breakoutThreshold) {
            $reasons[] = "Inside Bar Breakout: Bearish breakout too small (" . round($bearishBreakoutPoints, 2) . " pts, need " . $breakoutThreshold . "+)";
        } else {
            $reasons[] = "Inside Bar Breakout: No breakout (Close:" . round($currentClose, 2) . " within Mother:" . round($motherLow, 2) . "-" . round($motherHigh, 2) . ")";
        }

        $this->rejectionReasons = array_merge($this->rejectionReasons, $reasons);
        return false;
    }

    /**
     * Get pattern details with additional context
     * 
     * Returns pattern name plus additional details useful for analysis
     * 
     * @param array $candles Recent candles
     * @return array|null ['pattern' => string, 'strength' => string, 'details' => array]
     */
    public function detectPatternWithDetails(array $candles): ?array
    {
        $pattern = $this->detectPattern($candles);

        if (!$pattern) {
            return null;
        }

        $current = end($candles);
        $previous = count($candles) > 1 ? $candles[count($candles) - 2] : null;

        $details = [
            'pattern' => $pattern,
            'direction' => $this->getPatternDirection($pattern),
            'strength' => $this->assessPatternStrength($pattern, $candles),
            'candle' => [
                'open' => $current['open'],
                'high' => $current['high'],
                'low' => $current['low'],
                'close' => $current['close'],
                'range' => $current['high'] - $current['low'],
            ]
        ];

        return $details;
    }

    /**
     * Get pattern direction (bullish/bearish)
     * 
     * @param string $pattern Pattern name
     * @return string 'bullish' or 'bearish'
     */
    protected function getPatternDirection(string $pattern): string
    {
        return match($pattern) {
            'bullish_engulfing', 'bullish_pinbar' => 'bullish',
            'bearish_engulfing', 'bearish_pinbar' => 'bearish',
            'inside_bar_breakout' => 'neutral', // Depends on breakout direction
            default => 'unknown'
        };
    }

    /**
     * Assess pattern strength (strong/moderate/weak)
     * 
     * @param string $pattern Pattern name
     * @param array $candles Candles
     * @return string Strength rating
     */
    protected function assessPatternStrength(string $pattern, array $candles): string
    {
        $current = end($candles);
        $range = $current['high'] - $current['low'];
        $volume = $current['volume'] ?? 0;

        // Strong pattern criteria:
        // - Large range (> 200 points for Bank Nifty)
        // - High volume (> 2M)
        if ($range > 200 && $volume > 2000000) {
            return 'strong';
        }

        // Moderate pattern
        if ($range > 100 && $volume > 1500000) {
            return 'moderate';
        }

        // Weak pattern
        return 'weak';
    }

    /**
     * Validate pattern quality
     * 
     * Checks if pattern meets minimum quality standards
     * 
     * @param string $pattern Pattern name
     * @param array $candles Candles
     * @return array ['valid' => bool, 'reason' => string]
     */
    public function validatePattern(string $pattern, array $candles): array
    {
        $current = end($candles);
        $range = $current['high'] - $current['low'];

        // Minimum range check (50 points for Bank Nifty)
        if ($range < 50) {
            return [
                'valid' => false,
                'reason' => 'Candle range too small (< 50 points)'
            ];
        }

        // Maximum range check (avoid extreme volatility candles)
        if ($range > 500) {
            return [
                'valid' => false,
                'reason' => 'Candle range too large (> 500 points) - extreme volatility'
            ];
        }

        return [
            'valid' => true,
            'reason' => 'Pattern meets quality standards'
        ];
    }

    /**
     * Get all patterns from candles (for testing/analysis)
     * 
     * @param array $candles Candles
     * @return array Array of detected patterns with details
     */
    public function getAllPatterns(array $candles): array
    {
        $patterns = [];

        if ($this->isBullishEngulfing($candles)) {
            $patterns[] = ['pattern' => 'bullish_engulfing', 'direction' => 'bullish'];
        }

        if ($this->isBearishEngulfing($candles)) {
            $patterns[] = ['pattern' => 'bearish_engulfing', 'direction' => 'bearish'];
        }

        if ($this->isBullishPinbar($candles)) {
            $patterns[] = ['pattern' => 'bullish_pinbar', 'direction' => 'bullish'];
        }

        if ($this->isBearishPinbar($candles)) {
            $patterns[] = ['pattern' => 'bearish_pinbar', 'direction' => 'bearish'];
        }

        if ($this->isInsideBarBreakout($candles)) {
            $patterns[] = ['pattern' => 'inside_bar_breakout', 'direction' => 'neutral'];
        }

        return $patterns;
    }
}

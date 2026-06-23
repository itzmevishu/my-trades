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
     * Detect pattern from recent candles
     * 
     * @param array $candles Array of at least 2-3 candles (most recent last)
     * @return string|null Pattern name or null if no pattern
     */
    public function detectPattern(array $candles): ?string
    {
        if (count($candles) < 2) {
            $this->logWarning('Need at least 2 candles for pattern detection', [
                'provided' => count($candles)
            ]);
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

        // Previous must be bearish
        if ($previous['close'] >= $previous['open']) {
            return false;
        }

        // Current must be bullish
        if ($current['close'] <= $current['open']) {
            return false;
        }

        // Current body must engulf previous body
        $currentBodyTop = max($current['open'], $current['close']);
        $currentBodyBottom = min($current['open'], $current['close']);
        $previousBodyTop = max($previous['open'], $previous['close']);
        $previousBodyBottom = min($previous['open'], $previous['close']);

        $engulfs = $currentBodyTop > $previousBodyTop && 
                   $currentBodyBottom < $previousBodyBottom;

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

        // Previous must be bullish
        if ($previous['close'] <= $previous['open']) {
            return false;
        }

        // Current must be bearish
        if ($current['close'] >= $current['open']) {
            return false;
        }

        // Current body must engulf previous body
        $currentBodyTop = max($current['open'], $current['close']);
        $currentBodyBottom = min($current['open'], $current['close']);
        $previousBodyTop = max($previous['open'], $previous['close']);
        $previousBodyBottom = min($previous['open'], $previous['close']);

        $engulfs = $currentBodyTop > $previousBodyTop && 
                   $currentBodyBottom < $previousBodyBottom;

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

        // Must be bullish
        if ($current['close'] <= $current['open']) {
            return false;
        }

        // Body must not be too small
        if ($body < $totalRange * 0.1) {
            return false;
        }

        // Long lower wick (at least 2x body)
        if ($lowerWick < $body * 2) {
            return false;
        }

        // Small upper wick
        if ($upperWick > $body * 0.5) {
            return false;
        }

        // Body should be in upper portion
        $bodyPosition = (max($current['open'], $current['close']) - $current['low']) / $totalRange;
        if ($bodyPosition < 0.66) {
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

        // Must be bearish
        if ($current['close'] >= $current['open']) {
            return false;
        }

        // Body must not be too small
        if ($body < $totalRange * 0.1) {
            return false;
        }

        // Long upper wick (at least 2x body)
        if ($upperWick < $body * 2) {
            return false;
        }

        // Small lower wick
        if ($lowerWick > $body * 0.5) {
            return false;
        }

        // Body should be in lower portion
        $bodyPosition = (max($current['open'], $current['close']) - $current['low']) / $totalRange;
        if ($bodyPosition > 0.33) {
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

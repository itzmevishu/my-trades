<?php

namespace App\Services\Analysis;

use App\Services\BaseService;

/**
 * Advanced Pattern Scorer - Sophisticated pattern analysis with weighted scoring
 * 
 * Instead of binary pattern detection, this scores setups based on:
 * - Trend alignment (most important)
 * - Price action quality (flexible criteria)
 * - EMA confluence and pullbacks
 * - Market structure (higher highs/lows)
 * - Support/Resistance context
 * 
 * Score Ranges:
 * - 90-100: A+ setup (excellent probability)
 * - 80-89:  A setup (high probability)
 * - 70-79:  B setup (good probability)
 * - 60-69:  Watchlist (marginal)
 * - <60:    Skip
 */
class AdvancedPatternScorer extends BaseService
{
    private EMACalculator $emaCalculator;
    private array $scoreBreakdown = [];

    public function __construct()
    {
        $this->emaCalculator = new EMACalculator();
    }

    /**
     * Score a trading setup comprehensively
     * 
     * @param array $candles Recent candles (minimum 200 for EMAs)
     * @return array ['score' => int, 'grade' => string, 'breakdown' => array, 'recommendation' => string]
     */
    public function scoreSetup(array $candles): array
    {
        $this->scoreBreakdown = [];
        $totalScore = 0;

        // Step 1: Trend Filter (Most Important) - Max 35 points
        $trendScore = $this->scoreTrend($candles);
        $totalScore += $trendScore;

        // Step 2: Price Action Quality - Max 25 points
        $priceActionScore = $this->scorePriceAction($candles);
        $totalScore += $priceActionScore;

        // Step 3: EMA Confluence - Max 20 points
        $emaConfluenceScore = $this->scoreEMAConfluence($candles);
        $totalScore += $emaConfluenceScore;

        // Step 4: Market Structure - Max 15 points
        $structureScore = $this->scoreMarketStructure($candles);
        $totalScore += $structureScore;

        // Step 5: Volume Confirmation - Max 5 points
        $volumeScore = $this->scoreVolume($candles);
        $totalScore += $volumeScore;

        // Determine grade and recommendation
        $grade = $this->getGrade($totalScore);
        $recommendation = $this->getRecommendation($totalScore);

        return [
            'score' => $totalScore,
            'grade' => $grade,
            'breakdown' => $this->scoreBreakdown,
            'recommendation' => $recommendation,
            'direction' => $this->getSetupDirection($candles),
        ];
    }

    /**
     * Step 1: Score Trend Alignment (Most Important)
     * 
     * Strong Bullish: Price > 20 EMA > 100 EMA > 200 EMA + Rising 20 EMA
     * Strong Bearish: Price < 20 EMA < 100 EMA < 200 EMA + Falling 20 EMA
     */
    private function scoreTrend(array $candles): int
    {
        $score = 0;
        $emas = $this->emaCalculator->calculateMultipleEMAs($candles);
        $current = end($candles);
        $price = $current['close'];

        $ema20 = $emas['ema_20'];
        $ema100 = $emas['ema_100'];
        $ema200 = $emas['ema_200'];

        // Calculate 20 EMA slope
        $ema20Slope = $this->calculateEMASlope($candles, 20, 5);

        // Check for bullish trend
        if ($price > $ema20 && $ema20 > $ema100 && $ema100 > $ema200) {
            $score += 25;
            $this->scoreBreakdown['trend_alignment'] = '+25 (Strong Bullish Alignment)';
            
            if ($ema20Slope > 0) {
                $score += 10;
                $this->scoreBreakdown['ema20_slope'] = '+10 (Rising 20 EMA)';
            }
        }
        // Check for bearish trend
        elseif ($price < $ema20 && $ema20 < $ema100 && $ema100 < $ema200) {
            $score += 25;
            $this->scoreBreakdown['trend_alignment'] = '+25 (Strong Bearish Alignment)';
            
            if ($ema20Slope < 0) {
                $score += 10;
                $this->scoreBreakdown['ema20_slope'] = '+10 (Falling 20 EMA)';
            }
        }
        // Partial alignment
        elseif ($price > $ema20 && $ema20 > $ema100) {
            $score += 15;
            $this->scoreBreakdown['trend_alignment'] = '+15 (Partial Bullish Alignment)';
        }
        elseif ($price < $ema20 && $ema20 < $ema100) {
            $score += 15;
            $this->scoreBreakdown['trend_alignment'] = '+15 (Partial Bearish Alignment)';
        }
        else {
            $this->scoreBreakdown['trend_alignment'] = '+0 (No Clear Trend - Major Penalty)';
        }

        return $score;
    }

    /**
     * Step 2: Score Price Action Quality (Flexible Pattern Detection)
     */
    private function scorePriceAction(array $candles): int
    {
        $score = 0;
        $current = end($candles);
        $previous = $candles[count($candles) - 2];

        $body = abs($current['close'] - $current['open']);
        $upperWick = $current['high'] - max($current['open'], $current['close']);
        $lowerWick = min($current['open'], $current['close']) - $current['low'];
        $totalRange = $current['high'] - $current['low'];

        if ($totalRange == 0) return 0;

        $bodyPct = ($body / $totalRange) * 100;
        $bodyTop = max($current['open'], $current['close']);
        $bodyBottom = min($current['open'], $current['close']);
        $bodyPosition = ($bodyTop - $current['low']) / $totalRange;

        // Bullish Pinbar (Flexible)
        // Lower wick >= 1.5x body, Upper wick <= body, Body in top 60%, Body >= 8%
        if ($lowerWick >= $body * 1.5 && 
            $upperWick <= $body && 
            $bodyPosition >= 0.60 && 
            $bodyPct >= 8) {
            $score += 20;
            $this->scoreBreakdown['price_action'] = '+20 (Strong Bullish Rejection)';
        }
        // Bearish Pinbar (Flexible)
        // Upper wick >= 1.5x body, Lower wick <= body, Body in bottom 40%, Body >= 8%
        elseif ($upperWick >= $body * 1.5 && 
                $lowerWick <= $body && 
                $bodyPosition <= 0.40 && 
                $bodyPct >= 8) {
            $score += 20;
            $this->scoreBreakdown['price_action'] = '+20 (Strong Bearish Rejection)';
        }
        // Bullish Engulfing (Flexible)
        // Previous bearish, Current bullish, Current body >= previous body
        elseif ($previous['close'] < $previous['open'] && 
                $current['close'] > $current['open'] && 
                $body >= abs($previous['close'] - $previous['open']) &&
                $current['close'] > $previous['open']) {
            $score += 18;
            $this->scoreBreakdown['price_action'] = '+18 (Bullish Engulfing)';
        }
        // Bearish Engulfing (Flexible)
        elseif ($previous['close'] > $previous['open'] && 
                $current['close'] < $current['open'] && 
                $body >= abs($previous['close'] - $previous['open']) &&
                $current['close'] < $previous['open']) {
            $score += 18;
            $this->scoreBreakdown['price_action'] = '+18 (Bearish Engulfing)';
        }
        // Strong Bullish Close
        elseif ($current['close'] > $current['open'] && $bodyPct >= 60) {
            $score += 12;
            $this->scoreBreakdown['price_action'] = '+12 (Strong Bullish Candle)';
        }
        // Strong Bearish Close
        elseif ($current['close'] < $current['open'] && $bodyPct >= 60) {
            $score += 12;
            $this->scoreBreakdown['price_action'] = '+12 (Strong Bearish Candle)';
        }
        // Moderate candle
        elseif ($bodyPct >= 30) {
            $score += 5;
            $this->scoreBreakdown['price_action'] = '+5 (Moderate Price Action)';
        }
        else {
            $this->scoreBreakdown['price_action'] = '+0 (Weak/Indecisive Candle)';
        }

        return $score;
    }

    /**
     * Step 3: Score EMA Confluence and Pullbacks
     */
    private function scoreEMAConfluence(array $candles): int
    {
        $score = 0;
        $emas = $this->emaCalculator->calculateMultipleEMAs($candles);
        $current = end($candles);
        $price = $current['close'];

        $ema20 = $emas['ema_20'];
        $ema100 = $emas['ema_100'];
        $ema200 = $emas['ema_200'];

        $distFrom20 = abs($price - $ema20) / $ema20 * 100;
        $distFrom100 = abs($price - $ema100) / $ema100 * 100;
        $distFrom200 = abs($price - $ema200) / $ema200 * 100;

        // Highest Probability: Pullback to 100 EMA (best risk/reward)
        if ($distFrom100 <= 0.5) {
            $score += 20;
            $this->scoreBreakdown['ema_confluence'] = '+20 (At 100 EMA - Excellent Entry)';
        }
        // Strong: Pullback between 20 EMA and 100 EMA
        elseif ($distFrom20 <= 0.5) {
            $score += 15;
            $this->scoreBreakdown['ema_confluence'] = '+15 (At 20 EMA - Good Pullback)';
        }
        // Moderate: Close to 20 EMA
        elseif ($distFrom20 <= 1.5) {
            $score += 10;
            $this->scoreBreakdown['ema_confluence'] = '+10 (Near 20 EMA)';
        }
        // At 200 EMA (strong support/resistance)
        elseif ($distFrom200 <= 0.5) {
            $score += 15;
            $this->scoreBreakdown['ema_confluence'] = '+15 (At 200 EMA - Major Level)';
        }
        // Too far from EMAs
        else {
            $this->scoreBreakdown['ema_confluence'] = '+0 (Away from EMAs - Wait for Pullback)';
        }

        return $score;
    }

    /**
     * Step 4: Score Market Structure (Higher Highs/Lows or Lower Highs/Lows)
     */
    private function scoreMarketStructure(array $candles): int
    {
        $score = 0;
        
        // Need at least 10 candles to assess structure
        if (count($candles) < 10) {
            $this->scoreBreakdown['market_structure'] = '+0 (Insufficient Data)';
            return 0;
        }

        $recent = array_slice($candles, -10);
        $structure = $this->analyzeStructure($recent);

        if ($structure['type'] === 'higher_highs_lows') {
            $score += 15;
            $this->scoreBreakdown['market_structure'] = '+15 (Bullish Structure - HH/HL)';
        } elseif ($structure['type'] === 'lower_highs_lows') {
            $score += 15;
            $this->scoreBreakdown['market_structure'] = '+15 (Bearish Structure - LH/LL)';
        } elseif ($structure['type'] === 'consolidation') {
            $score += 5;
            $this->scoreBreakdown['market_structure'] = '+5 (Consolidation - Range Bound)';
        } else {
            $this->scoreBreakdown['market_structure'] = '+0 (Choppy/Unclear Structure)';
        }

        return $score;
    }

    /**
     * Step 5: Score Volume Confirmation
     */
    private function scoreVolume(array $candles): int
    {
        $score = 0;
        
        if (count($candles) < 2) {
            $this->scoreBreakdown['volume'] = '+0 (No Volume Data)';
            return 0;
        }

        $current = end($candles);
        $previous = $candles[count($candles) - 2];

        if (!isset($current['volume']) || !isset($previous['volume'])) {
            $this->scoreBreakdown['volume'] = '+0 (No Volume Data)';
            return 0;
        }

        // Strong volume increase
        if ($current['volume'] > $previous['volume'] * 1.5) {
            $score += 5;
            $this->scoreBreakdown['volume'] = '+5 (Strong Volume Increase)';
        }
        // Moderate volume
        elseif ($current['volume'] > $previous['volume']) {
            $score += 3;
            $this->scoreBreakdown['volume'] = '+3 (Rising Volume)';
        }
        else {
            $this->scoreBreakdown['volume'] = '+0 (Weak Volume)';
        }

        return $score;
    }

    /**
     * Calculate EMA slope
     */
    private function calculateEMASlope(array $candles, int $period, int $lookback = 5): float
    {
        if (count($candles) < $period + $lookback) {
            return 0;
        }

        $emaValues = [];
        $recent = array_slice($candles, -($period + $lookback));
        
        for ($i = $lookback; $i >= 1; $i--) {
            $subset = array_slice($recent, 0, count($recent) - $i + 1);
            $ema = $this->emaCalculator->calculateEMA($subset, $period);
            $emaValues[] = $ema;
        }

        if (count($emaValues) < 2) return 0;

        // Simple slope: difference between first and last
        return end($emaValues) - $emaValues[0];
    }

    /**
     * Analyze market structure
     */
    private function analyzeStructure(array $candles): array
    {
        $highs = array_map(fn($c) => $c['high'], $candles);
        $lows = array_map(fn($c) => $c['low'], $candles);

        $recentHigh = max(array_slice($highs, -5));
        $priorHigh = max(array_slice($highs, 0, 5));
        $recentLow = min(array_slice($lows, -5));
        $priorLow = min(array_slice($lows, 0, 5));

        // Higher highs and higher lows
        if ($recentHigh > $priorHigh && $recentLow > $priorLow) {
            return ['type' => 'higher_highs_lows', 'strength' => 'strong'];
        }

        // Lower highs and lower lows
        if ($recentHigh < $priorHigh && $recentLow < $priorLow) {
            return ['type' => 'lower_highs_lows', 'strength' => 'strong'];
        }

        // Consolidation
        $range = max($highs) - min($lows);
        $avgRange = $range / count($candles);
        if ($avgRange < 100) { // Bank Nifty specific
            return ['type' => 'consolidation', 'strength' => 'moderate'];
        }

        return ['type' => 'choppy', 'strength' => 'weak'];
    }

    /**
     * Get grade based on score
     */
    private function getGrade(int $score): string
    {
        return match(true) {
            $score >= 90 => 'A+',
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            default => 'D'
        };
    }

    /**
     * Get recommendation based on score
     */
    private function getRecommendation(int $score): string
    {
        return match(true) {
            $score >= 90 => 'Excellent Setup - Strong Entry Signal',
            $score >= 80 => 'High Probability Setup - Take Trade',
            $score >= 70 => 'Good Setup - Consider Entry',
            $score >= 60 => 'Marginal Setup - Watchlist Only',
            default => 'Skip - Low Probability'
        };
    }

    /**
     * Determine setup direction
     */
    private function getSetupDirection(array $candles): string
    {
        $emas = $this->emaCalculator->calculateMultipleEMAs($candles);
        $current = end($candles);
        $price = $current['close'];

        if ($price > $emas['ema_20'] && $emas['ema_20'] > $emas['ema_100']) {
            return 'bullish';
        } elseif ($price < $emas['ema_20'] && $emas['ema_20'] < $emas['ema_100']) {
            return 'bearish';
        }

        return 'neutral';
    }

    /**
     * Get detailed breakdown text
     */
    public function getScoreBreakdownText(): string
    {
        $text = "Score Breakdown:\n";
        foreach ($this->scoreBreakdown as $category => $detail) {
            $text .= "  • " . ucwords(str_replace('_', ' ', $category)) . ": " . $detail . "\n";
        }
        return $text;
    }
}

<?php

namespace App\Services\Trading;

use App\Services\BaseService;

/**
 * RiskEngine - Calculate position sizing and risk parameters
 * 
 * Implements 1% risk per trade rule with proper position sizing.
 * 
 * Key Features:
 * - Position sizing based on capital and risk percentage
 * - SL level detection from swing high/low
 * - Premium-based SL calculation (entry premium + delta)
 * - Lot size validation (max 2 lots)
 * - Risk/reward ratio calculation
 * 
 * As per PRD:
 * - Risk 1% of capital per trade
 * - Use 5-candle lookback for swing detection
 * - Assume SL delta of 0.5 points (50 paise) for premium calculation
 * - Max 2 lots to limit position size
 */
class RiskEngine extends BaseService
{
    /**
     * Calculate complete risk parameters for a trade
     * 
     * @param float $capital Total trading capital
     * @param float $entryPremium Current option premium (entry point)
     * @param float $slPremium Stop loss premium level
     * @param float $riskPercentage Risk per trade (default 1%)
     * @return array Complete risk parameters
     */
    public function calculateRisk(
        float $capital,
        float $entryPremium,
        float $slPremium,
        float $riskPercentage = 1.0
    ): array {
        $this->logInfo('Calculating risk parameters', [
            'capital' => $capital,
            'entry_premium' => $entryPremium,
            'sl_premium' => $slPremium,
            'risk_pct' => $riskPercentage
        ]);

        // Risk amount in INR
        $riskAmount = $capital * ($riskPercentage / 100);

        // SL distance in premium points
        $slDistance = abs($slPremium - $entryPremium);

        if ($slDistance == 0) {
            $this->logError('SL distance cannot be zero');
            throw new \Exception('Invalid SL distance: cannot be zero');
        }

        // Risk per lot = SL distance × lot size
        $lotSize = setting('banknifty_lot_size', 15); // Bank Nifty lot size from settings
        $riskPerLot = $slDistance * $lotSize;

        // Calculate number of lots (rounded down)
        $lots = floor($riskAmount / $riskPerLot);

        // Apply max lots limit from settings
        $maxLots = setting('max_lots', 2);
        $lots = min($lots, $maxLots);

        // Ensure at least 1 lot
        $lots = max(1, $lots);

        // Total risk with calculated lots
        $totalRisk = $lots * $riskPerLot;

        // Calculate target premium based on configured R:R
        $targetRR = setting('target_rr', 2.0);
        $targetPremium = $entryPremium - ($slDistance * $targetRR);
        $minTarget = setting('minimum_target_premium', 5);
        $targetPremium = max($minTarget, $targetPremium); // Enforce minimum target

        // Potential profit
        $potentialProfit = $lots * $lotSize * ($entryPremium - $targetPremium);

        // Actual risk percentage used
        $actualRiskPct = ($totalRisk / $capital) * 100;

        $result = [
            'lots' => $lots,
            'lot_size' => $lotSize,
            'quantity' => $lots * $lotSize,
            'entry_premium' => round($entryPremium, 2),
            'sl_premium' => round($slPremium, 2),
            'target_premium' => round($targetPremium, 2),
            'sl_distance' => round($slDistance, 2),
            'risk_per_lot' => round($riskPerLot, 2),
            'total_risk' => round($totalRisk, 2),
            'potential_profit' => round($potentialProfit, 2),
            'risk_reward_ratio' => $targetRR,
            'risk_percentage' => round($actualRiskPct, 2),
        ];

        $this->logInfo('Risk calculation complete', $result);

        return $result;
    }

    /**
     * Calculate SL level from swing high/low
     * 
     * As per PRD: Use 5-candle lookback to find swing point
     * 
     * @param array $candles Recent 15-minute candles (at least 5)
     * @param string $direction 'CALL' or 'PUT'
     * @return array ['sl_level' => float, 'swing_candle_index' => int]
     */
    public function calculateSLLevel(array $candles, string $direction): array
    {
        $lookback = setting('swing_lookback_candles', 5);

        if (count($candles) < $lookback) {
            $this->logWarning('Insufficient candles for swing detection', [
                'provided' => count($candles),
                'required' => $lookback
            ]);
            throw new \Exception("Need at least {$lookback} candles for SL detection");
        }

        // Get recent candles for swing detection
        $recentCandles = array_slice($candles, -$lookback);

        if ($direction === 'CALL') {
            // For CALL: Find swing low (lowest low in lookback period)
            $swingLow = PHP_FLOAT_MAX;
            $swingIndex = 0;

            foreach ($recentCandles as $index => $candle) {
                if ($candle['low'] < $swingLow) {
                    $swingLow = $candle['low'];
                    $swingIndex = $index;
                }
            }

            $this->logInfo('Swing low detected for CALL', [
                'swing_low' => $swingLow,
                'candle_index' => $swingIndex
            ]);

            return [
                'sl_level' => round($swingLow, 2),
                'swing_candle_index' => $swingIndex,
                'swing_type' => 'low'
            ];
        } else {
            // For PUT: Find swing high (highest high in lookback period)
            $swingHigh = 0;
            $swingIndex = 0;

            foreach ($recentCandles as $index => $candle) {
                if ($candle['high'] > $swingHigh) {
                    $swingHigh = $candle['high'];
                    $swingIndex = $index;
                }
            }

            $this->logInfo('Swing high detected for PUT', [
                'swing_high' => $swingHigh,
                'candle_index' => $swingIndex
            ]);

            return [
                'sl_level' => round($swingHigh, 2),
                'swing_candle_index' => $swingIndex,
                'swing_type' => 'high'
            ];
        }
    }

    /**
     * Calculate SL premium from entry premium
     * 
     * As per PRD: SL premium = Entry premium + delta (0.5 default)
     * 
     * @param float $entryPremium Entry premium
     * @param float $slDelta SL delta in points (default from settings)
     * @return float SL premium
     */
    public function calculateSLPremium(float $entryPremium, ?float $slDelta = null): float
    {
        $slDelta = $slDelta ?? setting('sl_delta_assumption', 0.5);
        $slPremium = $entryPremium + $slDelta;

        $this->logInfo('SL premium calculated', [
            'entry_premium' => $entryPremium,
            'sl_delta' => $slDelta,
            'sl_premium' => $slPremium
        ]);

        return round($slPremium, 2);
    }

    /**
     * Calculate target premium for given risk/reward ratio
     * 
     * @param float $entryPremium Entry premium
     * @param float $slPremium SL premium
     * @param float $rrRatio Risk/reward ratio (default 2.0)
     * @return float Target premium
     */
    public function calculateTargetPremium(
        float $entryPremium,
        float $slPremium,
        ?float $rrRatio = null
    ): float {
        $rrRatio = $rrRatio ?? setting('target_rr', 2.0);
        
        $slDistance = abs($slPremium - $entryPremium);
        $targetDistance = $slDistance * $rrRatio;
        
        $targetPremium = $entryPremium - $targetDistance;
        
        // Minimum 5 points target
        $targetPremium = max(5, $targetPremium);

        $this->logInfo('Target premium calculated', [
            'entry_premium' => $entryPremium,
            'sl_distance' => $slDistance,
            'rr_ratio' => $rrRatio,
            'target_premium' => $targetPremium
        ]);

        return round($targetPremium, 2);
    }

    /**
     * Validate position size is within limits
     * 
     * @param int $lots Number of lots
     * @param float $totalRisk Total risk amount
     * @param float $capital Trading capital
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validatePositionSize(int $lots, float $totalRisk, float $capital): array
    {
        $errors = [];

        // Check max lots limit
        $maxLots = setting('max_lots', 2);
        if ($lots > $maxLots) {
            $errors[] = "Position size exceeds max lots limit ({$maxLots})";
        }

        // Check minimum lots
        if ($lots < 1) {
            $errors[] = "Position size must be at least 1 lot";
        }

        // Check if risk exceeds capital
        if ($totalRisk > $capital) {
            $errors[] = "Total risk (₹{$totalRisk}) exceeds capital (₹{$capital})";
        }

        // Check if risk is reasonable (max 2% of capital)
        $riskPct = ($totalRisk / $capital) * 100;
        if ($riskPct > 2.0) {
            $errors[] = "Risk percentage ({$riskPct}%) exceeds 2% limit";
        }

        $valid = empty($errors);

        if ($valid) {
            $this->logInfo('Position size validated', [
                'lots' => $lots,
                'total_risk' => $totalRisk,
                'risk_pct' => round($riskPct, 2)
            ]);
        } else {
            $this->logError('Position size validation failed', [
                'errors' => $errors
            ]);
        }

        return [
            'valid' => $valid,
            'errors' => $errors
        ];
    }

    /**
     * Validate SL distance is within acceptable range
     * 
     * @param float $slDistance SL distance in premium points
     * @return array ['valid' => bool, 'reason' => string]
     */
    public function validateSLDistance(float $slDistance): array
    {
        $minSL = setting('min_sl_distance', 50);
        $maxSL = setting('max_sl_distance', 250);

        if ($slDistance < $minSL) {
            return [
                'valid' => false,
                'reason' => "SL distance ({$slDistance} pts) is too tight (min {$minSL} pts)"
            ];
        }

        if ($slDistance > $maxSL) {
            return [
                'valid' => false,
                'reason' => "SL distance ({$slDistance} pts) is too wide (max {$maxSL} pts)"
            ];
        }

        return [
            'valid' => true,
            'reason' => 'SL distance within acceptable range'
        ];
    }

    /**
     * Calculate partial exit parameters
     * 
     * As per PRD: Exit 50% at 1:1 RR
     * 
     * @param float $entryPremium Entry premium
     * @param float $slPremium SL premium
     * @param int $totalLots Total lots in trade
     * @return array Partial exit details
     */
    public function calculatePartialExit(
        float $entryPremium,
        float $slPremium,
        int $totalLots
    ): array {
        $partialExitRR = setting('partial_exit_rr', 1.0);
        
        $slDistance = abs($slPremium - $entryPremium);
        $partialExitPremium = $entryPremium - ($slDistance * $partialExitRR);
        
        // Exit 50% of position
        $partialLots = floor($totalLots / 2);
        $remainingLots = $totalLots - $partialLots;

        return [
            'partial_exit_premium' => round($partialExitPremium, 2),
            'partial_lots' => $partialLots,
            'remaining_lots' => $remainingLots,
            'rr_achieved' => $partialExitRR
        ];
    }

    /**
     * Get recommended ATM strike based on spot price
     * 
     * @param float $spotPrice Current Bank Nifty spot
     * @return int ATM strike (rounded to nearest 100)
     */
    public function getATMStrike(float $spotPrice): int
    {
        // Round to nearest 100
        return round($spotPrice / 100) * 100;
    }

    /**
     * Calculate complete trade setup with all risk parameters
     * 
     * Convenience method that combines all calculations
     * 
     * @param array $params ['capital', 'entry_premium', 'sl_premium', 'spot_price', 'direction']
     * @return array Complete trade setup
     */
    public function calculateTradeSetup(array $params): array
    {
        $capital = $params['capital'];
        $entryPremium = $params['entry_premium'];
        $slPremium = $params['sl_premium'] ?? $this->calculateSLPremium($entryPremium);
        $spotPrice = $params['spot_price'] ?? 50000;
        $direction = $params['direction'] ?? 'CALL';

        // Calculate risk parameters
        $risk = $this->calculateRisk($capital, $entryPremium, $slPremium);

        // Validate
        $sizeValidation = $this->validatePositionSize($risk['lots'], $risk['total_risk'], $capital);
        $slValidation = $this->validateSLDistance($risk['sl_distance']);

        // Calculate partial exit
        $partialExit = $this->calculatePartialExit($entryPremium, $slPremium, $risk['lots']);

        // Get ATM strike
        $atmStrike = $this->getATMStrike($spotPrice);

        return array_merge($risk, [
            'atm_strike' => $atmStrike,
            'direction' => $direction,
            'spot_price' => $spotPrice,
            'partial_exit' => $partialExit,
            'validations' => [
                'position_size' => $sizeValidation,
                'sl_distance' => $slValidation
            ]
        ]);
    }
}

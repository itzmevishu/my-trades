<?php

namespace App\Services\Trading;

use App\Services\BaseService;
use App\Services\Fyers\FyersDataService;
use App\Services\Analysis\EMACalculator;
use App\Services\Analysis\PatternDetector;
use App\Services\Analysis\AdvancedPatternScorer;
use App\Services\Claude\ClaudeAPIService;
use App\Services\Notifications\TelegramNotificationService;
use App\Models\Trade;
use App\Models\ScanLog;
use Carbon\Carbon;

/**
 * PaperTradingService - Orchestrate complete paper trading flow
 * 
 * Core Responsibilities:
 * - Detect entry signals (pattern + EMA + timing)
 * - Score setups with Claude AI
 * - Calculate risk and position size
 * - Simulate paper orders (entry/exit)
 * - Track open positions
 * - Manage exits (SL/target/partial/EOD)
 * - Record trades to database
 * 
 * Trading Rules:
 * - 1 trade per day maximum
 * - Entry window: Configurable via settings (default: 9:15 AM - 3:30 PM)
 * - EOD exit: 3:15 PM sharp
 * - Only ATM options (nearest strike)
 * - 15-minute timeframe analysis
 * 
 * Phase: Week 6 - Paper Trading
 */
class PaperTradingService extends BaseService
{
    private RiskEngine $riskEngine;
    private EMACalculator $emaCalculator;
    private PatternDetector $patternDetector;
    private AdvancedPatternScorer $advancedScorer;
    private ClaudeAPIService $claudeService;
    private FyersDataService $fyersData;

    public function __construct()
    {
        $this->riskEngine = new RiskEngine();
        $this->emaCalculator = new EMACalculator();
        $this->patternDetector = new PatternDetector();
        $this->advancedScorer = new AdvancedPatternScorer();
        $this->claudeService = new ClaudeAPIService();
        $this->fyersData = new FyersDataService();
    }

    /**
     * Scan for entry signal and execute if valid
     * 
     * This is called by scheduled job every 15 minutes during trading hours.
     * 
     * @return array|null Trade data if entry taken, null otherwise
     */
    public function scanAndExecute(): ?array
    {
        $this->logInfo('Starting entry scan');

        // Check if we can trade today
        if (!$this->canTradeToday()) {
            ScanLog::create([
                'scan_date' => now()->toDateString(),
                'scan_time' => now()->toTimeString(),
                'result' => 'outside_window',
                'rejection_reason' => 'Market holiday or weekend',
            ]);
            return null;
        }

        // Check if we're in trading window
        if (!$this->isInTradingWindow()) {
            ScanLog::create([
                'scan_date' => now()->toDateString(),
                'scan_time' => now()->toTimeString(),
                'result' => 'outside_window',
                'rejection_reason' => 'Outside trading window (9:15 AM - 3:30 PM)',
            ]);
            return null;
        }

        // Check if we already have a trade today
        if ($this->hasTradedToday()) {
            $this->logInfo('Already traded today, skipping scan');
            ScanLog::create([
                'scan_date' => now()->toDateString(),
                'scan_time' => now()->toTimeString(),
                'result' => 'already_traded',
                'rejection_reason' => '1 trade per day limit reached',
            ]);
            return null;
        }

        // Get real market data from Fyers API
        $this->logInfo('Fetching real market data from Fyers API');
        $timeframe = setting('trading_timeframe', '15');
        $lookback = setting('candle_lookback', 250);
        
        try {
            $candles = $this->fyersData->fetchCandles('NSE:NIFTYBANK-INDEX', $timeframe, $lookback);
        } catch (\Exception $e) {
            $this->logError('Failed to fetch market data: ' . $e->getMessage());
            return null;
        }
        
        if (!$candles || count($candles) < 200) {
            $this->logWarning('Insufficient candle data for analysis');
            return null;
        }

        // Use Advanced Pattern Scorer (new sophisticated system)
        $scoringResult = $this->advancedScorer->scoreSetup($candles);
        
        $this->logInfo('Setup scored', [
            'score' => $scoringResult['score'],
            'grade' => $scoringResult['grade'],
            'direction' => $scoringResult['direction'],
            'recommendation' => $scoringResult['recommendation']
        ]);

        // Minimum score threshold (configurable)
        $minScore = setting('min_setup_score', 70);
        
        if ($scoringResult['score'] < $minScore) {
            $this->logInfo('Setup score below threshold', [
                'score' => $scoringResult['score'],
                'min_required' => $minScore
            ]);
            
            ScanLog::create([
                'scan_date' => now()->toDateString(),
                'scan_time' => now()->toTimeString(),
                'result' => 'rejected_score',
                'current_price' => $candles[count($candles) - 1]['close'],
                'rejection_reason' => "Score {$scoringResult['score']}/100 (need {$minScore}+) - " . $scoringResult['recommendation'],
            ]);
            return null;
        }

        // Also run legacy pattern detector for compatibility
        $patternResult = $this->patternDetector->detectPatternWithDetails($candles);
        
        $this->logInfo('Advanced scoring passed', [
            'pattern' => $patternResult['pattern'] ?? 'none',
            'score' => $scoringResult['score'],
            'direction' => $scoringResult['direction']
        ]);

        // Calculate EMAs
        $emas = $this->emaCalculator->calculateMultipleEMAs($candles);
        $currentPrice = $candles[count($candles) - 1]['close'];
        
        // The advanced scorer already validated trend, EMA alignment, and pullbacks
        // It uses a weighted scoring system so we trust its judgment
        
        $this->logInfo('Setup meets quality standards', [
            'setup_score' => $scoringResult['score'],
            'grade' => $scoringResult['grade'],
            'direction' => $scoringResult['direction']
        ]);

        // Get HTF bias (simulate - in production would analyze daily/weekly)
        $htfBias = $this->determineHTFBias($candles);

        // Prepare enhanced setup data for Claude scoring
        // Include our advanced score to guide Claude
        $setupData = [
            'candle_pattern' => $patternResult['pattern'] ?? 'score_based',
            'advanced_score' => $scoringResult['score'],
            'advanced_grade' => $scoringResult['grade'],
            'score_breakdown' => $scoringResult['breakdown'],
            'price_vs_ema' => $currentPrice > $emas['ema_20'] ? 'above' : 'below',
            'htf_bias' => $htfBias,
            'session_slot' => $this->getCurrentSessionSlot(),
            'market_condition' => $this->determineMarketCondition($candles),
            'direction' => $scoringResult['direction']
        ];

        // Score setup with Claude (now as secondary validation)
        $claudeScore = $this->claudeService->scoreSetup($setupData);
        
        // Weighted final score: 70% advanced scorer, 30% Claude
        $finalScore = ($scoringResult['score'] * 0.7) + ($claudeScore['score'] * 3); // Scale Claude to 0-30
        
        $this->logInfo('Combined scoring', [
            'advanced_score' => $scoringResult['score'],
            'claude_score' => $claudeScore['score'],
            'final_weighted_score' => round($finalScore, 1)
        ]);

        // Check combined score (should still be high if advanced scorer approved)
        $minScore = setting('min_claude_score', 60); // Now represents combined score minimum
        
        if ($finalScore < $minScore) {
            $this->logInfo('Combined score below threshold', [
                'final_score' => round($finalScore, 1),
                'min_required' => $minScore
            ]);
            ScanLog::create([
                'scan_date' => now()->toDateString(),
                'scan_time' => now()->toTimeString(),
                'result' => 'rejected_score',
                'pattern_detected' => $patternResult['pattern'] ?? 'advanced_scoring',
                'pattern_direction' => $scoringResult['direction'],
                'current_price' => $currentPrice,
                'ema_20' => $emas['ema_20'],
                'ema_100' => $emas['ema_100'],
                'ema_200' => $emas['ema_200'],
                'ema_confluence_count' => 0,
                'claude_score' => round($finalScore, 1),
                'rejection_reason' => "Claude score below threshold ({$claudeScore['score']} < {$minScore}). Reason: {$claudeScore['reasoning']}",
            ]);
            return null;
        }

        // Calculate ATM strike
        $atmStrike = $this->riskEngine->calculateATMStrike($currentPrice);

        // Get option premium (real or simulated)
        $optionType = $patternResult['direction'] === 'bullish' ? 'CALL' : 'PUT';
        
        if ($useRealData) {
            // Build option symbol for Fyers
            $expiry = Carbon::now()->format('dMy'); // e.g., 23Jun26
            $symbol = "NSE:BANKNIFTY{$expiry}{$atmStrike}" . ($optionType === 'CALL' ? 'CE' : 'PE');
            $entryPremium = $this->fyersData->getOptionLTP($symbol);
            $this->logInfo("Real option premium from Fyers: ₹{$entryPremium}");
        } else {
            $entryPremium = FyersSimulator::getOptionPremium(
                $currentPrice,
                $atmStrike,
                $optionType === 'CALL' ? 'CE' : 'PE',
                0 // Assuming same-day expiry for simplicity
            );
            $this->logInfo("Simulated option premium: ₹{$entryPremium}");
        }

        // Calculate SL level from candles
        $slLevel = $this->riskEngine->calculateSLLevel($candles, $patternResult['direction']);
        
        // Calculate SL premium (entry premium + delta based on spot movement)
        $slPremium = $this->riskEngine->calculateSLPremium(
            $currentPrice,
            $slLevel,
            $entryPremium,
            $atmStrike,
            $optionType
        );

        // Calculate risk parameters
        $capital = setting('capital_amount', 300000);
        $riskPct = setting('risk_percentage', 1.0);
        
        $riskParams = $this->riskEngine->calculateRisk(
            $capital,
            $entryPremium,
            $slPremium,
            $riskPct
        );

        // Validate position size
        if (!$this->riskEngine->validatePositionSize($riskParams['lots'], $riskParams['total_risk'], $capital)) {
            $this->logWarning('Position size validation failed', $riskParams);
            return null;
        }

        // Simulate paper order
        $orderResult = FyersSimulator::simulatePaperOrder(
            $optionType,
            $atmStrike,
            $entryPremium,
            $riskParams['lots']
        );

        if ($orderResult['status'] !== 'success') {
            $this->logError('Paper order simulation failed', $orderResult);
            return null;
        }

        $this->logInfo('Paper order executed', [
            'filled_premium' => $orderResult['filled_premium'],
            'lots' => $riskParams['lots']
        ]);

        // Create trade record
        $trade = $this->recordTrade([
            'date' => now()->toDateString(),
            'direction' => $patternResult['direction'],
            'option_type' => $optionType,
            'strike' => $atmStrike,
            'entry_premium' => $orderResult['filled_premium'],
            'sl_premium' => $slPremium,
            'target_premium' => $riskParams['target_premium'],
            'partial_exit_premium' => $riskParams['partial_exit_premium'],
            'lots' => $riskParams['lots'],
            'quantity' => $riskParams['quantity'],
            'capital' => $capital,
            'candle_pattern' => $patternResult['pattern'],
            'ema_configuration' => json_encode([
                'ema_20' => $emas['ema_20'],
                'ema_100' => $emas['ema_100'],
                'ema_200' => $emas['ema_200'],
                'distance_from_20ema_pct' => round($distanceFrom20EMA, 2),
                'price_vs_20ema' => $currentPrice > $ema20 ? 'above' : 'below'
            ]),
            'htf_bias' => $htfBias,
            'claude_score' => $claudeScore['score'],
            'claude_reasoning' => $claudeScore['reasoning'],
            'status' => 'open',
            'entry_time' => now()->toTimeString(),
            'entry_slippage_pct' => $orderResult['slippage_pct']
        ]);

        // Log successful trade execution
        ScanLog::create([
            'scan_date' => now()->toDateString(),
            'scan_time' => now()->toTimeString(),
            'result' => 'trade_taken',
            'pattern_detected' => $patternResult['pattern'],
            'pattern_direction' => $patternResult['direction'],
            'current_price' => $currentPrice,
            'ema_20' => $emas['ema_20'],
            'ema_100' => $emas['ema_100'],
            'ema_200' => $emas['ema_200'],
            'ema_confluence_count' => 1, // Smart 20 EMA check passed
            'claude_score' => $claudeScore['score'],
            'trade_id' => $trade->id,
        ]);

        // Send trade entry notification
        try {
            $telegram = new TelegramNotificationService();
            $telegram->notifyTradeEntry(array_merge($trade->toArray(), [
                'max_risk' => $riskParams['max_risk'],
                'expected_profit' => $riskParams['expected_profit'],
            ]));
        } catch (\Exception $e) {
            $this->logError('Failed to send trade entry notification: ' . $e->getMessage());
        }

        return $trade->toArray();
    }

    /**
     * Monitor open position and manage exits
     * 
     * Called every minute by scheduled job to check for exit conditions.
     * 
     * @param Trade $trade Open trade to monitor
     * @return bool True if trade was exited
     */
    public function monitorAndExit(Trade $trade): bool
    {
        $this->logInfo('Monitoring trade', ['trade_id' => $trade->id]);

        // Get current option premium (real or simulated)
        $useRealData = setting('use_real_data', false);
        
        if ($useRealData) {
            // Build option symbol for Fyers
            $expiry = Carbon::parse($trade->entry_time)->format('dMy');
            $optionSuffix = ($trade->option_type === 'CALL' || $trade->option_type === 'CE') ? 'CE' : 'PE';
            $symbol = "NSE:BANKNIFTY{$expiry}{$trade->strike}{$optionSuffix}";
            $currentPremium = $this->fyersData->getOptionLTP($symbol);
        } else {
            $currentPremium = FyersSimulator::getOptionPremium(
                FyersSimulator::generateCandles('NSE:NIFTYBANK-INDEX', '1', 1)[0]['close'],
                $trade->strike,
                $trade->option_type === 'CALL' ? 'CE' : 'PE',
                0
            );
        }

        // Check for SL hit
        if ($this->isStopLossHit($trade, $currentPremium)) {
            return $this->exitTrade($trade, $currentPremium, 'sl_hit', 'loss');
        }

        // Check for target hit
        if ($this->isTargetHit($trade, $currentPremium)) {
            return $this->exitTrade($trade, $currentPremium, 'target_hit', 'win');
        }

        // Check for partial exit (if not already done)
        if (!$trade->partial_exit_done && $this->isPartialExitLevel($trade, $currentPremium)) {
            $this->executePartialExit($trade, $currentPremium);
        }

        // Check for EOD exit
        if ($this->isEODExitTime()) {
            return $this->exitTrade($trade, $currentPremium, 'eod_exit', $this->determineOutcome($trade, $currentPremium));
        }

        return false;
    }

    /**
     * Exit trade and record results
     */
    private function exitTrade(Trade $trade, float $exitPremium, string $exitReason, string $outcome): bool
    {
        $this->logInfo('Exiting trade', [
            'trade_id' => $trade->id,
            'reason' => $exitReason,
            'outcome' => $outcome
        ]);

        // Simulate exit order
        $exitOrder = FyersSimulator::simulatePaperOrder(
            $trade->option_type,
            $trade->strike,
            $exitPremium,
            $trade->remaining_lots ?? $trade->lots,
            'exit'
        );

        // Calculate P&L
        $pnl = $this->calculatePnL($trade, $exitOrder['filled_premium']);
        $rrAchieved = $this->calculateRRAchieved($trade, $exitOrder['filled_premium']);

        // Update trade record
        $trade->update([
            'exit_premium' => $exitOrder['filled_premium'],
            'exit_time' => now()->toTimeString(),
            'exit_reason' => $exitReason,
            'exit_slippage_pct' => $exitOrder['slippage_pct'],
            'outcome' => $outcome,
            'pnl_inr' => $pnl,
            'rr_achieved' => $rrAchieved,
            'status' => 'closed'
        ]);

        $this->logInfo('Trade closed', [
            'pnl' => $pnl,
            'rr' => $rrAchieved,
            'outcome' => $outcome
        ]);

        // Send trade exit notification
        try {
            $telegram = new TelegramNotificationService();
            $entryTime = Carbon::parse($trade->date . ' ' . $trade->entry_time);
            $duration = now()->diff($entryTime)->format('%H:%I');
            
            $telegram->notifyTradeExit(array_merge($trade->fresh()->toArray(), [
                'duration' => $duration,
                'pnl' => $pnl,
                'roi' => $trade->capital > 0 ? round(($pnl / $trade->capital) * 100, 2) : 0,
            ]));
        } catch (\Exception $e) {
            $this->logError('Failed to send trade exit notification: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Execute partial exit (50% at 1:1 RR)
     */
    private function executePartialExit(Trade $trade, float $currentPremium): void
    {
        $lotsToExit = (int)floor($trade->lots / 2);
        
        if ($lotsToExit < 1) {
            return;
        }

        $this->logInfo('Executing partial exit', [
            'trade_id' => $trade->id,
            'lots_to_exit' => $lotsToExit
        ]);

        $exitOrder = FyersSimulator::simulatePaperOrder(
            $trade->option_type,
            $trade->strike,
            $currentPremium,
            $lotsToExit,
            'exit'
        );

        $partialPnL = $this->calculatePartialPnL($trade, $exitOrder['filled_premium'], $lotsToExit);

        $trade->update([
            'partial_exit_done' => true,
            'partial_exit_premium' => $exitOrder['filled_premium'],
            'partial_exit_time' => now()->toTimeString(),
            'partial_exit_lots' => $lotsToExit,
            'remaining_lots' => $trade->lots - $lotsToExit,
            'partial_pnl_inr' => $partialPnL
        ]);

        $this->logInfo('Partial exit executed', [
            'pnl' => $partialPnL,
            'remaining_lots' => $trade->lots - $lotsToExit
        ]);
    }

    /**
     * Check if stop loss is hit
     */
    private function isStopLossHit(Trade $trade, float $currentPremium): bool
    {
        if ($trade->direction === 'bullish') {
            return $currentPremium >= $trade->sl_premium;
        } else {
            return $currentPremium >= $trade->sl_premium;
        }
    }

    /**
     * Check if target is hit
     */
    private function isTargetHit(Trade $trade, float $currentPremium): bool
    {
        if ($trade->direction === 'bullish') {
            return $currentPremium <= $trade->target_premium;
        } else {
            return $currentPremium <= $trade->target_premium;
        }
    }

    /**
     * Check if partial exit level is reached (1:1 RR)
     */
    private function isPartialExitLevel(Trade $trade, float $currentPremium): bool
    {
        if ($trade->direction === 'bullish') {
            return $currentPremium <= $trade->partial_exit_premium;
        } else {
            return $currentPremium <= $trade->partial_exit_premium;
        }
    }

    /**
     * Calculate P&L for trade
     */
    private function calculatePnL(Trade $trade, float $exitPremium): float
    {
        $remainingLots = $trade->remaining_lots ?? $trade->lots;
        $lotSize = setting('banknifty_lot_size', 15); // Bank Nifty lot size
        
        $entryValue = $trade->entry_premium * $remainingLots * $lotSize;
        $exitValue = $exitPremium * $remainingLots * $lotSize;
        
        $tradePnL = $exitValue - $entryValue;
        
        // Add partial exit P&L if exists
        if ($trade->partial_exit_done) {
            $tradePnL += $trade->partial_pnl_inr ?? 0;
        }
        
        return round($tradePnL, 2);
    }

    /**
     * Calculate partial P&L
     */
    private function calculatePartialPnL(Trade $trade, float $exitPremium, int $lotsExited): float
    {
        $lotSize = setting('banknifty_lot_size', 15);
        $entryValue = $trade->entry_premium * $lotsExited * $lotSize;
        $exitValue = $exitPremium * $lotsExited * $lotSize;
        
        return round($exitValue - $entryValue, 2);
    }

    /**
     * Calculate R:R achieved
     */
    private function calculateRRAchieved(Trade $trade, float $exitPremium): float
    {
        $risk = abs($trade->entry_premium - $trade->sl_premium);
        $reward = abs($trade->entry_premium - $exitPremium);
        
        if ($risk == 0) {
            return 0;
        }
        
        return round($reward / $risk, 2);
    }

    /**
     * Determine outcome based on P&L
     */
    private function determineOutcome(Trade $trade, float $exitPremium): string
    {
        $pnl = $this->calculatePnL($trade, $exitPremium);
        
        if ($pnl > 0) {
            return 'win';
        } elseif ($pnl < 0) {
            return 'loss';
        } else {
            return 'breakeven';
        }
    }

    /**
     * Record trade to database
     */
    private function recordTrade(array $data): Trade
    {
        return Trade::create($data);
    }

    /**
     * Check if we can trade today (market holiday check)
     */
    private function canTradeToday(): bool
    {
        // Check market calendar for holidays/events
        $today = now()->toDateString();
        
        $event = \App\Models\MarketCalendar::where('event_date', $today)
            ->where('action', 'skip')
            ->first();
        
        if ($event) {
            $this->logInfo('Market holiday today', ['event' => $event->description]);
            return false;
        }
        
        return true;
    }

    /**
     * Check if currently in trading window (configurable via settings)
     */
    private function isInTradingWindow(): bool
    {
        $now = now();
        $startTime = Carbon::parse(setting('trading_start_time', '09:15:00'));
        $endTime = Carbon::parse(setting('trading_end_time', '15:30:00'));
        
        return $now->between($startTime, $endTime);
    }

    /**
     * Check if it's time for EOD exit (3:15 PM)
     */
    private function isEODExitTime(): bool
    {
        $now = now();
        $eodTime = Carbon::parse(setting('eod_exit_time', '15:15:00'));
        
        return $now->greaterThanOrEqualTo($eodTime);
    }

    /**
     * Check if we already traded today
     */
    private function hasTradedToday(): bool
    {
        $today = now()->toDateString();
        
        $count = Trade::where('date', $today)->count();
        
        return $count > 0;
    }

    /**
     * Determine HTF bias (simplified - would analyze daily/weekly in production)
     */
    private function determineHTFBias(array $candles): string
    {
        // Simple: if last 50 candles trending up, bias is bullish
        $recentCandles = array_slice($candles, -50);
        $firstClose = $recentCandles[0]['close'];
        $lastClose = $recentCandles[count($recentCandles) - 1]['close'];
        
        if ($lastClose > $firstClose * 1.01) {
            return 'bullish';
        } elseif ($lastClose < $firstClose * 0.99) {
            return 'bearish';
        }
        
        return 'neutral';
    }

    /**
     * Get current session slot (morning/afternoon)
     */
    private function getCurrentSessionSlot(): string
    {
        $hour = now()->hour;
        
        if ($hour < 13) {
            return 'morning';
        }
        
        return 'afternoon';
    }

    /**
     * Determine market condition (trending/sideways)
     */
    private function determineMarketCondition(array $candles): string
    {
        // Simple: calculate ATR-based volatility
        $recentCandles = array_slice($candles, -20);
        
        $atr = 0;
        foreach ($recentCandles as $candle) {
            $atr += ($candle['high'] - $candle['low']);
        }
        $atr = $atr / count($recentCandles);
        
        $avgPrice = array_sum(array_column($recentCandles, 'close')) / count($recentCandles);
        $volatilityPct = ($atr / $avgPrice) * 100;
        
        if ($volatilityPct > 1.0) {
            return 'trending';
        }
        
        return 'sideways';
    }
}

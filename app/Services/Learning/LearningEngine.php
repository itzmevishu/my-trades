<?php

namespace App\Services\Learning;

use App\Services\BaseService;
use App\Services\Claude\ClaudeAPIService;
use App\Models\Trade;
use App\Models\StrategyConfig;
use App\Models\LearningLog;
use App\Models\ManualFeedback;

/**
 * LearningEngine - Self-learning system for strategy optimization
 * 
 * Core Features:
 * - Analyze every 10 completed trades
 * - Calculate pattern win rates and R:R performance
 * - Update pattern weights (reward winners, penalize losers)
 * - Build "avoid list" for consistently failing setups
 * - Use Claude AI for deep analysis
 * - Version strategy configs for tracking evolution
 * 
 * Learning Cycle:
 * 1. Every 10th trade triggers analysis
 * 2. Fetch last 10 trades
 * 3. Calculate pattern performance
 * 4. Claude analyzes what worked/didn't work
 * 5. Update pattern weights
 * 6. Add poor performers to avoid list
 * 7. Log learning cycle
 * 8. Increment strategy version
 * 
 * Phase: Week 7 - Learning Engine
 */
class LearningEngine extends BaseService
{
    private ClaudeAPIService $claudeService;
    private const LEARNING_CYCLE_TRADES = 10;

    public function __construct()
    {
        $this->claudeService = new ClaudeAPIService();
    }

    /**
     * Check if learning cycle should trigger
     * 
     * Called after every trade close.
     * 
     * @return bool True if cycle triggered
     */
    public function shouldTriggerLearningCycle(): bool
    {
        $totalTrades = Trade::where('status', 'closed')->count();
        
        // Trigger every 10 trades
        return $totalTrades > 0 && $totalTrades % self::LEARNING_CYCLE_TRADES === 0;
    }

    /**
     * Execute full learning cycle
     * 
     * @return array Learning results
     */
    public function executeLearningCycle(): array
    {
        $this->logInfo('Starting learning cycle');

        // Get last 10 trades
        $trades = Trade::where('status', 'closed')
            ->orderBy('date', 'desc')
            ->take(self::LEARNING_CYCLE_TRADES)
            ->get();

        if ($trades->count() < self::LEARNING_CYCLE_TRADES) {
            $this->logWarning('Not enough trades for learning cycle', [
                'found' => $trades->count(),
                'required' => self::LEARNING_CYCLE_TRADES
            ]);
            return [];
        }

        // Analyze pattern performance
        $patternPerformance = $this->analyzePatternPerformance($trades);
        
        $this->logInfo('Pattern performance calculated', [
            'patterns_analyzed' => count($patternPerformance)
        ]);

        // Get Claude's analysis
        $claudeAnalysis = $this->getClaudeAnalysis($trades);
        
        // Update pattern weights
        $weightChanges = $this->updatePatternWeights($patternPerformance);
        
        // Update avoid list
        $avoidListChanges = $this->updateAvoidList($patternPerformance);
        
        // Create new strategy version
        $newVersion = $this->createNewStrategyVersion($weightChanges, $avoidListChanges, $claudeAnalysis);
        
        // Log learning cycle
        $this->logLearningCycle($trades, $claudeAnalysis, $weightChanges, $avoidListChanges);
        
        $this->logInfo('Learning cycle completed', [
            'new_version' => $newVersion,
            'weight_changes' => count($weightChanges),
            'avoid_list_additions' => count($avoidListChanges)
        ]);

        return [
            'version' => $newVersion,
            'pattern_performance' => $patternPerformance,
            'weight_changes' => $weightChanges,
            'avoid_list_changes' => $avoidListChanges,
            'claude_analysis' => $claudeAnalysis
        ];
    }

    /**
     * Analyze pattern performance from trades
     * 
     * @param \Illuminate\Support\Collection $trades
     * @return array Pattern stats
     */
    private function analyzePatternPerformance($trades): array
    {
        $patterns = [];

        foreach ($trades as $trade) {
            $pattern = $trade->candle_pattern;

            if (!isset($patterns[$pattern])) {
                $patterns[$pattern] = [
                    'pattern' => $pattern,
                    'total_trades' => 0,
                    'wins' => 0,
                    'losses' => 0,
                    'breakevens' => 0,
                    'total_pnl' => 0,
                    'avg_rr' => 0,
                    'win_rate' => 0,
                    'avg_pnl' => 0,
                    'rr_values' => []
                ];
            }

            $patterns[$pattern]['total_trades']++;
            
            if ($trade->outcome === 'win') {
                $patterns[$pattern]['wins']++;
            } elseif ($trade->outcome === 'loss') {
                $patterns[$pattern]['losses']++;
            } else {
                $patterns[$pattern]['breakevens']++;
            }
            
            $patterns[$pattern]['total_pnl'] += $trade->pnl_inr;
            $patterns[$pattern]['rr_values'][] = $trade->rr_achieved;
        }

        // Calculate derived metrics
        foreach ($patterns as $pattern => &$stats) {
            $stats['win_rate'] = round(($stats['wins'] / $stats['total_trades']) * 100, 1);
            $stats['avg_pnl'] = round($stats['total_pnl'] / $stats['total_trades'], 2);
            $stats['avg_rr'] = round(array_sum($stats['rr_values']) / count($stats['rr_values']), 2);
            $stats['performance_score'] = $this->calculatePerformanceScore($stats);
        }

        // Sort by performance score
        uasort($patterns, fn($a, $b) => $b['performance_score'] <=> $a['performance_score']);

        return $patterns;
    }

    /**
     * Calculate performance score (0-10)
     * 
     * Factors:
     * - Win rate (40% weight)
     * - Average R:R (30% weight)
     * - Average P&L (30% weight)
     */
    private function calculatePerformanceScore(array $stats): float
    {
        // Normalize win rate (0-100 → 0-10)
        $winRateScore = ($stats['win_rate'] / 100) * 10;
        
        // Normalize R:R (0-3 → 0-10, capped)
        $rrScore = min(($stats['avg_rr'] / 3) * 10, 10);
        
        // Normalize P&L (negative → 0, positive → 0-10)
        $pnlScore = $stats['avg_pnl'] > 0 ? min(($stats['avg_pnl'] / 100) * 10, 10) : 0;
        
        // Weighted average
        $score = ($winRateScore * 0.4) + ($rrScore * 0.3) + ($pnlScore * 0.3);
        
        return round($score, 2);
    }

    /**
     * Get Claude's analysis of trade batch + manual feedback
     */
    private function getClaudeAnalysis($trades): string
    {
        $this->logInfo('Requesting Claude analysis');

        try {
            $tradesSummary = $trades->map(function($trade) {
                return [
                    'date' => $trade->date,
                    'pattern' => $trade->candle_pattern,
                    'outcome' => $trade->outcome,
                    'pnl' => $trade->pnl_inr,
                    'rr' => $trade->rr_achieved
                ];
            })->toArray();

            // Fetch manual feedback that hasn't been incorporated yet
            $manualNotes = ManualFeedback::notIncorporated()
                ->orderBy('importance', 'desc')
                ->orderBy('feedback_date', 'desc')
                ->get();

            // Prepare manual feedback for Claude
            $userInsights = [];
            if ($manualNotes->isNotEmpty()) {
                $userInsights = $manualNotes->map(function($note) {
                    return [
                        'date' => $note->feedback_date->format('Y-m-d'),
                        'category' => $note->category,
                        'importance' => $note->importance,
                        'observation' => $note->note,
                        'related_trade' => $note->trade_id ? "#" . $note->trade_id : null,
                    ];
                })->toArray();
            }

            // Enhanced prompt with manual feedback
            $prompt = $this->buildLearningPrompt($tradesSummary, $userInsights);
            
            $analysis = $this->claudeService->generateCustomAnalysis($prompt);

            // Mark manual feedback as incorporated
            if ($manualNotes->isNotEmpty()) {
                $this->markFeedbackAsIncorporated($manualNotes);
            }

            return $analysis;

        } catch (\Exception $e) {
            $this->logError('Claude analysis failed', ['error' => $e->getMessage()]);
            return 'Analysis unavailable due to API error.';
        }
    }

    /**
     * Build enhanced learning prompt with manual feedback
     */
    private function buildLearningPrompt(array $trades, array $userInsights): string
    {
        $prompt = "Analyze this trading performance batch:\n\n";
        
        $prompt .= "TRADE RESULTS:\n";
        foreach ($trades as $trade) {
            $prompt .= "- {$trade['date']}: {$trade['pattern']} → {$trade['outcome']} (₹{$trade['pnl']}, R:R {$trade['rr']})\n";
        }
        
        if (!empty($userInsights)) {
            $prompt .= "\n\nTRADER'S OBSERVATIONS (User Insights):\n";
            foreach ($userInsights as $insight) {
                $importance = strtoupper($insight['importance']);
                $prompt .= "- [{$importance}] [{$insight['category']}] {$insight['observation']}";
                if ($insight['related_trade']) {
                    $prompt .= " (Trade {$insight['related_trade']})";
                }
                $prompt .= "\n";
            }
            $prompt .= "\nIMPORTANT: Pay special attention to the trader's observations above. These are real-world insights that complement the statistical analysis.\n";
        }
        
        $prompt .= "\n\nPROVIDE:\n";
        $prompt .= "1. Pattern Performance Summary\n";
        $prompt .= "2. What worked well\n";
        $prompt .= "3. What didn't work\n";
        $prompt .= "4. Key lessons learned (incorporate trader's observations)\n";
        $prompt .= "5. Specific recommendations for next trades\n";
        
        if (!empty($userInsights)) {
            $prompt .= "6. How to apply the trader's insights to improve strategy\n";
        }
        
        return $prompt;
    }

    /**
     * Mark manual feedback as incorporated into learning
     */
    private function markFeedbackAsIncorporated($feedbackRecords): void
    {
        foreach ($feedbackRecords as $feedback) {
            $feedback->update([
                'incorporated_in_learning' => true,
                'learning_log_id' => null, // Will be set when learning log is saved
            ]);
        }
        
        $this->logInfo('Marked manual feedback as incorporated', [
            'count' => $feedbackRecords->count()
        ]);
    }

    /**
     * Update pattern weights based on performance
     * 
     * Strategy:
     * - Top performers (score >= 7): Increase weight by 10%
     * - Mid performers (score 4-7): Keep current weight
     * - Poor performers (score < 4): Decrease weight by 20%
     * 
     * @param array $patternPerformance
     * @return array Weight changes
     */
    private function updatePatternWeights(array $patternPerformance): array
    {
        $currentConfig = StrategyConfig::latest()->first();
        
        if (!$currentConfig) {
            $this->logWarning('No strategy config found, skipping weight update');
            return [];
        }

        $currentWeights = $currentConfig->pattern_weights;
        $changes = [];

        foreach ($patternPerformance as $pattern => $stats) {
            $currentWeight = $currentWeights[$pattern] ?? 1.0;
            $newWeight = $currentWeight;

            if ($stats['performance_score'] >= 7) {
                // Reward top performers
                $newWeight = round($currentWeight * 1.10, 2);
                $changes[$pattern] = [
                    'old' => $currentWeight,
                    'new' => $newWeight,
                    'change' => '+10%',
                    'reason' => 'Top performer (score >= 7)'
                ];
            } elseif ($stats['performance_score'] < 4) {
                // Penalize poor performers
                $newWeight = round($currentWeight * 0.80, 2);
                $changes[$pattern] = [
                    'old' => $currentWeight,
                    'new' => $newWeight,
                    'change' => '-20%',
                    'reason' => 'Poor performer (score < 4)'
                ];
            }

            // Ensure weight stays within bounds (0.1 - 2.0)
            $newWeight = max(0.1, min(2.0, $newWeight));
            $currentWeights[$pattern] = $newWeight;
        }

        // Save updated weights (will be saved in new version)
        $this->updatedWeights = $currentWeights;

        return $changes;
    }

    /**
     * Update avoid list (patterns to skip)
     * 
     * Add patterns that:
     * - Win rate < 30% AND
     * - Traded at least 5 times AND
     * - Average P&L negative
     * 
     * @param array $patternPerformance
     * @return array Avoid list additions
     */
    private function updateAvoidList(array $patternPerformance): array
    {
        $currentConfig = StrategyConfig::latest()->first();
        $avoidList = $currentConfig ? $currentConfig->avoid_setups : [];
        $additions = [];

        foreach ($patternPerformance as $pattern => $stats) {
            // Check if should be avoided
            if ($stats['win_rate'] < 30 && 
                $stats['total_trades'] >= 5 && 
                $stats['avg_pnl'] < 0) {
                
                // Check if not already in avoid list
                if (!in_array($pattern, $avoidList)) {
                    $avoidList[] = $pattern;
                    $additions[] = [
                        'pattern' => $pattern,
                        'reason' => "Win rate {$stats['win_rate']}%, Avg P&L ₹{$stats['avg_pnl']}",
                        'stats' => $stats
                    ];
                }
            }
        }

        $this->updatedAvoidList = $avoidList;

        return $additions;
    }

    /**
     * Create new strategy config version
     */
    private function createNewStrategyVersion(array $weightChanges, array $avoidListChanges, string $claudeAnalysis): int
    {
        $currentConfig = StrategyConfig::latest()->first();
        $newVersion = $currentConfig ? $currentConfig->version + 1 : 1;

        $learningNote = $this->buildLearningNote($weightChanges, $avoidListChanges);

        StrategyConfig::create([
            'version' => $newVersion,
            'pattern_weights' => $this->updatedWeights ?? $currentConfig->pattern_weights,
            'best_entry_window' => $currentConfig->best_entry_window ?? '11:15-12:00',
            'min_score_threshold' => $currentConfig->min_score_threshold ?? 6.0,
            'avoid_setups' => $this->updatedAvoidList ?? $currentConfig->avoid_setups ?? [],
            'learning_note' => $learningNote,
            'claude_analysis' => $claudeAnalysis
        ]);

        $this->logInfo('New strategy version created', ['version' => $newVersion]);

        return $newVersion;
    }

    /**
     * Log learning cycle to database
     */
    private function logLearningCycle($trades, string $claudeAnalysis, array $weightChanges, array $avoidListChanges): void
    {
        $configChanges = [
            'weight_changes' => $weightChanges,
            'avoid_list_additions' => $avoidListChanges
        ];

        LearningLog::create([
            'trigger_trade_count' => self::LEARNING_CYCLE_TRADES,
            'trades_analysed' => $trades->count(),
            'config_changes' => $configChanges,
            'claude_full_response' => $claudeAnalysis
        ]);

        $this->logInfo('Learning cycle logged to database');
    }

    /**
     * Build human-readable learning note
     */
    private function buildLearningNote(array $weightChanges, array $avoidListChanges): string
    {
        $note = "Learning Cycle " . date('Y-m-d H:i:s') . "\n\n";

        if (count($weightChanges) > 0) {
            $note .= "Pattern Weight Adjustments:\n";
            foreach ($weightChanges as $pattern => $change) {
                $note .= "- {$pattern}: {$change['old']} → {$change['new']} ({$change['change']})\n";
                $note .= "  Reason: {$change['reason']}\n";
            }
            $note .= "\n";
        }

        if (count($avoidListChanges) > 0) {
            $note .= "Avoid List Additions:\n";
            foreach ($avoidListChanges as $addition) {
                $note .= "- {$addition['pattern']}: {$addition['reason']}\n";
            }
            $note .= "\n";
        }

        if (count($weightChanges) === 0 && count($avoidListChanges) === 0) {
            $note .= "No significant changes. Strategy performing consistently.\n";
        }

        return $note;
    }

    /**
     * Get current strategy config
     */
    public function getCurrentStrategy(): ?StrategyConfig
    {
        return StrategyConfig::latest()->first();
    }

    /**
     * Get pattern weight for scoring
     */
    public function getPatternWeight(string $pattern): float
    {
        $config = $this->getCurrentStrategy();
        
        if (!$config) {
            return 1.0; // Default weight
        }

        return $config->pattern_weights[$pattern] ?? 1.0;
    }

    /**
     * Check if pattern is in avoid list
     */
    public function shouldAvoidPattern(string $pattern): bool
    {
        $config = $this->getCurrentStrategy();
        
        if (!$config) {
            return false;
        }

        return in_array($pattern, $config->avoid_setups);
    }

    /**
     * Get learning history
     */
    public function getLearningHistory(int $limit = 10): array
    {
        return LearningLog::orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get strategy evolution (all versions)
     */
    public function getStrategyEvolution(): array
    {
        return StrategyConfig::orderBy('version', 'desc')
            ->get()
            ->toArray();
    }
}

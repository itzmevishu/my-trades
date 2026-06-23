<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Trade;
use App\Models\StrategyConfig;
use App\Services\Learning\LearningEngine;

/**
 * Test Learning Cycle at Different Stages
 * 
 * Simulates learning cycles at different trade counts
 * to validate pattern weight evolution and avoid list logic
 * 
 * Usage: 
 *   php artisan test:learning 10   - Analyze first 10 trades
 *   php artisan test:learning 20   - Analyze first 20 trades
 *   php artisan test:learning 30   - Analyze all 30 trades
 */
class TestLearningCommand extends Command
{
    protected $signature = 'test:learning {trades=10 : Number of trades to analyze}';
    protected $description = 'Test learning cycle at different stages';
    
    private LearningEngine $learningEngine;

    public function __construct(LearningEngine $learningEngine)
    {
        parent::__construct();
        $this->learningEngine = $learningEngine;
    }

    public function handle(): int
    {
        $tradeCount = (int) $this->argument('trades');
        
        $this->info("🧪 Testing Learning Cycle with {$tradeCount} trades...");
        $this->newLine();
        
        // Validate trade count
        $actualTrades = Trade::count();
        if ($actualTrades < $tradeCount) {
            $this->error("❌ Only {$actualTrades} trades in database. Need {$tradeCount}.");
            $this->info("💡 Run: php artisan db:seed --class=ExtendedTestSeeder");
            return Command::FAILURE;
        }
        
        // Get current strategy version
        $currentVersion = StrategyConfig::where('is_active', true)->value('version') ?? 0;
        $expectedVersion = $currentVersion + 1;
        
        // Show pre-learning state
        $this->displayPreLearningState($tradeCount);
        
        // Execute learning cycle
        $this->info('🔄 Executing learning cycle...');
        $this->newLine();
        
        try {
            $result = $this->learningEngine->executeLearningCycle();
            
            // Check if learning cycle actually ran (returns empty array if not enough trades)
            if (empty($result)) {
                $this->error('❌ Learning cycle did not execute (not enough trades or other error)');
                return Command::FAILURE;
            }
            
            $this->displayLearningResults($result);
            $this->validateStrategyUpdate($expectedVersion);
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Learning cycle failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function displayPreLearningState(int $tradeCount): void
    {
        $trades = Trade::orderBy('date')->limit($tradeCount)->get();
        
        $this->info('📊 TRADE DISTRIBUTION');
        $this->newLine();
        
        // Pattern stats
        $patterns = $trades->groupBy('candle_pattern')->map(function ($group) {
            $wins = $group->where('outcome', 'win')->count();
            $total = $group->count();
            return [
                'count' => $total,
                'wins' => $wins,
                'losses' => $total - $wins,
                'win_rate' => $total > 0 ? round(($wins / $total) * 100, 1) : 0,
                'total_pnl' => $group->sum('pnl_inr'),
            ];
        });
        
        $rows = [];
        foreach ($patterns as $pattern => $stats) {
            $rows[] = [
                $pattern,
                $stats['count'],
                $stats['wins'],
                $stats['losses'],
                $stats['win_rate'] . '%',
                '₹' . number_format($stats['total_pnl'], 0),
            ];
        }
        
        $this->table(
            ['Pattern', 'Total', 'Wins', 'Losses', 'Win Rate', 'P&L'],
            $rows
        );
        
        $this->newLine();
        
        // Current strategy
        $current = StrategyConfig::where('is_active', true)->first();
        if ($current) {
            $this->info("📋 Current Strategy: v{$current->version}");
            $this->line("   Analyzed: {$current->trades_analysed} trades");
            $this->line("   Win Rate: " . round($current->win_rate_at_update, 1) . "%");
            $this->line("   Avoid List: " . (empty($current->avoid_setups) ? 'None' : implode(', ', $current->avoid_setups)));
        }
        
        $this->newLine();
    }
    
    private function displayLearningResults(array $result): void
    {
        $this->info('✅ LEARNING CYCLE COMPLETED');
        $this->newLine();
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['New Version', 'v' . $result['version']],
                ['Weight Changes', count($result['weight_changes'])],
                ['Avoid List Additions', count($result['avoid_list_changes'])],
            ]
        );
        
        if (!empty($result['weight_changes'])) {
            $this->newLine();
            $this->info('📊 PATTERN WEIGHT CHANGES');
            $this->newLine();
            
            $rows = [];
            foreach ($result['weight_changes'] as $pattern => $change) {
                $rows[] = [
                    $pattern,
                    number_format($change['old'], 2),
                    number_format($change['new'], 2),
                    $change['change'],
                    $change['reason'],
                ];
            }
            
            $this->table(
                ['Pattern', 'Old Weight', 'New Weight', 'Change', 'Reason'],
                $rows
            );
        }
        
        if (!empty($result['avoid_list_changes'])) {
            $this->newLine();
            $this->warn('⚠️  AVOID LIST ADDITIONS');
            foreach ($result['avoid_list_changes'] as $pattern) {
                $this->line("   • {$pattern} (blacklisted due to poor performance)");
            }
        }
        
        $this->newLine();
    }
    
    private function validateStrategyUpdate(int $expectedVersion): void
    {
        $newStrategy = StrategyConfig::where('version', $expectedVersion)->first();
        
        if (!$newStrategy) {
            $this->error('❌ VALIDATION FAILED: Strategy v' . $expectedVersion . ' not created');
            return;
        }
        
        $this->info('✅ VALIDATION PASSED');
        $this->line("   • Strategy v{$expectedVersion} created");
        $this->line("   • Active status: " . ($newStrategy->is_active ? 'Yes' : 'No'));
        $this->line("   • Trades analyzed: {$newStrategy->trades_analysed}");
        $this->line("   • Win rate: " . round($newStrategy->win_rate_at_update, 1) . "%");
        
        // Check old strategy deactivated
        $oldStrategy = StrategyConfig::where('version', $expectedVersion - 1)->first();
        if ($oldStrategy && !$oldStrategy->is_active) {
            $this->line("   • Previous version deactivated: Yes");
        }
        
        $this->newLine();
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Learning\LearningEngine;

/**
 * Trading Learn Command
 * 
 * Manually trigger learning cycle analysis.
 * Automatically runs after every 10th trade.
 * 
 * Usage: php artisan trading:learn
 */
class TradingLearnCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'trading:learn {--force : Force learning even if not at 10-trade cycle}';

    /**
     * The console command description.
     */
    protected $description = 'Execute learning cycle to optimize strategy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧠 Starting Learning Cycle...');
        $this->newLine();

        $engine = new LearningEngine();

        // Check if should trigger (unless forced)
        if (!$this->option('force') && !$engine->shouldTriggerLearningCycle()) {
            $this->warn('⚠️  Learning cycle not yet due');
            $this->line('Learning triggers automatically every 10 trades.');
            $this->newLine();
            $this->line('Use --force flag to run analysis anyway:');
            $this->line('  php artisan trading:learn --force');
            return Command::SUCCESS;
        }

        if ($this->option('force')) {
            $this->warn('⚠️  Force mode: Running learning cycle manually');
            $this->newLine();
        }

        $this->info('Analyzing last 10 trades...');

        try {
            $result = $engine->executeLearningCycle();

            if (empty($result)) {
                $this->error('❌ Not enough trades for learning cycle');
                $this->line('Minimum 10 closed trades required.');
                return Command::FAILURE;
            }

            $this->info('✅ Learning cycle completed!');
            $this->newLine();

            // Display new version
            $this->table(
                ['Metric', 'Value'],
                [
                    ['New Strategy Version', $result['version']],
                    ['Weight Changes', count($result['weight_changes'])],
                    ['Avoid List Additions', count($result['avoid_list_changes'])],
                ]
            );
            $this->newLine();

            // Display pattern performance
            if (!empty($result['pattern_performance'])) {
                $this->info('📊 Pattern Performance:');
                $this->newLine();

                $rows = [];
                foreach ($result['pattern_performance'] as $pattern => $stats) {
                    $rows[] = [
                        $pattern,
                        $stats['total_trades'],
                        $stats['win_rate'] . '%',
                        $stats['avg_rr'],
                        '₹' . $stats['avg_pnl'],
                        $stats['performance_score'],
                    ];
                }

                $this->table(
                    ['Pattern', 'Trades', 'Win Rate', 'Avg R:R', 'Avg P&L', 'Score'],
                    $rows
                );
                $this->newLine();
            }

            // Display weight changes
            if (!empty($result['weight_changes'])) {
                $this->info('⚖️  Pattern Weight Changes:');
                $this->newLine();

                foreach ($result['weight_changes'] as $pattern => $change) {
                    $this->line("  • {$pattern}: {$change['old']} → {$change['new']} ({$change['change']})");
                    $this->line("    Reason: {$change['reason']}");
                }
                $this->newLine();
            }

            // Display avoid list additions
            if (!empty($result['avoid_list_changes'])) {
                $this->warn('🚫 Patterns Added to Avoid List:');
                $this->newLine();

                foreach ($result['avoid_list_changes'] as $addition) {
                    $this->line("  • {$addition['pattern']}: {$addition['reason']}");
                }
                $this->newLine();
            }

            // Display Claude analysis
            if (!empty($result['claude_analysis'])) {
                $this->info('🤖 Claude AI Analysis:');
                $this->newLine();
                $this->line($result['claude_analysis']);
                $this->newLine();
            }

            $this->info('💾 Strategy updated to version ' . $result['version']);
            $this->info('📈 Next learning cycle will trigger after 10 more trades');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Learning cycle failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}

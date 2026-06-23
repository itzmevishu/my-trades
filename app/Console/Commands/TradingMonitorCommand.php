<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Trading\PaperTradingService;
use App\Models\Trade;

/**
 * Trading Monitor Command
 * 
 * Monitor open positions and manage exits (SL/target/partial/EOD).
 * In production, this runs automatically every 1 minute via cron.
 * 
 * Usage: php artisan trading:monitor
 */
class TradingMonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'trading:monitor';

    /**
     * The console command description.
     */
    protected $description = 'Monitor open positions and manage exits';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('👀 Monitoring open positions...');
        $this->newLine();

        // Get all open trades
        $openTrades = Trade::where('status', 'open')->get();

        if ($openTrades->isEmpty()) {
            $this->warn('⚠️  No open positions to monitor');
            $this->line('Run: php artisan trading:scan to find entry signals');
            return Command::SUCCESS;
        }

        $this->info("Found {$openTrades->count()} open position(s)");
        $this->newLine();

        $service = new PaperTradingService();
        $exitedCount = 0;

        foreach ($openTrades as $trade) {
            $this->line("Checking Trade #{$trade->id} ({$trade->candle_pattern}, {$trade->direction})...");

            try {
                $exited = $service->monitorAndExit($trade);

                if ($exited) {
                    $trade->refresh(); // Reload from database

                    $this->info("  ✅ Trade exited!");
                    $this->table(
                        ['Field', 'Value'],
                        [
                            ['Exit Reason', $trade->exit_reason],
                            ['Exit Premium', '₹' . $trade->exit_premium],
                            ['Outcome', strtoupper($trade->outcome)],
                            ['P&L', '₹' . $trade->pnl_inr],
                            ['R:R Achieved', $trade->rr_achieved],
                            ['Exit Time', $trade->exit_time],
                        ]
                    );

                    $exitedCount++;
                } else {
                    $this->line("  ⏳ Still running (monitoring...)");
                }

            } catch (\Exception $e) {
                $this->error("  ❌ Monitor failed for trade #{$trade->id}: " . $e->getMessage());
            }

            $this->newLine();
        }

        if ($exitedCount > 0) {
            $this->info("🎯 {$exitedCount} trade(s) exited successfully!");
            
            // Check if learning cycle should trigger
            $learningEngine = new \App\Services\Learning\LearningEngine();
            if ($learningEngine->shouldTriggerLearningCycle()) {
                $this->newLine();
                $this->info('🧠 Learning cycle triggered!');
                $this->line('Run: php artisan trading:learn to execute analysis');
            }
        } else {
            $this->info('✅ All positions still running');
        }

        return Command::SUCCESS;
    }
}

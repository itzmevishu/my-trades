<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Trading\PaperTradingService;

/**
 * Trading Scan Command
 * 
 * Manually trigger entry signal scan.
 * In production, this runs automatically every 15 minutes via cron.
 * 
 * Usage: php artisan trading:scan
 */
class TradingScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'trading:scan';

    /**
     * The console command description.
     */
    protected $description = 'Scan for entry signals and execute trades if valid setup found';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Starting entry signal scan...');
        $this->newLine();

        $service = new PaperTradingService();

        try {
            $result = $service->scanAndExecute();

            if ($result) {
                $this->info('✅ Trade Entry Executed!');
                $this->newLine();
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Trade ID', $result['id']],
                        ['Pattern', $result['candle_pattern']],
                        ['Direction', $result['direction']],
                        ['Strike', $result['strike']],
                        ['Entry Premium', '₹' . $result['entry_premium']],
                        ['SL Premium', '₹' . $result['sl_premium']],
                        ['Target Premium', '₹' . $result['target_premium']],
                        ['Lots', $result['lots']],
                        ['Quantity', $result['quantity']],
                        ['Claude Score', $result['claude_score'] . '/10'],
                        ['Entry Time', $result['entry_time']],
                    ]
                );
                $this->newLine();
                $this->info('💡 Trade is now being monitored. Run: php artisan trading:monitor');
            } else {
                $this->warn('⚠️  No valid entry signal found');
                $this->newLine();
                $this->line('Possible reasons:');
                $this->line('  • Already traded today (1 trade/day limit)');
                $this->line('  • Outside trading window (11:15 AM - 2:00 PM)');
                $this->line('  • Market holiday');
                $this->line('  • No pattern detected');
                $this->line('  • Insufficient EMA confluence');
                $this->line('  • Claude score below threshold');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Scan failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}

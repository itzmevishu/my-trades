<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Trade;
use App\Models\StrategyConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Extended Test Data Seeder
 * 
 * Creates 30 trades over 6 weeks to test:
 * - Multiple learning cycles (3 cycles: after 10, 20, 30 trades)
 * - Avoid list functionality (poor patterns blacklisted)
 * - All exit scenarios (SL, target, partial, EOD)
 * - Pattern evolution tracking
 * - Win rate variations
 * 
 * Usage: php artisan db:seed --class=ExtendedTestSeeder
 */
class ExtendedTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🧪 Seeding extended test data (30 trades)...');
        
        // Clear existing data (with foreign key checks disabled)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Trade::truncate();
        StrategyConfig::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        // Create initial strategy
        StrategyConfig::create([
            'version' => 1,
            'pattern_weights' => [
                'bullish_engulfing' => 1.0,
                'bearish_engulfing' => 1.0,
                'bullish_pinbar' => 1.0,
                'bearish_pinbar' => 1.0,
                'inside_bar_breakout' => 1.0,
            ],
            'best_entry_window' => '11:15-12:00',
            'min_score_threshold' => 6.0,
            'avoid_setups' => [],
            'learning_note' => 'Initial strategy - equal weights',
            'trades_analysed' => 0,
            'win_rate_at_update' => 0.0,
            'is_active' => true,
        ]);
        
        // 30 trades with specific pattern distribution
        $trades = [
            // Week 1-2: First 10 trades (bullish/bearish engulfing performing well)
            ['2026-06-10', 'long', 'bullish_engulfing', 8.0, 'win', 300, 'TARGET_HIT'],
            ['2026-06-11', 'short', 'bearish_pinbar', 6.5, 'loss', -150, 'SL_HIT'],
            ['2026-06-12', 'long', 'bullish_pinbar', 7.5, 'win', 250, 'TARGET_HIT'],
            ['2026-06-13', 'long', 'inside_bar_breakout', 6.0, 'loss', -90, 'EOD_EXIT'],
            ['2026-06-14', 'short', 'bearish_engulfing', 8.5, 'win', 300, 'TARGET_HIT'],
            ['2026-06-17', 'long', 'bullish_engulfing', 8.0, 'win', 300, 'TARGET_HIT'],
            ['2026-06-18', 'short', 'bearish_pinbar', 5.5, 'loss', -150, 'SL_HIT'],
            ['2026-06-19', 'long', 'bullish_pinbar', 7.0, 'win', 200, 'PARTIAL_EXIT'],
            ['2026-06-20', 'short', 'inside_bar_breakout', 5.0, 'loss', -100, 'EOD_EXIT'],
            ['2026-06-21', 'long', 'bullish_engulfing', 9.0, 'win', 300, 'TARGET_HIT'], // 10th - learning cycle 1
            
            // Week 3-4: Next 10 trades (inside bar & bearish pinbar continue failing)
            ['2026-06-24', 'short', 'bearish_engulfing', 7.5, 'win', 280, 'TARGET_HIT'],
            ['2026-06-25', 'long', 'bullish_pinbar', 8.0, 'win', 300, 'TARGET_HIT'],
            ['2026-06-26', 'short', 'inside_bar_breakout', 5.5, 'loss', -80, 'EOD_EXIT'],
            ['2026-06-27', 'long', 'bullish_engulfing', 8.5, 'win', 300, 'TARGET_HIT'],
            ['2026-06-28', 'short', 'bearish_pinbar', 6.0, 'loss', -140, 'SL_HIT'],
            ['2026-07-01', 'long', 'bullish_pinbar', 7.5, 'win', 250, 'TARGET_HIT'],
            ['2026-07-02', 'short', 'bearish_engulfing', 8.0, 'win', 300, 'TARGET_HIT'],
            ['2026-07-03', 'long', 'inside_bar_breakout', 6.0, 'loss', -110, 'SL_HIT'],
            ['2026-07-04', 'long', 'bullish_engulfing', 8.5, 'win', 300, 'TARGET_HIT'],
            ['2026-07-05', 'short', 'bearish_pinbar', 5.5, 'loss', -150, 'SL_HIT'], // 20th - learning cycle 2
            
            // Week 5-6: Final 10 trades (avoid list should kick in for poor patterns)
            ['2026-07-08', 'long', 'bullish_pinbar', 7.5, 'win', 270, 'TARGET_HIT'],
            ['2026-07-09', 'short', 'bearish_engulfing', 8.0, 'win', 300, 'TARGET_HIT'],
            ['2026-07-10', 'long', 'bullish_engulfing', 9.0, 'win', 300, 'TARGET_HIT'],
            ['2026-07-11', 'short', 'bearish_engulfing', 7.5, 'win', 280, 'TARGET_HIT'],
            ['2026-07-12', 'long', 'bullish_pinbar', 8.0, 'win', 300, 'TARGET_HIT'],
            ['2026-07-15', 'long', 'bullish_engulfing', 8.5, 'win', 300, 'TARGET_HIT'],
            ['2026-07-16', 'short', 'bearish_engulfing', 8.0, 'win', 290, 'TARGET_HIT'],
            ['2026-07-17', 'long', 'bullish_pinbar', 7.0, 'win', 250, 'PARTIAL_EXIT'],
            ['2026-07-18', 'long', 'bullish_engulfing', 8.5, 'win', 300, 'TARGET_HIT'],
            ['2026-07-19', 'short', 'bearish_engulfing', 8.0, 'win', 300, 'TARGET_HIT'], // 30th - learning cycle 3
        ];
        
        foreach ($trades as $index => $trade) {
            Trade::create([
                'date' => Carbon::parse($trade[0]),
                'direction' => $trade[1],
                'instrument' => 'BANKNIFTY',
                'expiry' => Carbon::parse($trade[0]),
                'strike' => 50000 + (($index % 5) * 50), // Vary strikes
                'entry_premium' => 145.00 + ($index % 10), // Vary premiums
                'exit_premium' => $trade[4] === 'win' ? (140.00 - ($index % 5)) : (150.00 + ($index % 5)),
                'sl_premium' => 155.00,
                'target_premium' => 135.00,
                'lots' => $index % 3 === 0 ? 1 : 2, // Mix of 1 and 2 lots
                'capital_at_trade' => 300000,
                'candle_pattern' => $trade[2],
                'ema_configuration' => json_encode([
                    'ema_20' => 55000 + ($index * 10),
                    'ema_100' => 54900 + ($index * 8),
                    'ema_200' => 54700 + ($index * 5),
                    'confluence_count' => $trade[4] === 'win' ? 2 : 1
                ]),
                'htf_bias' => $trade[1] === 'long' ? 'bullish' : 'bearish',
                'session_slot' => $index % 3 === 0 ? '11:15-12:00' : ($index % 3 === 1 ? '12:00-13:00' : '13:00-14:00'),
                'claude_score' => $trade[3],
                'claude_reasoning' => 'Extended test trade #' . ($index + 1) . ' - ' . $trade[2],
                'status' => 'closed',
                'entry_time' => '11:' . (15 + ($index % 45)) . ':00',
                'exit_time' => '13:' . (30 + ($index % 30)) . ':00',
                'exit_type' => $trade[6],
                'outcome' => $trade[4],
                'pnl_inr' => $trade[5],
                'rr_achieved' => $trade[4] === 'win' ? round(abs($trade[5] / 150), 2) : -1.0,
                'mode' => 'paper',
            ]);
            
            // Show progress every 10 trades
            if (($index + 1) % 10 === 0) {
                $this->command->info("  ✅ Created " . ($index + 1) . " trades");
            }
        }
        
        $this->displaySummary();
    }
    
    private function displaySummary(): void
    {
        $totalTrades = Trade::count();
        $wins = Trade::where('outcome', 'win')->count();
        $losses = Trade::where('outcome', 'loss')->count();
        $winRate = round(($wins / $totalTrades) * 100, 1);
        $totalPnL = Trade::sum('pnl_inr');
        
        // Pattern breakdown
        $patterns = Trade::selectRaw('candle_pattern, COUNT(*) as count, 
                                     SUM(CASE WHEN outcome = "win" THEN 1 ELSE 0 END) as wins')
            ->groupBy('candle_pattern')
            ->get();
        
        $this->command->newLine();
        $this->command->info('📊 EXTENDED TEST DATA SUMMARY');
        $this->command->newLine();
        
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Total Trades', $totalTrades],
                ['Wins', $wins],
                ['Losses', $losses],
                ['Win Rate', $winRate . '%'],
                ['Total P&L', '₹' . number_format($totalPnL, 2)],
            ]
        );
        
        $this->command->newLine();
        $this->command->info('📈 PATTERN BREAKDOWN');
        $this->command->newLine();
        
        $patternRows = [];
        foreach ($patterns as $pattern) {
            $patternWinRate = round(($pattern->wins / $pattern->count) * 100, 1);
            $patternRows[] = [
                $pattern->candle_pattern,
                $pattern->count,
                $pattern->wins,
                $patternWinRate . '%'
            ];
        }
        
        $this->command->table(
            ['Pattern', 'Total', 'Wins', 'Win Rate'],
            $patternRows
        );
        
        $this->command->newLine();
        $this->command->info('🧪 TEST SCENARIOS');
        $this->command->line('  1. Run learning after 10 trades:  php artisan test:learning 10');
        $this->command->line('  2. Run learning after 20 trades:  php artisan test:learning 20');
        $this->command->line('  3. Run learning after 30 trades:  php artisan test:learning 30');
        $this->command->line('  4. Test all features:            php artisan test:all');
        $this->command->newLine();
        $this->command->info('✅ Extended test data seeded successfully!');
    }
}

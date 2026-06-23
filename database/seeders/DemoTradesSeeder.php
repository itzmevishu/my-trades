<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Trade;
use App\Models\StrategyConfig;
use Carbon\Carbon;

class DemoTradesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding demo trades and strategy...');
        
        // Create initial strategy (skip if exists)
        if (!StrategyConfig::where('version', 1)->exists()) {
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
            $this->command->info('  ✅ Strategy v1 created');
        } else {
            $this->command->warn('  ⚠️  Strategy v1 already exists, skipping');
        }
        
        // Create 12 trades over 3 weeks
        $trades = [
            ['2026-06-16', 'long', 'bullish_engulfing', 8.0, 'win', 300],
            ['2026-06-17', 'short', 'bearish_pinbar', 6.5, 'loss', -150],
            ['2026-06-18', 'long', 'bullish_pinbar', 8.5, 'win', 300],
            ['2026-06-19', 'long', 'inside_bar_breakout', 6.0, 'loss', -90],
            ['2026-06-20', 'short', 'bearish_engulfing', 7.5, 'win', 300],
            ['2026-06-23', 'long', 'bullish_engulfing', 8.0, 'win', 300],
            ['2026-06-24', 'short', 'bearish_pinbar', 6.0, 'loss', -150],
            ['2026-06-25', 'long', 'bullish_pinbar', 8.5, 'win', 300],
            ['2026-06-26', 'short', 'inside_bar_breakout', 5.5, 'loss', -60],
            ['2026-06-27', 'long', 'bullish_engulfing', 7.5, 'win', 300],  // 10th trade
            ['2026-06-30', 'long', 'bullish_engulfing', 8.0, 'win', 300],
            ['2026-07-01', 'short', 'bearish_engulfing', 7.0, 'win', 300],
        ];
        
        foreach ($trades as $index => $trade) {
            Trade::create([
                'date' => Carbon::parse($trade[0]),
                'direction' => $trade[1],
                'instrument' => 'BANKNIFTY',
                'expiry' => Carbon::parse($trade[0]),
                'strike' => 50000,
                'entry_premium' => 150.00,
                'exit_premium' => $trade[4] === 'win' ? 140.00 : 155.00,
                'sl_premium' => 155.00,
                'target_premium' => 140.00,
                'lots' => 2,
                'capital_at_trade' => 300000,
                'candle_pattern' => $trade[2],
                'ema_configuration' => json_encode(['ema_20' => 55000, 'ema_100' => 54900, 'ema_200' => 54700]),
                'htf_bias' => $trade[1] === 'long' ? 'bullish' : 'bearish',
                'session_slot' => '11:15-12:00',
                'claude_score' => $trade[3],
                'claude_reasoning' => 'Demo trade #' . ($index + 1),
                'status' => 'closed',
                'entry_time' => '11:30:00',
                'exit_time' => '13:30:00',
                'exit_type' => $trade[4] === 'win' ? 'TARGET_HIT' : 'SL_HIT',
                'outcome' => $trade[4],
                'pnl_inr' => $trade[5],
                'rr_achieved' => $trade[4] === 'win' ? 2.0 : -1.0,
                'mode' => 'paper',
            ]);
        }
        
        $totalTrades = Trade::count();
        $wins = Trade::where('outcome', 'win')->count();
        $losses = Trade::where('outcome', 'loss')->count();
        $winRate = round(($wins / $totalTrades) * 100, 1);
        $totalPnL = Trade::sum('pnl_inr');
        
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
        $this->command->info('✅ Demo data seeded successfully!');
        $this->command->info('💡 Run: php artisan trading:learn --force');
    }
}

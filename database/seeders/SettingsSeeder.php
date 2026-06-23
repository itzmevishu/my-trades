<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Trading Configuration
        Setting::setValue('capital_amount', 300000, 'decimal', 'Total trading capital in INR');
        Setting::setValue('risk_percentage', 1.0, 'decimal', 'Risk per trade as percentage of capital');
        Setting::setValue('min_claude_score', 6.0, 'decimal', 'Minimum Claude score to take trade (updated by learning engine)');
        Setting::setValue('exception_min_score', 7.5, 'decimal', 'Minimum score for exception trades');
        Setting::setValue('sl_delta_assumption', 0.5, 'decimal', 'ATM delta assumption for SL calculation');
        Setting::setValue('partial_exit_rr', 1.0, 'decimal', 'Risk-reward ratio to trigger 50% exit');
        Setting::setValue('target_rr', 2.0, 'decimal', 'Target risk-reward ratio');
        
        // Trading Mode
        Setting::setValue('paper_trade_mode', true, 'boolean', 'Paper trading mode (true) or live trading (false)');
        Setting::setValue('live_trading_enabled', false, 'boolean', 'Enable live trading toggle (unlocked after 30+ paper trades)');
        
        // Risk Limits
        Setting::setValue('max_lots', 2, 'integer', 'Maximum lots allowed per trade (safety limit)');
        Setting::setValue('min_sl_distance', 50, 'integer', 'Minimum stop loss distance in points');
        Setting::setValue('max_sl_distance', 250, 'integer', 'Maximum stop loss distance in points');
        
        // Time Windows
        Setting::setValue('trading_start_time', '11:15:00', 'string', 'Earliest time to enter trades (IST)');
        Setting::setValue('trading_end_time', '14:00:00', 'string', 'Latest time to enter new trades (IST)');
        Setting::setValue('eod_exit_time', '15:15:00', 'string', 'Hard exit time for all positions (IST)');
        
        // EMA Configuration
        Setting::setValue('ema_proximity_tolerance', 0.3, 'decimal', 'Tolerance % for "at EMA 20" check');
        Setting::setValue('swing_lookback_candles', 5, 'integer', 'Number of candles to look back for swing high/low');
        
        // Learning Engine
        Setting::setValue('learning_cycle_trigger', 10, 'integer', 'Number of trades before learning cycle triggers');
        Setting::setValue('auto_apply_learning', false, 'boolean', 'Auto-apply learning engine changes (false = manual review required)');
        
        // Paper Trade Slippage
        Setting::setValue('entry_slippage_pct', 0.2, 'decimal', 'Entry slippage % for paper trades');
        Setting::setValue('sl_slippage_pct', 1.0, 'decimal', 'SL exit slippage % for paper trades');
        Setting::setValue('eod_slippage_pct', 1.0, 'decimal', 'EOD exit slippage % for paper trades');
        
        // Monitoring
        Setting::setValue('premium_check_interval', 1, 'integer', 'Seconds between premium checks for active trades');
        Setting::setValue('use_websocket', false, 'boolean', 'Use WebSocket for real-time premium (false = polling)');
        
        // API Configuration
        Setting::setValue('fyers_client_id', '', 'string', 'Fyers API Client ID');
        Setting::setValue('fyers_secret_key', '', 'string', 'Fyers API Secret Key (encrypted)');
        Setting::setValue('claude_api_key', '', 'string', 'Claude API Key (encrypted)');
        Setting::setValue('api_retry_attempts', 3, 'integer', 'Number of retry attempts for API calls');
        Setting::setValue('api_retry_delay', 2, 'integer', 'Delay in seconds between API retries');
        
        // Acceptance Criteria Tracking
        Setting::setValue('paper_trades_completed', 0, 'integer', 'Number of paper trades completed');
        Setting::setValue('paper_win_rate', 0, 'decimal', 'Current paper trade win rate %');
        Setting::setValue('paper_avg_rr', 0, 'decimal', 'Average risk-reward achieved in paper trades');
        
        // SEBI Compliance (Effective April 1, 2026)
        Setting::setValue('sebi_compliant_mode', true, 'boolean', 'SEBI April 2026 framework enabled');
        Setting::setValue('static_ip_address', '', 'string', 'Whitelisted static IP for Fyers API');
        Setting::setValue('fyers_new_app_id', '', 'string', 'Fyers App ID (post-April 1 2026)');
        Setting::setValue('last_2fa_auth_date', '', 'string', 'Last successful 2FA authentication date');
        Setting::setValue('require_daily_2fa', true, 'boolean', 'Require daily 2FA before trading');
        Setting::setValue('max_orders_per_second', 10, 'integer', 'SEBI rate limit: max orders per second');
        Setting::setValue('use_mpp_orders', true, 'boolean', 'Use MPP instead of pure market orders');
        Setting::setValue('mpp_price_protection_pct', 5.0, 'decimal', 'MPP price protection band %');
        
        $this->command->info('✓ Default settings seeded successfully!');
        $this->command->warn('⚠ SEBI Compliance: Configure static IP and new Fyers App ID before April 1, 2026');
    }
}

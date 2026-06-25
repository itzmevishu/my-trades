<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add only MISSING trading parameters (not already in SettingsSeeder)
        $settings = [
            // Lot Size & Position Limits
            [
                'key' => 'banknifty_lot_size',
                'value' => '15',
                'type' => 'integer',
                'description' => 'Bank Nifty lot size (contracts per lot)',
            ],
            
            // NOTE: max_lots already exists in SettingsSeeder
            
            // Risk Parameters  
            [
                'key' => 'minimum_target_premium',
                'value' => '5',
                'type' => 'float',
                'description' => 'Minimum target premium in points (prevents unrealistic targets)',
            ],
            
            // NOTE: target_rr, sl_delta_assumption already exist in SettingsSeeder
            
            // Timeframe
            [
                'key' => 'trading_timeframe',
                'value' => '15m',
                'type' => 'string',
                'description' => 'Trading timeframe for candle analysis (15m, 5m, 1h, etc.)',
            ],
            [
                'key' => 'candle_lookback',
                'value' => '250',
                'type' => 'integer',
                'description' => 'Number of candles to fetch for analysis',
            ],
            
            // Scanning Frequency
            [
                'key' => 'scan_interval_minutes',
                'value' => '15',
                'type' => 'integer',
                'description' => 'How often to scan for entry signals (in minutes)',
            ],
            [
                'key' => 'monitor_interval_minutes',
                'value' => '1',
                'type' => 'integer',
                'description' => 'How often to monitor positions (in minutes)',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $keys = [
            'banknifty_lot_size',
            'minimum_target_premium',
            'trading_timeframe',
            'candle_lookback',
            'scan_interval_minutes',
            'monitor_interval_minutes',
        ];

        Setting::whereIn('key', $keys)->delete();
    }
};

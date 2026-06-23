<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class MarketCalendar extends Model
{
    protected $table = 'market_calendar';

    protected $fillable = [
        'event_date',
        'event_type',
        'description',
        'action',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    /**
     * Check if today is a high-impact day
     */
    public static function isHighImpactDay(?Carbon $date = null): bool
    {
        $date = $date ?? now();
        
        return static::where('event_date', $date->format('Y-m-d'))
            ->where('action', 'skip')
            ->exists();
    }

    /**
     * Get caution flags for today
     */
    public static function getCautionFlags(?Carbon $date = null): ?self
    {
        $date = $date ?? now();
        
        return static::where('event_date', $date->format('Y-m-d'))
            ->where('action', 'caution')
            ->first();
    }

    /**
     * Check if date is Bank Nifty expiry
     */
    public static function isExpiryDay(?Carbon $date = null): bool
    {
        $date = $date ?? now();
        
        return static::where('event_date', $date->format('Y-m-d'))
            ->where('event_type', 'expiry')
            ->exists();
    }
}

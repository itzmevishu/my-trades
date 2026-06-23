<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    protected $fillable = [
        'report_date',
        'report_type',
        'market_context',
        'setup_summary',
        'trade_outcome',
        'claude_analysis',
        'pnl_summary',
    ];

    protected $casts = [
        'report_date' => 'date',
        'pnl_summary' => 'json',
    ];

    /**
     * Scope for daily reports
     */
    public function scopeDaily($query)
    {
        return $query->where('report_type', 'daily');
    }

    /**
     * Scope for weekly reports
     */
    public function scopeWeekly($query)
    {
        return $query->where('report_type', 'weekly');
    }

    /**
     * Scope for monthly reports
     */
    public function scopeMonthly($query)
    {
        return $query->where('report_type', 'monthly');
    }
}

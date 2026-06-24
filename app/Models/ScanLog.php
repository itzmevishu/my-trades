<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ScanLog Model
 * 
 * Records every trading scan with complete decision context:
 * - Patterns detected (or not)
 * - EMA confluence status
 * - Claude scores
 * - Rejection reasons
 * 
 * Enables comprehensive reporting and system transparency
 */
class ScanLog extends Model
{
    protected $fillable = [
        'scan_date',
        'scan_time',
        'result',
        'pattern_detected',
        'pattern_direction',
        'current_price',
        'ema_20',
        'ema_100',
        'ema_200',
        'ema_confluence_count',
        'claude_score',
        'rejection_reason',
        'trade_id',
    ];

    protected $casts = [
        'scan_date' => 'date',
        'scan_time' => 'datetime:H:i:s',
        'current_price' => 'decimal:2',
        'ema_20' => 'decimal:2',
        'ema_100' => 'decimal:2',
        'ema_200' => 'decimal:2',
        'claude_score' => 'decimal:1',
    ];

    /**
     * Get the trade that was taken (if any)
     */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /**
     * Scope: Get logs for a specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('scan_date', $date);
    }

    /**
     * Scope: Get rejected scans only
     */
    public function scopeRejected($query)
    {
        return $query->whereIn('result', ['rejected_ema', 'rejected_score', 'no_pattern']);
    }

    /**
     * Scope: Get successful trades
     */
    public function scopeTradesTaken($query)
    {
        return $query->where('result', 'trade_taken');
    }
}

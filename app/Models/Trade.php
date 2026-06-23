<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trade extends Model
{
    protected $fillable = [
        'date',
        'direction',
        'instrument',
        'expiry',
        'strike',
        'entry_time',
        'exit_time',
        'entry_premium',
        'exit_premium',
        'sl_premium',
        'target_premium',
        'lots',
        'capital_at_trade',
        'candle_pattern',
        'ema_configuration',
        'htf_bias',
        'session_slot',
        'is_exception_trade',
        'claude_score',
        'claude_reasoning',
        'outcome',
        'rr_achieved',
        'pnl_points',
        'pnl_inr',
        'market_condition',
        'post_trade_analysis',
        'status',
        'exit_type',
        'partial_exit_premium',
        'partial_exit_time',
        'lots_remaining',
        'pnl_realized',
        'mode',
    ];

    protected $casts = [
        'date' => 'date',
        'expiry' => 'date',
        'entry_time' => 'datetime',
        'exit_time' => 'datetime',
        'partial_exit_time' => 'datetime',
        'entry_premium' => 'decimal:2',
        'exit_premium' => 'decimal:2',
        'sl_premium' => 'decimal:2',
        'target_premium' => 'decimal:2',
        'partial_exit_premium' => 'decimal:2',
        'capital_at_trade' => 'decimal:2',
        'claude_score' => 'decimal:2',
        'rr_achieved' => 'decimal:2',
        'pnl_points' => 'decimal:2',
        'pnl_inr' => 'decimal:2',
        'pnl_realized' => 'decimal:2',
        'is_exception_trade' => 'boolean',
        'ema_configuration' => 'json',
    ];

    /**
     * Scope for active trades
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for closed trades
     */
    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * Scope for winning trades
     */
    public function scopeWins($query)
    {
        return $query->where('outcome', 'win');
    }

    /**
     * Scope for losing trades
     */
    public function scopeLosses($query)
    {
        return $query->where('outcome', 'loss');
    }

    /**
     * Scope for paper trades
     */
    public function scopePaper($query)
    {
        return $query->where('mode', 'paper');
    }

    /**
     * Scope for live trades
     */
    public function scopeLive($query)
    {
        return $query->where('mode', 'live');
    }
}

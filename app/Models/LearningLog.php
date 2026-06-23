<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningLog extends Model
{
    protected $fillable = [
        'trigger_trade_count',
        'trades_analysed',
        'previous_config_id',
        'new_config_id',
        'changes_summary',
        'claude_full_response',
    ];

    protected $casts = [
        'changes_summary' => 'json',
    ];

    /**
     * Get the previous strategy config
     */
    public function previousConfig(): BelongsTo
    {
        return $this->belongsTo(StrategyConfig::class, 'previous_config_id');
    }

    /**
     * Get the new strategy config
     */
    public function newConfig(): BelongsTo
    {
        return $this->belongsTo(StrategyConfig::class, 'new_config_id');
    }
}

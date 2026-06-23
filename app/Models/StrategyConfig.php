<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategyConfig extends Model
{
    protected $fillable = [
        'version',
        'pattern_weights',
        'best_entry_window',
        'min_score_threshold',
        'avoid_setups',
        'learning_note',
        'trades_analysed',
        'win_rate_at_update',
        'is_active',
    ];

    protected $casts = [
        'pattern_weights' => 'json',
        'avoid_setups' => 'json',
        'min_score_threshold' => 'decimal:2',
        'win_rate_at_update' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the active configuration
     */
    public static function getActive()
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Set this config as active (deactivates others)
     */
    public function setActive()
    {
        static::where('is_active', true)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    /**
     * Learning logs that created this config
     */
    public function learningLogsAsNew(): HasMany
    {
        return $this->hasMany(LearningLog::class, 'new_config_id');
    }

    /**
     * Learning logs that used this as previous config
     */
    public function learningLogsAsPrevious(): HasMany
    {
        return $this->hasMany(LearningLog::class, 'previous_config_id');
    }
}

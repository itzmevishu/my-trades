<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualFeedback extends Model
{
    protected $table = 'manual_feedback';
    
    protected $fillable = [
        'feedback_date',
        'feedback_time',
        'trade_id',
        'scan_log_id',
        'category',
        'importance',
        'note',
        'incorporated_in_learning',
        'learning_log_id',
    ];

    protected $casts = [
        'feedback_date' => 'date',
        'incorporated_in_learning' => 'boolean',
    ];

    // Relationships
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function scanLog(): BelongsTo
    {
        return $this->belongsTo(ScanLog::class);
    }

    public function learningLog(): BelongsTo
    {
        return $this->belongsTo(LearningLog::class);
    }

    // Scopes
    public function scopeNotIncorporated($query)
    {
        return $query->where('incorporated_in_learning', false);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('feedback_date', $date);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeHighImportance($query)
    {
        return $query->where('importance', 'high');
    }
}

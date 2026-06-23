<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandleCache extends Model
{
    protected $table = 'candle_cache';

    protected $fillable = [
        'symbol',
        'timeframe',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'candle_timestamp',
    ];

    protected $casts = [
        'open' => 'decimal:2',
        'high' => 'decimal:2',
        'low' => 'decimal:2',
        'close' => 'decimal:2',
        'candle_timestamp' => 'datetime',
    ];

    /**
     * Get candles for a symbol and timeframe
     */
    public static function getCandles(string $symbol, string $timeframe, int $limit = 100)
    {
        return static::where('symbol', $symbol)
            ->where('timeframe', $timeframe)
            ->orderBy('candle_timestamp', 'desc')
            ->limit($limit)
            ->get();
    }
}

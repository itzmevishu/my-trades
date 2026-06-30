<?php

namespace App\Console\Commands;

use App\Models\CandleCache;
use Illuminate\Console\Command;

/**
 * Clear Candle Cache Command
 * 
 * Removes all cached candle data to force fresh API fetch
 * Useful when corrupted or simulated data needs to be purged
 */
class ClearCandleCacheCommand extends Command
{
    protected $signature = 'cache:clear-candles 
                            {--symbol= : Clear cache for specific symbol only}
                            {--timeframe= : Clear cache for specific timeframe only}
                            {--all : Clear all candle cache without confirmation}';
    
    protected $description = 'Clear cached candle data to force fresh fetch from Fyers API';

    public function handle()
    {
        $symbol = $this->option('symbol');
        $timeframe = $this->option('timeframe');
        $all = $this->option('all');
        
        // Build query
        $query = CandleCache::query();
        
        if ($symbol) {
            $query->where('symbol', $symbol);
        }
        
        if ($timeframe) {
            $query->where('timeframe', $timeframe);
        }
        
        // Get count before deletion
        $count = $query->count();
        
        if ($count === 0) {
            $this->info('No candle cache records found.');
            return 0;
        }
        
        // Show what will be deleted
        $this->info("Found {$count} cached candle records");
        
        if ($symbol) {
            $this->line("  Symbol: {$symbol}");
        }
        
        if ($timeframe) {
            $this->line("  Timeframe: {$timeframe}");
        }
        
        // Confirm deletion
        if (!$all) {
            if (!$this->confirm('Do you want to delete these records?', true)) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }
        
        // Delete
        $deleted = $query->delete();
        
        $this->info("✅ Successfully deleted {$deleted} cached candle records");
        $this->line('');
        $this->info('Next scan will fetch fresh data from Fyers API');
        
        return 0;
    }
}

<?php

namespace App\Filament\Resources\CandleCacheResource\Pages;

use App\Filament\Resources\CandleCacheResource;
use App\Models\CandleCache;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCandleCaches extends ListRecords
{
    protected static string $resource = CandleCacheResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('dataInfo')
                ->label('Data Retention')
                ->icon('heroicon-o-information-circle')
                ->color('info')
                ->modalHeading('Candle Cache Information')
                ->modalDescription(function () {
                    $retentionDays = setting('candle_cache_retention_days', 30);
                    $totalCandles = CandleCache::count();
                    $oldestCandle = CandleCache::orderBy('candle_timestamp', 'asc')->first();
                    $newestCandle = CandleCache::orderBy('candle_timestamp', 'desc')->first();
                    
                    $oldestDate = $oldestCandle ? $oldestCandle->candle_timestamp->format('M d, Y') : 'N/A';
                    $newestDate = $newestCandle ? $newestCandle->candle_timestamp->format('M d, Y') : 'N/A';
                    
                    return "**Retention Period:** {$retentionDays} days\n\n" .
                           "**Total Candles Stored:** " . number_format($totalCandles) . "\n\n" .
                           "**Oldest Data:** {$oldestDate}\n\n" .
                           "**Newest Data:** {$newestDate}\n\n" .
                           "*Old data is automatically cleaned up after {$retentionDays} days. " .
                           "To change retention period, add 'candle_cache_retention_days' setting in Settings.*";
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
        ];
    }
}

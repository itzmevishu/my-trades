<?php

namespace App\Filament\Resources\ScanLogResource\Pages;

use App\Filament\Resources\ScanLogResource;
use App\Models\CandleCache;
use Carbon\Carbon;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewScanLog extends ViewRecord
{
    protected static string $resource = ScanLogResource::class;
    
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Scan Overview')
                    ->schema([
                        TextEntry::make('scan_date')
                            ->label('Date')
                            ->date('M d, Y'),
                        TextEntry::make('scan_time')
                            ->label('Time')
                            ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('H:i:s')),
                        TextEntry::make('result')
                            ->label('Result')
                            ->badge()
                            ->color(fn ($state) => match($state) {
                                'trade_taken' => 'success',
                                'rejected_ema', 'rejected_score', 'no_pattern' => 'danger',
                                default => 'warning',
                            })
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state))),
                        TextEntry::make('pattern_detected')
                            ->label('Pattern')
                            ->formatStateUsing(fn ($state) => $state ? ucwords(str_replace('_', ' ', $state)) : 'None'),
                        TextEntry::make('pattern_direction')
                            ->label('Direction')
                            ->badge()
                            ->color(fn ($state) => match($state) {
                                'bullish' => 'success',
                                'bearish' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '-'),
                        TextEntry::make('current_price')
                            ->label('Price at Scan')
                            ->formatStateUsing(fn ($state) => number_format($state, 2)),
                    ])->columns(3),
                    
                Section::make('Market Analysis Chart')
                    ->schema([
                        ViewEntry::make('chart')
                            ->view('filament.components.scan-log-chart')
                            ->viewData([
                                'candles' => $this->getHistoricalCandles(),
                                'scanLog' => $this->record,
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(false),
                    
                Section::make('Technical Indicators')
                    ->schema([
                        TextEntry::make('ema_20')
                            ->label('20 EMA')
                            ->formatStateUsing(fn ($state) => number_format($state, 2)),
                        TextEntry::make('ema_100')
                            ->label('100 EMA')
                            ->formatStateUsing(fn ($state) => number_format($state, 2)),
                        TextEntry::make('ema_200')
                            ->label('200 EMA')
                            ->formatStateUsing(fn ($state) => number_format($state, 2)),
                        TextEntry::make('ema_confluence_count')
                            ->label('EMA Confluence'),
                        TextEntry::make('claude_score')
                            ->label('Claude AI Score')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state, 1) . '/10' : '-'),
                    ])->columns(3),
                    
                Section::make('Decision Analysis')
                    ->schema([
                        TextEntry::make('rejection_reason')
                            ->label('Details')
                            ->html()
                            ->formatStateUsing(fn ($state) => nl2br(e($state)))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => !empty($record->rejection_reason)),
                    
                Section::make('Related Trade')
                    ->schema([
                        TextEntry::make('trade.symbol')
                            ->label('Symbol'),
                        TextEntry::make('trade.entry_price')
                            ->label('Entry')
                            ->money('INR'),
                        TextEntry::make('trade.exit_price')
                            ->label('Exit')
                            ->money('INR'),
                        TextEntry::make('trade.profit_loss')
                            ->label('P&L')
                            ->money('INR')
                            ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                    ])
                    ->columns(4)
                    ->visible(fn ($record) => $record->trade_id !== null),
            ]);
    }
    
    /**
     * Get historical candles around the scan time
     */
    protected function getHistoricalCandles(): array
    {
        $scanDateTime = Carbon::parse($this->record->scan_date . ' ' . $this->record->scan_time);
        
        // Get 50 candles before the scan and 10 after (if available)
        $startTime = $scanDateTime->copy()->subMinutes(50 * 15); // 15-min candles
        $endTime = $scanDateTime->copy()->addMinutes(10 * 15);
        
        $candles = CandleCache::where('symbol', 'NSE:NIFTYBANK-INDEX')
            ->where('timeframe', '15')
            ->whereBetween('candle_timestamp', [$startTime, $endTime])
            ->orderBy('candle_timestamp', 'asc')
            ->get()
            ->map(function ($candle) {
                return [
                    'timestamp' => $candle->candle_timestamp->timestamp * 1000, // JS timestamp
                    'datetime' => $candle->candle_timestamp->format('Y-m-d H:i'),
                    'open' => (float) $candle->open,
                    'high' => (float) $candle->high,
                    'low' => (float) $candle->low,
                    'close' => (float) $candle->close,
                    'volume' => (int) $candle->volume,
                ];
            })
            ->toArray();
        
        return $candles;
    }
}

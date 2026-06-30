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
                            ->formatStateUsing(fn ($state) => $state ? ucwords(str_replace('_', ' ', $state)) : 'None')
                            ->placeholder('None')
                            ->color(fn ($state) => $state ? 'success' : 'gray'),
                        TextEntry::make('pattern_direction')
                            ->label('Direction')
                            ->badge()
                            ->color(fn ($state) => match($state) {
                                'bullish' => 'success',
                                'bearish' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : 'N/A')
                            ->placeholder('N/A'),
                        TextEntry::make('current_price')
                            ->label('Price at Scan')
                            ->formatStateUsing(fn ($state) => '₹ ' . number_format($state, 2))
                            ->copyable()
                            ->copyMessage('Price copied'),
                    ])
                    ->columns(3)
                    ->description(fn ($record) => 
                        'Scan executed at ' . Carbon::parse($record->scan_date . ' ' . $record->scan_time)->format('M d, Y H:i:s')
                    ),
                    
                Section::make('Market Analysis Chart')
                    ->schema([
                        ViewEntry::make('chart')
                            ->view('filament.components.scan-log-chart')
                            ->viewData([
                                'candles' => $this->getHistoricalCandles(),
                                'scanLog' => $this->record,
                            ]),
                    ])
                    ->description(fn () => empty($this->getHistoricalCandles()) 
                        ? '📊 Chart unavailable - candle data not in cache' 
                        : 'Interactive candlestick chart with EMA indicators'
                    )
                    ->collapsible()
                    ->collapsed(fn () => empty($this->getHistoricalCandles())),
                    
                Section::make('Technical Indicators')
                    ->schema([
                        TextEntry::make('ema_20')
                            ->label('20 EMA')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state, 2) : 'N/A')
                            ->placeholder('N/A'),
                        TextEntry::make('ema_100')
                            ->label('100 EMA')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state, 2) : 'N/A')
                            ->placeholder('N/A'),
                        TextEntry::make('ema_200')
                            ->label('200 EMA')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state, 2) : 'N/A')
                            ->placeholder('N/A'),
                        TextEntry::make('ema_confluence_count')
                            ->label('EMA Confluence')
                            ->formatStateUsing(fn ($state) => $state ?? '0'),
                        TextEntry::make('claude_score')
                            ->label('Claude AI Score')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state, 1) . '/10' : 'Not Scored')
                            ->placeholder('Not Scored'),
                    ])
                    ->columns(3)
                    ->description('EMA values at scan time'),
                    
                Section::make('Decision Analysis')
                    ->schema([
                        TextEntry::make('rejection_reason')
                            ->label('Why This Scan Failed')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return 'No rejection reason available';
                                }
                                
                                // Format rejection reasons for better readability
                                $lines = explode("\n", $state);
                                $formatted = [];
                                
                                foreach ($lines as $line) {
                                    $line = trim($line);
                                    if (empty($line)) continue;
                                    
                                    // Add bullet points for better visual hierarchy
                                    if (str_contains($line, ':')) {
                                        $formatted[] = '• ' . $line;
                                    } else {
                                        $formatted[] = $line;
                                    }
                                }
                                
                                return '<div class="space-y-1">' . implode('<br>', $formatted) . '</div>';
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->description('Detailed breakdown of why no trade was taken')
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
        // Properly combine date and time fields
        $scanDateTime = $this->record->scan_date->copy()
            ->setTimeFromTimeString(
                Carbon::parse($this->record->scan_time)->format('H:i:s')
            );
        
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

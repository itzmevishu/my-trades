<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CandleCacheResource\Pages;
use App\Filament\Resources\CandleCacheResource\RelationManagers;
use App\Models\CandleCache;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CandleCacheResource extends Resource
{
    protected static ?string $model = CandleCache::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    
    protected static ?string $navigationGroup = 'Data';
    
    protected static ?string $navigationLabel = 'Price History';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Candle Data')
                    ->description('Historical OHLC price data from Fyers API')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('symbol')
                                    ->disabled(),
                                Forms\Components\TextInput::make('timeframe')
                                    ->disabled(),
                                Forms\Components\DateTimePicker::make('candle_timestamp')
                                    ->label('Time')
                                    ->disabled(),
                            ]),
                        
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('open')
                                    ->label('Open ₹')
                                    ->disabled(),
                                Forms\Components\TextInput::make('high')
                                    ->label('High ₹')
                                    ->disabled(),
                                Forms\Components\TextInput::make('low')
                                    ->label('Low ₹')
                                    ->disabled(),
                                Forms\Components\TextInput::make('close')
                                    ->label('Close ₹')
                                    ->disabled(),
                            ]),
                        
                        Forms\Components\TextInput::make('volume')
                            ->label('Volume')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('candle_timestamp')
                    ->label('Date & Time')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('timeframe')
                    ->label('TF')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        '15m' => 'success',
                        '1D' => 'info',
                        '1W' => 'warning',
                        '1M' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('open')
                    ->label('Open')
                    ->money('INR')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('high')
                    ->label('High')
                    ->money('INR')
                    ->color('success')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('low')
                    ->label('Low')
                    ->money('INR')
                    ->color('danger')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('close')
                    ->label('Close')
                    ->money('INR')
                    ->weight('bold')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('volume')
                    ->label('Volume')
                    ->formatStateUsing(fn ($state) => number_format($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('candle_change')
                    ->label('Change')
                    ->formatStateUsing(function ($record) {
                        $change = $record->close - $record->open;
                        $pct = ($change / $record->open) * 100;
                        return ($change >= 0 ? '+' : '') . number_format($change, 2) . ' (' . number_format($pct, 2) . '%)';
                    })
                    ->color(function ($record) {
                        return $record->close >= $record->open ? 'success' : 'danger';
                    })
                    ->sortable(false),
            ])
            ->defaultSort('candle_timestamp', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('timeframe')
                    ->options([
                        '15m' => '15 Minutes',
                        '1D' => 'Daily',
                        '1W' => 'Weekly',
                        '1M' => 'Monthly',
                    ])
                    ->default('15m'),
                
                Tables\Filters\Filter::make('candle_timestamp')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('candle_timestamp', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('candle_timestamp', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'From ' . \Carbon\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Until ' . \Carbon\Carbon::parse($data['until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Clear Old Data')
                        ->modalHeading('Clear old candle data?')
                        ->modalDescription('This will permanently delete selected historical price data. Use to free up space.')
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCandleCaches::route('/'),
            'view' => Pages\ViewCandleCache::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Candles are fetched from API, not created manually
    }

    public static function canEdit($record): bool
    {
        return false; // Historical price data is read-only
    }
}

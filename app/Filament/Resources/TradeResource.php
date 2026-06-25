<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TradeResource\Pages;
use App\Models\Trade;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Builder;

class TradeResource extends Resource
{
    protected static ?string $model = Trade::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    
    protected static ?string $navigationLabel = 'Trades';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Trade Details')
                    ->schema([
                        Forms\Components\DatePicker::make('date')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('direction')
                            ->options([
                                'long' => 'Long (Call)',
                                'short' => 'Short (Put)',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('strike')
                            ->label('Strike (Index Level)')
                            ->required()
                            ->numeric()
                            ->helperText('BankNifty strike (e.g., 58200, 58300)'),
                        Forms\Components\TextInput::make('lots')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(2),
                    ])->columns(4),
                    
                Forms\Components\Section::make('Entry & Exit')
                    ->schema([
                        Forms\Components\TextInput::make('entry_premium')
                            ->numeric()
                            ->suffix('₹'),
                        Forms\Components\TextInput::make('exit_premium')
                            ->numeric()
                            ->suffix('₹'),
                        Forms\Components\TextInput::make('sl_premium')
                            ->numeric()
                            ->suffix('₹'),
                        Forms\Components\TextInput::make('target_premium')
                            ->numeric()
                            ->suffix('₹'),
                    ])->columns(4),
                    
                Forms\Components\Section::make('Pattern Analysis')
                    ->schema([
                        Forms\Components\Select::make('candle_pattern')
                            ->options([
                                'bullish_engulfing' => 'Bullish Engulfing',
                                'bearish_engulfing' => 'Bearish Engulfing',
                                'bullish_pinbar' => 'Bullish Pinbar',
                                'bearish_pinbar' => 'Bearish Pinbar',
                                'inside_bar_breakout' => 'Inside Bar Breakout',
                            ]),
                        Forms\Components\TextInput::make('claude_score')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10)
                            ->suffix('/10'),
                        Forms\Components\Select::make('htf_bias')
                            ->options([
                                'bullish' => 'Bullish',
                                'bearish' => 'Bearish',
                                'neutral' => 'Neutral',
                            ]),
                    ])->columns(3),
                    
                Forms\Components\Section::make('Results')
                    ->schema([
                        Forms\Components\Select::make('outcome')
                            ->options([
                                'win' => 'Win',
                                'loss' => 'Loss',
                                'breakeven' => 'Breakeven',
                            ]),
                        Forms\Components\TextInput::make('pnl_inr')
                            ->numeric()
                            ->prefix('₹'),
                        Forms\Components\TextInput::make('rr_achieved')
                            ->numeric()
                            ->label('R:R Achieved'),
                        Forms\Components\Select::make('exit_type')
                            ->options([
                                'TARGET_HIT' => 'Target Hit',
                                'SL_HIT' => 'Stop Loss Hit',
                                'PARTIAL_EXIT' => 'Partial Exit',
                                'EOD_EXIT' => 'End of Day',
                            ]),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date('M d, Y')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\BadgeColumn::make('direction')
                    ->colors([
                        'success' => 'long',
                        'danger' => 'short',
                    ])
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                    
                Tables\Columns\TextColumn::make('candle_pattern')
                    ->label('Pattern')
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucwords($state, '_')))
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('strike')
                    ->label('Strike')
                    ->formatStateUsing(fn ($state) => number_format($state, 0))
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('lots')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('entry_premium')
                    ->label('Entry')
                    ->money('INR', divideBy: 1)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('exit_premium')
                    ->label('Exit')
                    ->money('INR', divideBy: 1)
                    ->sortable(),
                    
                Tables\Columns\BadgeColumn::make('outcome')
                    ->colors([
                        'success' => 'win',
                        'danger' => 'loss',
                        'warning' => 'breakeven',
                    ])
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                    
                Tables\Columns\TextColumn::make('pnl_inr')
                    ->label('P&L')
                    ->money('INR', divideBy: 1)
                    ->sortable()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('rr_achieved')
                    ->label('R:R')
                    ->formatStateUsing(fn ($state) => number_format($state, 2))
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('claude_score')
                    ->label('Score')
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        $state >= 8 => 'success',
                        $state >= 6 => 'info',
                        $state >= 4 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => $state . '/10')
                    ->sortable(),
                    
                Tables\Columns\BadgeColumn::make('mode')
                    ->colors([
                        'warning' => 'paper',
                        'success' => 'live',
                    ])
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('outcome')
                    ->options([
                        'win' => 'Wins',
                        'loss' => 'Losses',
                        'breakeven' => 'Breakeven',
                    ]),
                    
                Tables\Filters\SelectFilter::make('candle_pattern')
                    ->label('Pattern')
                    ->options([
                        'bullish_engulfing' => 'Bullish Engulfing',
                        'bearish_engulfing' => 'Bearish Engulfing',
                        'bullish_pinbar' => 'Bullish Pinbar',
                        'bearish_pinbar' => 'Bearish Pinbar',
                        'inside_bar_breakout' => 'Inside Bar Breakout',
                    ]),
                    
                Tables\Filters\SelectFilter::make('direction')
                    ->options([
                        'long' => 'Long',
                        'short' => 'Short',
                    ]),
                    
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrades::route('/'),
            'create' => Pages\CreateTrade::route('/create'),
            'edit' => Pages\EditTrade::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}

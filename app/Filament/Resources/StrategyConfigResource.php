<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StrategyConfigResource\Pages;
use App\Models\StrategyConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StrategyConfigResource extends Resource
{
    protected static ?string $model = StrategyConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static ?string $navigationLabel = 'Strategy Evolution';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Strategy Version')
                    ->schema([
                        Forms\Components\TextInput::make('version')
                            ->required()
                            ->numeric()
                            ->disabled(),
                        Forms\Components\Toggle::make('is_active')
                            ->required()
                            ->label('Active Strategy'),
                        Forms\Components\TextInput::make('trades_analysed')
                            ->numeric()
                            ->label('Trades Analyzed'),
                        Forms\Components\TextInput::make('win_rate_at_update')
                            ->numeric()
                            ->suffix('%')
                            ->label('Win Rate'),
                    ])->columns(4),
                    
                Forms\Components\Section::make('Pattern Weights')
                    ->schema([
                        Forms\Components\KeyValue::make('pattern_weights')
                            ->label('Pattern Weights')
                            ->keyLabel('Pattern')
                            ->valueLabel('Weight')
                            ->required(),
                    ]),
                    
                Forms\Components\Section::make('Avoid List')
                    ->schema([
                        Forms\Components\TagsInput::make('avoid_setups')
                            ->label('Patterns to Avoid')
                            ->placeholder('Add pattern to avoid list'),
                    ]),
                    
                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('best_entry_window')
                            ->label('Best Entry Window'),
                        Forms\Components\TextInput::make('min_score_threshold')
                            ->numeric()
                            ->label('Minimum Score Threshold'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Learning Notes')
                    ->schema([
                        Forms\Components\Textarea::make('learning_note')
                            ->label('Learning Note')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => 'v' . $state)
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                    
                Tables\Columns\TextColumn::make('trades_analysed')
                    ->label('Trades')
                    ->badge()
                    ->color('warning'),
                    
                Tables\Columns\TextColumn::make('win_rate_at_update')
                    ->label('Win Rate')
                    ->formatStateUsing(fn ($state) => number_format($state, 1) . '%')
                    ->color(fn ($state) => match(true) {
                        $state >= 70 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->weight('bold')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('pattern_weights')
                    ->label('Active Patterns')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) : 0)
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('avoid_setups')
                    ->label('Avoid List')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) : 0)
                    ->badge()
                    ->color('danger'),
                    
                Tables\Columns\TextColumn::make('min_score_threshold')
                    ->label('Min Score')
                    ->formatStateUsing(fn ($state) => $state . '/10')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('version', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Strategy')
                    ->boolean()
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for strategies
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStrategyConfigs::route('/'),
            'view' => Pages\ViewStrategyConfig::route('/{record}'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return 'v' . (static::getModel()::max('version') ?? '1');
    }
    
    public static function canCreate(): bool
    {
        return false; // Strategies are created by learning engine only
    }
    
    public static function canDelete($record): bool
    {
        return false; // Never delete strategies (audit trail)
    }
}

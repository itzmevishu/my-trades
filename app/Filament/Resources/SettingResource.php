<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Colors\Color;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Setting Information')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?Setting $record) => $record !== null) // Can't change key after creation
                            ->helperText('Unique identifier for this setting'),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('Human-readable description of what this setting controls'),

                        Forms\Components\Select::make('type')
                            ->options([
                                'string' => 'Text',
                                'integer' => 'Integer',
                                'decimal' => 'Decimal',
                                'boolean' => 'Boolean (Yes/No)',
                                'json' => 'JSON',
                            ])
                            ->required()
                            ->default('string')
                            ->reactive()
                            ->helperText('Data type of the value'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Value')
                    ->schema([
                        // Boolean field
                        Forms\Components\Toggle::make('value')
                            ->label('Enabled')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'boolean')
                            ->afterStateHydrated(function ($component, $state) {
                                // Convert database string ('1'/'0') to boolean for the toggle
                                $boolValue = in_array($state, ['1', 1, true, 'true', 'yes', 'on'], true);
                                $component->state($boolValue);
                            })
                            ->dehydrateStateUsing(fn ($state) => $state ? '1' : '0')
                            ->helperText(function (Forms\Get $get) {
                                $key = $get('key');
                                return match($key) {
                                    'paper_trade_mode' => '🟢 ON = Paper Trading (Safe) | 🔴 OFF = Would use live mode (if enabled)',
                                    'live_trading_enabled' => '🔴 Enable with EXTREME CAUTION! Unlocks real money trading.',
                                    default => 'Toggle on/off'
                                };
                            }),

                        // Integer field
                        Forms\Components\TextInput::make('value')
                            ->label('Value')
                            ->numeric()
                            ->visible(fn (Forms\Get $get) => $get('type') === 'integer')
                            ->helperText(function (Forms\Get $get) {
                                $key = $get('key');
                                return match($key) {
                                    'max_lots' => 'Maximum lots per trade (safety limit)',
                                    'learning_cycle_trigger' => 'Trades before learning cycle runs',
                                    default => 'Enter a whole number'
                                };
                            }),

                        // Decimal field
                        Forms\Components\TextInput::make('value')
                            ->label('Value')
                            ->numeric()
                            ->step(0.01)
                            ->visible(fn (Forms\Get $get) => $get('type') === 'decimal')
                            ->helperText(function (Forms\Get $get) {
                                $key = $get('key');
                                return match($key) {
                                    'capital_amount' => 'Total trading capital in ₹',
                                    'risk_percentage' => 'Risk per trade as % of capital (e.g., 1.0 = 1%)',
                                    'min_claude_score' => 'Minimum AI score to take trade (0-10)',
                                    'target_rr' => 'Target risk:reward ratio',
                                    default => 'Enter a decimal number'
                                };
                            }),

                        // String field
                        Forms\Components\TextInput::make('value')
                            ->label('Value')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['string', null]))
                            ->helperText('Enter text value'),

                        // JSON field
                        Forms\Components\Textarea::make('value')
                            ->label('JSON Value')
                            ->rows(6)
                            ->visible(fn (Forms\Get $get) => $get('type') === 'json')
                            ->helperText('Enter valid JSON'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Setting Key')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->formatStateUsing(fn (string $state) => str($state)->replace('_', ' ')->title()),

                Tables\Columns\TextColumn::make('value')
                    ->label('Current Value')
                    ->limit(50)
                    ->formatStateUsing(function ($state, Setting $record) {
                        return match($record->type) {
                            'boolean' => filter_var($state, FILTER_VALIDATE_BOOLEAN) ? '✅ Yes' : '❌ No',
                            'decimal' => is_numeric($state) ? number_format((float)$state, 2) : $state,
                            'integer' => is_numeric($state) ? number_format((int)$state) : $state,
                            default => $state,
                        };
                    })
                    ->badge()
                    ->color(function ($state, Setting $record) {
                        if ($record->type === 'boolean') {
                            $boolValue = filter_var($state, FILTER_VALIDATE_BOOLEAN);
                            // Green for safe settings, red for dangerous ones
                            return match($record->key) {
                                'paper_trade_mode' => $boolValue ? 'success' : 'danger',
                                'live_trading_enabled' => $boolValue ? 'danger' : 'success',
                                default => $boolValue ? 'success' : 'gray',
                            };
                        }
                        return 'gray';
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'warning' => 'boolean',
                        'info' => 'integer',
                        'success' => 'decimal',
                        'gray' => 'string',
                        'danger' => 'json',
                    ]),

                Tables\Columns\TextColumn::make('description')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('key', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'string' => 'String',
                        'integer' => 'Integer',
                        'decimal' => 'Decimal',
                        'boolean' => 'Boolean',
                        'json' => 'JSON',
                    ]),
                Tables\Filters\Filter::make('trading')
                    ->label('Trading Settings')
                    ->query(fn ($query) => $query->where('key', 'like', '%trade%')
                        ->orWhere('key', 'like', '%capital%')
                        ->orWhere('key', 'like', '%risk%')),
                Tables\Filters\Filter::make('critical')
                    ->label('Critical Settings')
                    ->query(fn ($query) => $query->whereIn('key', [
                        'paper_trade_mode',
                        'live_trading_enabled',
                        'capital_amount',
                        'risk_percentage',
                        'max_lots',
                    ])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Setting?')
                    ->modalDescription('Are you sure? This may break the system if required by code.')
                    ->visible(fn (Setting $record) => !in_array($record->key, [
                        'paper_trade_mode',
                        'live_trading_enabled',
                        'capital_amount',
                        'risk_percentage',
                    ])), // Prevent deletion of critical settings
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        // Show warning if live trading is enabled
        if (Setting::getValue('live_trading_enabled', false)) {
            return '⚠️ LIVE';
        }
        
        // Show paper mode indicator
        if (Setting::getValue('paper_trade_mode', true)) {
            return '📄 Paper';
        }
        
        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        if (Setting::getValue('live_trading_enabled', false)) {
            return 'danger';
        }
        return 'success';
    }
}

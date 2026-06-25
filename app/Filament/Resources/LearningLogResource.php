<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LearningLogResource\Pages;
use App\Filament\Resources\LearningLogResource\RelationManagers;
use App\Models\LearningLog;
use App\Services\Learning\LearningEngine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LearningLogResource extends Resource
{
    protected static ?string $model = LearningLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    
    protected static ?string $navigationGroup = 'Learning';
    
    protected static ?string $navigationLabel = 'Learning Cycles';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cycle Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('trigger_trade_count')
                                    ->label('Triggered After Trades')
                                    ->numeric()
                                    ->disabled(),
                                Forms\Components\TextInput::make('trades_analysed')
                                    ->label('Trades Analysed')
                                    ->numeric()
                                    ->disabled(),
                            ]),
                    ]),
                
                Forms\Components\Section::make('Strategy Changes')
                    ->schema([
                        Forms\Components\KeyValue::make('changes_summary')
                            ->label('Changes Applied')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
                
                Forms\Components\Section::make('AI Analysis')
                    ->schema([
                        Forms\Components\Textarea::make('claude_full_response')
                            ->label('Claude AI Insights')
                            ->rows(12)
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Cycle #')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('trigger_trade_count')
                    ->label('After Trades')
                    ->numeric()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('trades_analysed')
                    ->label('Analysed')
                    ->numeric()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('changes_summary')
                    ->label('Changes')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return count($state) . ' changes';
                        }
                        return '-';
                    })
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListLearningLogs::route('/'),
            'view' => Pages\ViewLearningLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Learning cycles are triggered automatically, not created manually
    }

    public static function canEdit($record): bool
    {
        return false; // Learning cycles are read-only
    }
}

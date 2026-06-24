<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScanLogResource\Pages;
use App\Filament\Resources\ScanLogResource\RelationManagers;
use App\Models\ScanLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ScanLogResource extends Resource
{
    protected static ?string $model = ScanLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    
    protected static ?string $navigationLabel = 'Scan Logs';
    
    protected static ?string $navigationGroup = 'Reports';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('scan_date')->required(),
                Forms\Components\TimePicker::make('scan_time')->required(),
                Forms\Components\Select::make('result')
                    ->options([
                        'no_pattern' => 'No Pattern',
                        'rejected_ema' => 'Rejected - EMA',
                        'rejected_score' => 'Rejected - Score',
                        'trade_taken' => 'Trade Taken',
                        'already_traded' => 'Already Traded',
                        'outside_window' => 'Outside Window',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('pattern_detected'),
                Forms\Components\TextInput::make('current_price')->numeric(),
                Forms\Components\TextInput::make('ema_20')->numeric(),
                Forms\Components\TextInput::make('ema_100')->numeric(),
                Forms\Components\TextInput::make('ema_200')->numeric(),
                Forms\Components\TextInput::make('ema_confluence_count')->numeric(),
                Forms\Components\TextInput::make('claude_score')->numeric(),
                Forms\Components\Textarea::make('rejection_reason')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('scan_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('scan_time')
                    ->time('H:i')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('result')
                    ->colors([
                        'success' => 'trade_taken',
                        'danger' => fn ($state) => in_array($state, ['rejected_ema', 'rejected_score', 'no_pattern']),
                        'warning' => fn ($state) => in_array($state, ['already_traded', 'outside_window']),
                    ])
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state))),
                Tables\Columns\TextColumn::make('pattern_detected')
                    ->formatStateUsing(fn ($state) => $state ? ucwords(str_replace('_', ' ', $state)) : '-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ema_confluence_count')
                    ->label('EMA Conf.')
                    ->sortable(),
                Tables\Columns\TextColumn::make('claude_score')
                    ->label('Claude')
                    ->numeric(1)
                    ->sortable(),
                Tables\Columns\TextColumn::make('rejection_reason')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->rejection_reason)
                    ->searchable(),
            ])
            ->defaultSort('scan_date', 'desc')
            ->defaultSort('scan_time', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('result')
                    ->options([
                        'no_pattern' => 'No Pattern',
                        'rejected_ema' => 'Rejected - EMA',
                        'rejected_score' => 'Rejected - Score',
                        'trade_taken' => 'Trade Taken',
                        'already_traded' => 'Already Traded',
                        'outside_window' => 'Outside Window',
                    ]),
                Tables\Filters\Filter::make('scan_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('scan_date', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('scan_date', '<=', $date),
                            );
                    }),
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
            'index' => Pages\ListScanLogs::route('/'),
            'view' => Pages\ViewScanLog::route('/{record}'),
        ];
    }
    
    public static function canCreate(): bool
    {
        return false; // Scan logs are system-generated only
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyReportResource\Pages;
use App\Filament\Resources\DailyReportResource\RelationManagers;
use App\Models\DailyReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DailyReportResource extends Resource
{
    protected static ?string $model = DailyReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'Reports';
    
    protected static ?string $navigationGroup = 'Reports';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('report_date')
                    ->required(),
                Forms\Components\Select::make('report_type')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('market_context')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('setup_summary')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('trade_outcome')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('claude_analysis')
                    ->rows(10)
                    ->columnSpanFull(),
                Forms\Components\KeyValue::make('pnl_summary')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('report_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('report_type')
                    ->colors([
                        'primary' => 'daily',
                        'success' => 'weekly',
                        'warning' => 'monthly',
                    ]),
                Tables\Columns\TextColumn::make('trade_outcome')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('pnl_summary.total_pnl')
                    ->label('Total P&L')
                    ->money('INR')
                    ->sortable()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('pnl_summary.win_rate')
                    ->label('Win Rate')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('report_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('report_type')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                    ]),
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
            'index' => Pages\ListDailyReports::route('/'),
            'view' => Pages\ViewDailyReport::route('/{record}'),
        ];
    }
    
    public static function canCreate(): bool
    {
        return false; // Reports are system-generated
    }
}

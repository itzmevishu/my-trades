<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManualFeedbackResource\Pages;
use App\Filament\Resources\ManualFeedbackResource\RelationManagers;
use App\Models\ManualFeedback;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ManualFeedbackResource extends Resource
{
    protected static ?string $model = ManualFeedback::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';
    
    protected static ?string $navigationGroup = 'Learning';
    
    protected static ?string $navigationLabel = 'My Notes';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Add Your Observation')
                    ->description('Your insights help the AI learn faster and better')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('feedback_date')
                                    ->label('Date')
                                    ->default(now())
                                    ->required(),
                                Forms\Components\TimePicker::make('feedback_time')
                                    ->label('Time')
                                    ->default(now())
                                    ->seconds(false)
                                    ->required(),
                            ]),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('category')
                                    ->options([
                                        'pattern' => 'Pattern Recognition',
                                        'market' => 'Market Condition',
                                        'timing' => 'Entry/Exit Timing',
                                        'exit' => 'Exit Management',
                                        'risk' => 'Risk Management',
                                        'general' => 'General Observation',
                                    ])
                                    ->default('general')
                                    ->required(),
                                
                                Forms\Components\Select::make('importance')
                                    ->options([
                                        'low' => 'Low - Minor observation',
                                        'medium' => 'Medium - Worth noting',
                                        'high' => 'High - Critical insight',
                                    ])
                                    ->default('medium')
                                    ->required(),
                            ]),
                        
                        Forms\Components\Textarea::make('note')
                            ->label('Your Note')
                            ->placeholder('E.g., "Inside bar breakout failed during first hour volatility - avoid 9:15-10:00 slot"')
                            ->rows(4)
                            ->required()
                            ->columnSpanFull(),
                    ]),
                
                Forms\Components\Section::make('Link to Trade/Scan (Optional)')
                    ->description('Connect your note to a specific trade or scan for context')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('trade_id')
                                    ->label('Related Trade')
                                    ->relationship('trade', 'id')
                                    ->searchable()
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "#{$record->id} - {$record->direction} {$record->option_type} @ ₹{$record->entry_premium}"),
                                
                                Forms\Components\Select::make('scan_log_id')
                                    ->label('Related Scan')
                                    ->relationship('scanLog', 'id')
                                    ->searchable()
                                    ->preload()
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "#{$record->id} - {$record->pattern_detected} ({$record->result})"),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('feedback_date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('category')
                    ->colors([
                        'primary' => 'pattern',
                        'success' => 'market',
                        'warning' => 'timing',
                        'danger' => 'exit',
                        'info' => 'risk',
                        'secondary' => 'general',
                    ])
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('importance')
                    ->colors([
                        'gray' => 'low',
                        'warning' => 'medium',
                        'danger' => 'high',
                    ])
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('note')
                    ->label('Your Note')
                    ->limit(60)
                    ->searchable()
                    ->wrap(),
                
                Tables\Columns\IconColumn::make('incorporated_in_learning')
                    ->label('Learned')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray'),
                
                Tables\Columns\TextColumn::make('learningLog.id')
                    ->label('Learning Log')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '-')
                    ->url(fn ($record) => $record->learning_log_id 
                        ? route('filament.admin.resources.learning-logs.view', $record->learning_log_id) 
                        : null),
            ])
            ->defaultSort('feedback_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'pattern' => 'Pattern',
                        'market' => 'Market',
                        'timing' => 'Timing',
                        'exit' => 'Exit',
                        'risk' => 'Risk',
                        'general' => 'General',
                    ]),
                
                Tables\Filters\SelectFilter::make('importance')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ]),
                
                Tables\Filters\TernaryFilter::make('incorporated_in_learning')
                    ->label('Learning Status')
                    ->placeholder('All notes')
                    ->trueLabel('Already learned')
                    ->falseLabel('Pending learning'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListManualFeedback::route('/'),
            'create' => Pages\CreateManualFeedback::route('/create'),
            'view' => Pages\ViewManualFeedback::route('/{record}'),
            'edit' => Pages\EditManualFeedback::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\LearningLogResource\Pages;

use App\Filament\Resources\LearningLogResource;
use App\Services\Learning\LearningEngine;
use App\Models\Trade;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLearningLogs extends ListRecords
{
    protected static string $resource = LearningLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('triggerLearning')
                ->label('Trigger Learning Now')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Trigger Learning Cycle')
                ->modalDescription('This will analyze recent trades and your manual notes to update the strategy. Are you sure?')
                ->modalSubmitActionLabel('Yes, Trigger Learning')
                ->action(function () {
                    try {
                        $engine = new LearningEngine();
                        
                        // Check if there are enough closed trades
                        $closedTrades = Trade::where('status', 'closed')->count();
                        
                        if ($closedTrades < 5) {
                            Notification::make()
                                ->title('Not enough trades')
                                ->body("Need at least 5 closed trades to trigger learning. Currently: {$closedTrades}")
                                ->warning()
                                ->send();
                            return;
                        }
                        
                        // Execute learning cycle
                        $result = $engine->executeLearningCycle();
                        
                        if (empty($result)) {
                            Notification::make()
                                ->title('Learning cycle skipped')
                                ->body('Not enough data available.')
                                ->warning()
                                ->send();
                            return;
                        }
                        
                        Notification::make()
                            ->title('Learning cycle completed!')
                            ->body('Strategy has been updated based on recent performance and your notes.')
                            ->success()
                            ->send();
                        
                        // Refresh the table
                        $this->dispatch('refreshLearningLogs');
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Learning cycle failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            
            Actions\Action::make('viewStats')
                ->label('Learning Stats')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->url(function () {
                    $closedTrades = Trade::where('status', 'closed')->count();
                    $pendingNotes = \App\Models\ManualFeedback::notIncorporated()->count();
                    
                    return '#';  // Could link to a stats page
                })
                ->badge(function () {
                    return \App\Models\ManualFeedback::notIncorporated()->count();
                })
                ->badgeColor('warning')
                ->modalContent(function () {
                    $closedTrades = Trade::where('status', 'closed')->count();
                    $pendingNotes = \App\Models\ManualFeedback::notIncorporated()->count();
                    $totalCycles = \App\Models\LearningLog::count();
                    
                    return view('filament.pages.learning-stats', [
                        'closedTrades' => $closedTrades,
                        'pendingNotes' => $pendingNotes,
                        'totalCycles' => $totalCycles,
                    ]);
                }),
        ];
    }
}


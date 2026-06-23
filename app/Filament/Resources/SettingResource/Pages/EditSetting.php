<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\SettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => !in_array($this->record->key, [
                    'paper_trade_mode',
                    'live_trading_enabled',
                    'capital_amount',
                    'risk_percentage',
                ]))
                ->requiresConfirmation(),
        ];
    }

    public function getTitle(): string
    {
        return 'Edit: ' . str($this->record->key)->replace('_', ' ')->title();
    }

    protected function getSavedNotification(): ?Notification
    {
        $key = $this->record->key;
        
        // Special warnings for critical settings
        if ($key === 'live_trading_enabled' && $this->record->value === '1') {
            return Notification::make()
                ->warning()
                ->title('⚠️ LIVE TRADING ENABLED!')
                ->body('Real money trading is now possible. Ensure all safeguards are in place.')
                ->persistent()
                ->send();
        }
        
        if ($key === 'paper_trade_mode' && $this->record->value === '0') {
            return Notification::make()
                ->warning()
                ->title('⚠️ Paper Mode Disabled')
                ->body('System will attempt live trading if enabled. Verify Fyers API connection.')
                ->send();
        }

        return Notification::make()
            ->success()
            ->title('Setting Updated')
            ->body('Changes will take effect immediately.')
            ->send();
    }

    protected function beforeSave(): void
    {
        $key = $this->record->key;
        
        // Validate critical settings
        if ($key === 'live_trading_enabled' && $this->data['value'] === '1') {
            $paperTradesCompleted = (int) setting('paper_trades_completed', 0);
            
            if ($paperTradesCompleted < 30) {
                Notification::make()
                    ->danger()
                    ->title('Cannot Enable Live Trading')
                    ->body("Complete at least 30 paper trades first. Current: {$paperTradesCompleted}")
                    ->persistent()
                    ->send();
                
                $this->halt();
            }
            
            // Check if Fyers API is configured
            $fyersClientId = setting('fyers_client_id', '');
            if (empty($fyersClientId)) {
                Notification::make()
                    ->warning()
                    ->title('Fyers API Not Configured')
                    ->body('Add Fyers API credentials before enabling live trading.')
                    ->persistent()
                    ->send();
                
                $this->halt();
            }
        }
    }
}

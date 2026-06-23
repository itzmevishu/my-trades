<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\SettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'System Settings';
    }

    public function getHeading(): string
    {
        return 'System Settings';
    }

    public function getSubheading(): ?string
    {
        $paperMode = setting('paper_trade_mode', true);
        $liveEnabled = setting('live_trading_enabled', false);
        
        if ($liveEnabled && !$paperMode) {
            return '⚠️ LIVE TRADING MODE ACTIVE - Real money at risk!';
        } elseif (!$paperMode && !$liveEnabled) {
            return '⚠️ Paper mode OFF but live trading is locked. System cannot trade.';
        } elseif ($paperMode) {
            return '✅ Paper Trading Mode - Safe simulation environment';
        }
        
        return 'Configure application settings and trading parameters';
    }
}

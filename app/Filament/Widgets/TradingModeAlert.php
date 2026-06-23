<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Setting;

class TradingModeAlert extends Widget
{
    protected static string $view = 'filament.widgets.trading-mode-alert';

    protected static ?int $sort = -1; // Show at top

    public function getData(): array
    {
        $paperMode = Setting::getValue('paper_trade_mode', true);
        $liveEnabled = Setting::getValue('live_trading_enabled', false);
        $paperTrades = (int) Setting::getValue('paper_trades_completed', 0);
        $capital = Setting::getValue('capital_amount', 300000);
        
        return [
            'paper_mode' => $paperMode,
            'live_enabled' => $liveEnabled,
            'paper_trades_completed' => $paperTrades,
            'capital' => $capital,
            'is_safe' => $paperMode || !$liveEnabled,
            'message' => $this->getMessage($paperMode, $liveEnabled, $paperTrades),
            'alert_type' => $this->getAlertType($paperMode, $liveEnabled),
        ];
    }

    private function getMessage(bool $paperMode, bool $liveEnabled, int $paperTrades): string
    {
        if ($liveEnabled && !$paperMode) {
            return "🔴 LIVE TRADING ACTIVE - Real money at risk! Paper trades completed: {$paperTrades}";
        }
        
        if (!$paperMode && !$liveEnabled) {
            return "⚠️ Paper mode OFF but live trading locked. System cannot trade until settings fixed.";
        }
        
        if ($paperMode) {
            $remaining = max(0, 30 - $paperTrades);
            if ($remaining > 0) {
                return "✅ Paper Trading Mode - Safe simulation. Complete {$remaining} more trades to unlock live trading.";
            }
            return "✅ Paper Trading Mode - Safe simulation. Ready to enable live trading when you're confident.";
        }
        
        return "System configuration unclear. Check settings.";
    }

    private function getAlertType(bool $paperMode, bool $liveEnabled): string
    {
        if ($liveEnabled && !$paperMode) {
            return 'danger';
        }
        
        if (!$paperMode && !$liveEnabled) {
            return 'warning';
        }
        
        return 'success';
    }

    public static function canView(): bool
    {
        return true;
    }
}

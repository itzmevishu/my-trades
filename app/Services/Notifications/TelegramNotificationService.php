<?php

namespace App\Services\Notifications;

use App\Services\BaseService;
use Illuminate\Support\Facades\Http;

/**
 * Telegram Notification Service
 * 
 * Send instant trading notifications via Telegram bot
 * Free, fast, and reliable for real-time trading alerts
 */
class TelegramNotificationService extends BaseService
{
    private ?string $botToken;
    private ?string $chatId;
    private bool $enabled;
    
    public function __construct()
    {
        $this->botToken = setting('telegram_bot_token');
        $this->chatId = setting('telegram_chat_id');
        $this->enabled = setting('telegram_notifications_enabled', false);
    }
    
    /**
     * Send notification about pattern detected
     */
    public function notifyPatternDetected(array $data): void
    {
        if (!$this->enabled || !$this->botToken || !$this->chatId) {
            return;
        }
        
        $message = "🔍 *PATTERN DETECTED*\n\n";
        $message .= "Pattern: *{$data['pattern']}*\n";
        $message .= "Direction: " . ($data['direction'] === 'bullish' ? '🟢' : '🔴') . " *{$data['direction']}*\n";
        $message .= "Price: ₹" . number_format($data['price'], 2) . "\n";
        $message .= "20 EMA: ₹" . number_format($data['ema_20'], 2) . "\n";
        $message .= "Claude Score: ⭐ {$data['claude_score']}/10\n";
        $message .= "\nTime: {$data['time']}";
        
        $this->sendMessage($message);
    }
    
    /**
     * Send notification about trade entry
     */
    public function notifyTradeEntry(array $trade): void
    {
        if (!$this->enabled || !$this->botToken || !$this->chatId) {
            return;
        }
        
        $emoji = $trade['direction'] === 'bullish' ? '📈' : '📉';
        
        $message = "{$emoji} *TRADE ENTRY*\n\n";
        $message .= "✅ Trade #{$trade['id']} Executed\n\n";
        $message .= "Pattern: *{$trade['candle_pattern']}*\n";
        $message .= "Direction: *{$trade['direction']}*\n";
        $message .= "Strike: {$trade['strike']}\n";
        $message .= "Type: {$trade['option_type']}\n\n";
        $message .= "💰 Entry: ₹{$trade['entry_premium']}\n";
        $message .= "🎯 Target: ₹{$trade['target_premium']}\n";
        $message .= "🛑 Stop Loss: ₹{$trade['sl_premium']}\n\n";
        $message .= "Quantity: {$trade['quantity']} ({$trade['lots']} lots)\n";
        $message .= "Max Risk: ₹" . number_format($trade['max_risk'], 2) . "\n";
        $message .= "Expected Profit: ₹" . number_format($trade['expected_profit'], 2) . "\n\n";
        $message .= "Claude Score: ⭐ {$trade['claude_score']}/10\n";
        $message .= "Time: {$trade['entry_time']}";
        
        $this->sendMessage($message);
    }
    
    /**
     * Send notification about trade exit
     */
    public function notifyTradeExit(array $trade): void
    {
        if (!$this->enabled || !$this->botToken || !$this->chatId) {
            return;
        }
        
        $isWin = $trade['pnl'] >= 0;
        $emoji = $isWin ? '✅' : '❌';
        $outcome = $isWin ? 'WIN' : 'LOSS';
        
        $message = "{$emoji} *TRADE EXIT - {$outcome}*\n\n";
        $message .= "Trade #{$trade['id']}\n\n";
        $message .= "Pattern: {$trade['candle_pattern']}\n";
        $message .= "Direction: {$trade['direction']}\n";
        $message .= "Strike: {$trade['strike']}\n\n";
        $message .= "💰 Entry: ₹{$trade['entry_premium']}\n";
        $message .= "🏁 Exit: ₹{$trade['exit_premium']}\n";
        $message .= "Exit Reason: *{$trade['exit_reason']}*\n\n";
        
        // P&L with color indicator
        if ($isWin) {
            $message .= "💵 *Profit: +₹" . number_format($trade['pnl'], 2) . "*\n";
        } else {
            $message .= "💸 *Loss: -₹" . number_format(abs($trade['pnl']), 2) . "*\n";
        }
        
        $message .= "ROI: " . number_format($trade['roi'], 2) . "%\n\n";
        $message .= "Entry: {$trade['entry_time']}\n";
        $message .= "Exit: {$trade['exit_time']}\n";
        $message .= "Duration: {$trade['duration']}";
        
        $this->sendMessage($message);
    }
    
    /**
     * Send notification about rejected scan
     */
    public function notifyRejectedScan(array $data): void
    {
        if (!$this->enabled || !$this->botToken || !$this->chatId) {
            return;
        }
        
        // Only send if user wants rejection notifications
        if (!setting('telegram_notify_rejections', false)) {
            return;
        }
        
        $message = "⚠️ *SCAN REJECTED*\n\n";
        $message .= "Result: {$data['result']}\n";
        
        if (!empty($data['pattern'])) {
            $message .= "Pattern: {$data['pattern']}\n";
        }
        
        $message .= "Price: ₹" . number_format($data['price'], 2) . "\n\n";
        $message .= "Reason:\n_{$data['reason']}_\n\n";
        $message .= "Time: {$data['time']}";
        
        $this->sendMessage($message);
    }
    
    /**
     * Send daily summary
     */
    public function notifyDailySummary(array $summary): void
    {
        if (!$this->enabled || !$this->botToken || !$this->chatId) {
            return;
        }
        
        $message = "📊 *DAILY TRADING SUMMARY*\n\n";
        $message .= "Date: {$summary['date']}\n\n";
        $message .= "Trades: {$summary['total_trades']}\n";
        $message .= "Wins: {$summary['wins']} 🎯\n";
        $message .= "Losses: {$summary['losses']} ❌\n";
        $message .= "Win Rate: {$summary['win_rate']}%\n\n";
        
        if ($summary['total_pnl'] >= 0) {
            $message .= "💰 *Net P&L: +₹" . number_format($summary['total_pnl'], 2) . "*\n";
        } else {
            $message .= "💸 *Net P&L: -₹" . number_format(abs($summary['total_pnl']), 2) . "*\n";
        }
        
        $message .= "Avg P&L: ₹" . number_format($summary['avg_pnl'], 2) . "\n";
        $message .= "Best Trade: ₹" . number_format($summary['best_trade'], 2) . "\n";
        $message .= "Worst Trade: ₹" . number_format($summary['worst_trade'], 2);
        
        $this->sendMessage($message);
    }
    
    /**
     * Send test notification
     */
    public function sendTestNotification(): bool
    {
        if (!$this->botToken || !$this->chatId) {
            return false;
        }
        
        $message = "✅ *Test Notification*\n\n";
        $message .= "Your BankNifty AI Trading Bot is connected!\n\n";
        $message .= "You will receive notifications for:\n";
        $message .= "• Pattern detected\n";
        $message .= "• Trade entry\n";
        $message .= "• Trade exit\n";
        $message .= "• Daily summary\n\n";
        $message .= "Time: " . now()->format('M d, Y H:i:s');
        
        return $this->sendMessage($message);
    }
    
    /**
     * Send message via Telegram Bot API
     */
    private function sendMessage(string $message): bool
    {
        try {
            $response = Http::post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);
            
            if ($response->successful()) {
                $this->logInfo('Telegram notification sent');
                return true;
            }
            
            $this->logError('Telegram notification failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            return false;
            
        } catch (\Exception $e) {
            $this->logError('Telegram notification exception: ' . $e->getMessage());
            return false;
        }
    }
}

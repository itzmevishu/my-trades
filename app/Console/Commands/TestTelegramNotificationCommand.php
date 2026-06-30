<?php

namespace App\Console\Commands;

use App\Services\Notifications\TelegramNotificationService;
use Illuminate\Console\Command;

/**
 * Test Telegram Notifications
 * 
 * Send a test notification to verify Telegram bot is configured correctly
 */
class TestTelegramNotificationCommand extends Command
{
    protected $signature = 'telegram:test';
    
    protected $description = 'Send a test notification to verify Telegram configuration';

    public function handle()
    {
        $this->info('📱 Testing Telegram Notification...');
        $this->newLine();
        
        // Check configuration
        $botToken = setting('telegram_bot_token');
        $chatId = setting('telegram_chat_id');
        $enabled = setting('telegram_notifications_enabled', false);
        
        if (!$botToken) {
            $this->error('❌ Telegram bot token not configured');
            $this->line('Set in admin panel: Settings → telegram_bot_token');
            return Command::FAILURE;
        }
        
        if (!$chatId) {
            $this->error('❌ Telegram chat ID not configured');
            $this->line('Set in admin panel: Settings → telegram_chat_id');
            return Command::FAILURE;
        }
        
        if (!$enabled) {
            $this->warn('⚠️  Telegram notifications are DISABLED');
            $this->line('Enable in admin panel: Settings → telegram_notifications_enabled → true');
            $this->newLine();
            $this->line('Sending test anyway...');
        }
        
        // Send test notification
        try {
            $telegram = new TelegramNotificationService();
            $result = $telegram->sendTestNotification();
            
            if ($result) {
                $this->info('✅ Test notification sent successfully!');
                $this->newLine();
                $this->line('Check your Telegram app to see the message.');
                return Command::SUCCESS;
            } else {
                $this->error('❌ Failed to send notification');
                $this->line('Check logs for details');
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Exception: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

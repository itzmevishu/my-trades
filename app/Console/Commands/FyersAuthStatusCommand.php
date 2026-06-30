<?php

namespace App\Console\Commands;

use App\Services\Fyers\FyersAuthService;
use Illuminate\Console\Command;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * Check Fyers Authentication Status
 * 
 * Displays current Fyers API authentication status
 * and provides guidance on re-authentication if needed
 */
class FyersAuthStatusCommand extends Command
{
    protected $signature = 'fyers:status';
    
    protected $description = 'Check Fyers API authentication status';

    public function handle()
    {
        $this->info('🔐 Fyers API Authentication Status');
        $this->newLine();
        
        // Check credentials
        $clientId = setting('fyers_client_id');
        $secretKey = setting('fyers_secret_key');
        $redirectUri = setting('fyers_redirect_uri');
        
        $this->line('📋 Configuration:');
        $this->line("   Client ID: " . ($clientId ? substr($clientId, 0, 15) . '...' : '❌ Not set'));
        $this->line("   Secret Key: " . ($secretKey ? '✅ Set' : '❌ Not set'));
        $this->line("   Redirect URI: " . ($redirectUri ?: '❌ Not set'));
        $this->newLine();
        
        if (!$clientId || !$secretKey || !$redirectUri) {
            $this->error('❌ Fyers credentials not configured!');
            $this->newLine();
            $this->line('Configure in admin panel:');
            $this->line('  Settings → fyers_client_id');
            $this->line('  Settings → fyers_secret_key');
            $this->line('  Settings → fyers_redirect_uri');
            return Command::FAILURE;
        }
        
        // Check access token
        $authService = new FyersAuthService();
        $accessToken = $authService->getAccessToken();
        $expiresAt = Setting::getValue('fyers_token_expires_at');
        
        $this->line('🔑 Access Token:');
        
        if ($accessToken) {
            $this->line("   Token: " . substr($accessToken, 0, 30) . '...');
            
            if ($expiresAt) {
                $expiryTime = Carbon::createFromTimestamp($expiresAt);
                $hoursLeft = now()->diffInHours($expiryTime, false);
                
                if ($hoursLeft > 0) {
                    $this->info("   Status: ✅ Valid");
                    $this->line("   Expires: {$expiryTime->format('M d, Y H:i:s')}");
                    $this->line("   Time left: " . round($hoursLeft, 1) . " hours");
                } else {
                    $this->error("   Status: ❌ Expired");
                    $this->line("   Expired: {$expiryTime->format('M d, Y H:i:s')}");
                    $this->newLine();
                    $this->warn('⚠️  Token expired. Please re-authenticate.');
                    $this->line('Visit: ' . url('/fyers/auth'));
                }
            } else {
                $this->warn("   Status: ⚠️  Expiry unknown");
            }
        } else {
            $this->error("   Status: ❌ No token found");
            $this->newLine();
            $this->warn('⚠️  Not authenticated. Please authenticate first.');
            $this->line('Visit: ' . url('/fyers/auth'));
        }
        
        $this->newLine();
        
        // Show auth URL
        if (!$accessToken || ($expiresAt && now()->timestamp > $expiresAt)) {
            $this->line('📌 To authenticate:');
            $this->line('   1. Visit: ' . url('/fyers/auth'));
            $this->line('   2. Login with Fyers credentials');
            $this->line('   3. Authorize the application');
            $this->line('   4. You will be redirected back with success message');
            $this->newLine();
            $this->info('💡 Tokens are valid for 23 hours');
            
            return Command::FAILURE;
        }
        
        $this->info('✅ Fyers API is authenticated and ready!');
        
        return Command::SUCCESS;
    }
}

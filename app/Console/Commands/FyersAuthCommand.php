<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Services\Fyers\FyersAuthService;

#[Signature('fyers:auth {action=url : Action to perform (url, verify, status, logout)}')]
#[Description('Manage Fyers API authentication')]
class FyersAuthCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $authService = new FyersAuthService();

        return match($action) {
            'url' => $this->generateAuthUrl($authService),
            'verify' => $this->verifyAuthCode($authService),
            'status' => $this->checkStatus($authService),
            'logout' => $this->logout($authService),
            default => $this->showHelp()
        };
    }

    private function generateAuthUrl(FyersAuthService $authService): int
    {
        $this->info('🔐 Fyers Authentication Setup');
        $this->newLine();

        $authUrl = $authService->generateAuthUrl();

        $this->line('📝 Step 1: Open this URL in your browser:');
        $this->newLine();
        $this->line($authUrl);
        $this->newLine();
        
        $this->line('📝 Step 2: Login with your Fyers credentials');
        $this->line('📝 Step 3: Authorize the app');
        $this->line('📝 Step 4: Copy the auth code from the redirect URL');
        $this->newLine();
        
        $this->info('💡 After you get the auth code, run:');
        $this->line('   php artisan fyers:auth verify');

        return Command::SUCCESS;
    }

    private function verifyAuthCode(FyersAuthService $authService): int
    {
        $this->info('🔐 Verify Fyers Auth Code');
        $this->newLine();

        $authCode = $this->ask('Enter the auth code from Fyers');

        if (!$authCode) {
            $this->error('Auth code is required');
            return Command::FAILURE;
        }

        $this->info('Verifying auth code...');
        $result = $authService->exchangeAuthCode($authCode);

        if ($result['success']) {
            $this->newLine();
            $this->info('✅ Authentication successful!');
            $this->line('Access token stored and valid for 24 hours');
            $this->newLine();
            $this->info('🚀 You can now enable real data:');
            $this->line('   Go to: http://127.0.0.1:8001/admin/settings');
            $this->line('   Set: use_real_data = true');
            return Command::SUCCESS;
        } else {
            $this->error('❌ Authentication failed: ' . ($result['error'] ?? 'Unknown error'));
            return Command::FAILURE;
        }
    }

    private function checkStatus(FyersAuthService $authService): int
    {
        $this->info('📊 Fyers Authentication Status');
        $this->newLine();

        $isValid = $authService->isTokenValid();
        $token = $authService->getAccessToken();

        if ($isValid && $token) {
            $this->info('✅ Status: Authenticated');
            $this->line('🔑 Token: ' . substr($token, 0, 20) . '...');
            $this->line('⏰ Valid for: ~24 hours from authentication');
            $this->newLine();
            $this->info('💡 Real data enabled: ' . (setting('use_real_data') ? 'YES' : 'NO'));
        } else {
            $this->warn('⚠️  Status: Not authenticated');
            $this->newLine();
            $this->info('To authenticate, run:');
            $this->line('   php artisan fyers:auth url');
        }

        return Command::SUCCESS;
    }

    private function logout(FyersAuthService $authService): int
    {
        $authService->revokeToken();
        $this->info('✅ Logged out from Fyers API');
        $this->line('Access token has been revoked');
        return Command::SUCCESS;
    }

    private function showHelp(): int
    {
        $this->info('📖 Fyers Authentication Commands');
        $this->newLine();
        $this->line('Available actions:');
        $this->line('  url     - Generate authentication URL');
        $this->line('  verify  - Verify auth code and get access token');
        $this->line('  status  - Check authentication status');
        $this->line('  logout  - Revoke access token');
        $this->newLine();
        $this->line('Example usage:');
        $this->line('  php artisan fyers:auth url');
        $this->line('  php artisan fyers:auth verify');
        $this->line('  php artisan fyers:auth status');
        return Command::SUCCESS;
    }
}

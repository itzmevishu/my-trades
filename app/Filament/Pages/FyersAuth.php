<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action;
use Filament\Notifications\Notification;
use App\Services\Fyers\FyersAuthService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;

class FyersAuth extends Page implements HasForms
{
    use InteractsWithForms;
    
    protected static ?string $navigationIcon = 'heroicon-o-key';
    
    protected static ?string $navigationLabel = 'Fyers API';
    
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.fyers-auth';
    
    public ?string $authCode = null;
    public ?array $authStatus = null;
    public ?string $authUrl = null;
    
    public function mount(): void
    {
        $this->refreshAuthStatus();
    }
    
    public function refreshAuthStatus(): void
    {
        $authService = new FyersAuthService();
        $isValid = $authService->isTokenValid();
        $token = $authService->getAccessToken();
        
        $this->authStatus = [
            'authenticated' => $isValid,
            'token_preview' => $token ? substr($token, 0, 30) . '...' : null,
            'use_real_data' => setting('use_real_data', false),
            'fyers_client_id' => setting('fyers_client_id'),
        ];
    }
    
    public function generateAuthUrl(): void
    {
        $authService = new FyersAuthService();
        $this->authUrl = $authService->generateAuthUrl();
        
        Notification::make()
            ->title('Auth URL Generated')
            ->body('Open the URL in a new tab to authorize the app')
            ->success()
            ->send();
    }
    
    public function verifyAuthCode(): void
    {
        if (!$this->authCode) {
            Notification::make()
                ->title('Error')
                ->body('Please enter the auth code')
                ->danger()
                ->send();
            return;
        }
        
        $authService = new FyersAuthService();
        $result = $authService->exchangeAuthCode($this->authCode);
        
        if ($result['success']) {
            $this->authCode = null;
            $this->authUrl = null;
            $this->refreshAuthStatus();
            
            Notification::make()
                ->title('Authentication Successful!')
                ->body('Access token stored and valid for 24 hours')
                ->success()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title('Authentication Failed')
                ->body($result['error'] ?? 'Unknown error')
                ->danger()
                ->send();
        }
    }
    
    public function logout(): void
    {
        $authService = new FyersAuthService();
        $authService->revokeToken();
        $this->refreshAuthStatus();
        
        Notification::make()
            ->title('Logged Out')
            ->body('Access token has been revoked')
            ->warning()
            ->send();
    }
    
    public function enableRealData(): void
    {
        if (!$this->authStatus['authenticated']) {
            Notification::make()
                ->title('Not Authenticated')
                ->body('Please authenticate with Fyers first')
                ->danger()
                ->send();
            return;
        }
        
        \App\Models\Setting::setValue('use_real_data', true, 'boolean');
        $this->refreshAuthStatus();
        
        Notification::make()
            ->title('Real Data Enabled')
            ->body('System will now use real market data from Fyers API')
            ->success()
            ->send();
    }
    
    public function disableRealData(): void
    {
        \App\Models\Setting::setValue('use_real_data', false, 'boolean');
        $this->refreshAuthStatus();
        
        Notification::make()
            ->title('Real Data Disabled')
            ->body('System will use simulated data')
            ->warning()
            ->send();
    }
}

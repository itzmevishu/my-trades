<?php

namespace App\Services\Fyers;

use App\Services\BaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\Setting;

/**
 * Fyers Authentication Service
 * 
 * Handles OAuth2 authentication, token management, and auto-refresh
 * for Fyers API integration.
 * 
 * Phase: Week 3 - Data Pipeline
 */
class FyersAuthService extends BaseService
{
    private string $baseUrl = 'https://api-t1.fyers.in/api/v3';
    
    /**
     * Generate auth URL for user to login
     */
    public function generateAuthUrl(): string
    {
        $clientId = setting('fyers_client_id');
        $redirectUri = setting('fyers_redirect_uri');
        $state = bin2hex(random_bytes(16));
        
        Cache::put('fyers_auth_state', $state, now()->addMinutes(10));
        
        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
            'scope' => 'openid profile orders',
        ]);
        
        return "https://api-t1.fyers.in/api/v3/generate-authcode?{$params}";
    }
    
    /**
     * Exchange auth code for access token
     */
    public function exchangeAuthCode(string $authCode): array
    {
        $clientId = setting('fyers_client_id');
        $secretKey = setting('fyers_secret_key');
        
        try {
            $response = Http::post("{$this->baseUrl}/validate-authcode", [
                'grant_type' => 'authorization_code',
                'appIdHash' => hash('sha256', $clientId . ':' . $secretKey),
                'code' => $authCode,
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $accessToken = $data['access_token'] ?? null;
                
                if ($accessToken) {
                    // Store token in settings (valid for 23 hours)
                    Setting::setValue('fyers_access_token', $accessToken, 'string', 'Fyers API Access Token');
                    Setting::setValue('fyers_token_expires_at', now()->addHours(23)->timestamp, 'integer', 'Token expiration timestamp');
                    
                    $this->logInfo('Fyers authentication successful');
                    
                    return ['success' => true, 'token' => $accessToken];
                }
            }
            
            $this->logError('Fyers auth failed: ' . $response->body());
            return ['success' => false, 'error' => 'Authentication failed'];
            
        } catch (\Exception $e) {
            $this->logError('Fyers auth exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get access token from settings
     */
    public function getAccessToken(): ?string
    {
        if (!$this->isTokenValid()) {
            return null;
        }
        
        return Setting::getValue('fyers_access_token');
    }
    
    /**
     * Check if token is valid (exists and not expired)
     */
    public function isTokenValid(): bool
    {
        $token = Setting::getValue('fyers_access_token');
        $expiresAt = Setting::getValue('fyers_token_expires_at');
        
        if (!$token || !$expiresAt) {
            return false;
        }
        
        // Check if token hasn't expired
        return now()->timestamp < $expiresAt;
    }
    
    /**
     * Revoke access token (logout)
     */
    public function revokeToken(): bool
    {
        Setting::setValue('fyers_access_token', '', 'string');
        Setting::setValue('fyers_token_expires_at', 0, 'integer');
        Cache::forget('fyers_auth_state');
        $this->logInfo('Fyers token revoked');
        return true;
    }
    
    /**
     * Authenticate with Fyers API (legacy method)
     */
    public function authenticate(): bool
    {
        if ($this->isTokenValid()) {
            $this->logInfo('Fyers token already valid');
            return true;
        }
        
        $this->logWarning('Fyers token not found. Generate auth URL and complete OAuth flow.');
        return false;
    }
    
    /**
     * Refresh access token (not supported by Fyers v3 - requires re-auth)
     */
    public function refreshToken(): bool
    {
        $this->logWarning('Fyers v3 does not support token refresh. User must re-authenticate daily.');
        return false;
    }
}

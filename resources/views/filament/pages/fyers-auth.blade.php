<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Auth Status Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-2 {{ $authStatus['authenticated'] ? 'border-success-500' : 'border-gray-300 dark:border-gray-700' }}">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Authentication Status</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Fyers API OAuth2 Connection</p>
                </div>
                <div class="flex items-center gap-2">
                    @if($authStatus['authenticated'])
                        <x-filament::badge color="success">
                            Authenticated
                        </x-filament::badge>
                    @else
                        <x-filament::badge color="danger">
                            Not Authenticated
                        </x-filament::badge>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="bg-gray-50 dark:bg-gray-900 rounded p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">App ID</div>
                    <div class="font-mono text-sm text-gray-900 dark:text-white">{{ $authStatus['fyers_client_id'] ?? 'Not configured' }}</div>
                </div>
                @if($authStatus['token_preview'])
                    <div class="bg-gray-50 dark:bg-gray-900 rounded p-4">
                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Access Token</div>
                        <div class="font-mono text-xs text-gray-900 dark:text-white truncate">{{ $authStatus['token_preview'] }}</div>
                    </div>
                @endif
                <div class="bg-gray-50 dark:bg-gray-900 rounded p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Real Data</div>
                    <div class="font-semibold text-sm {{ $authStatus['use_real_data'] ? 'text-success-600' : 'text-gray-600 dark:text-gray-400' }}">
                        {{ $authStatus['use_real_data'] ? '✅ Enabled' : '❌ Disabled' }}
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900 rounded p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Token Validity</div>
                    <div class="font-semibold text-sm text-gray-900 dark:text-white">
                        {{ $authStatus['authenticated'] ? '~24 hours' : 'N/A' }}
                    </div>
                </div>
            </div>

            @if($authStatus['authenticated'])
                <div class="bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-800 rounded p-4">
                    <div class="flex items-start gap-3">
                        <div>
                            <div class="font-medium text-success-900 dark:text-success-100">✅ Connected to Fyers API</div>
                            <div class="text-sm text-success-700 dark:text-success-300 mt-1">
                                You can now fetch real market data. Enable "Real Data" below to start using it.
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800 rounded p-4">
                    <div class="flex items-start gap-3">
                        <div>
                            <div class="font-medium text-warning-900 dark:text-warning-100">⚠️ Not Connected</div>
                            <div class="text-sm text-warning-700 dark:text-warning-300 mt-1">
                                Follow the steps below to authenticate with Fyers API and enable real market data.
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Authentication Flow -->
        @if(!$authStatus['authenticated'])
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    🔐 Authentication Steps
                </h3>

                <!-- Step 1: Generate Auth URL -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 text-primary-600 dark:text-primary-400 font-bold">1</div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Generate Authorization URL</h4>
                    </div>
                    
                    <div class="ml-10">
                        <x-filament::button wire:click="generateAuthUrl" color="primary" size="lg">
                            Generate Auth URL
                        </x-filament::button>

                        @if($authUrl)
                            <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-900 rounded border">
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">Click to open in new tab:</div>
                                <a href="{{ $authUrl }}" target="_blank" class="text-primary-600 hover:text-primary-700 dark:text-primary-400 break-all text-sm font-mono">
                                    {{ $authUrl }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Step 2: Login & Authorize -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 font-bold">2</div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Login & Authorize</h4>
                    </div>
                    <div class="ml-10 text-sm text-gray-600 dark:text-gray-400">
                        <ul class="list-disc list-inside space-y-1">
                            <li>Open the URL in a new browser tab</li>
                            <li>Login with your Fyers credentials</li>
                            <li>Click "Authorize" to grant access</li>
                            <li>You'll be redirected back to this app</li>
                        </ul>
                    </div>
                </div>

                <!-- Step 3: Automatic -->
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 font-bold">3</div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Automatic Verification</h4>
                    </div>
                    <div class="ml-10 text-sm text-gray-600 dark:text-gray-400">
                        After authorization, you'll be redirected back and the token will be automatically stored.
                    </div>
                </div>
            </div>
        @endif

        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                ⚡ Quick Actions
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @if($authStatus['authenticated'])
                    @if($authStatus['use_real_data'])
                        <x-filament::button wire:click="disableRealData" color="warning" outlined size="lg">
                            Disable Real Data
                        </x-filament::button>
                    @else
                        <x-filament::button wire:click="enableRealData" color="success" size="lg">
                            Enable Real Data
                        </x-filament::button>
                    @endif

                    <x-filament::button wire:click="logout" color="danger" outlined size="lg">
                        Logout (Revoke Token)
                        </x-filament::button>
                @endif

                <x-filament::button wire:click="refreshAuthStatus" color="gray" outlined size="lg">
                    Refresh Status
                </x-filament::button>

                <x-filament::button href="{{ route('filament.admin.resources.settings.index') }}" tag="a" color="gray" outlined size="lg">
                    View All Settings
                </x-filament::button>
            </div>
        </div>

        <!-- Important Notes -->
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
            <div class="flex items-start gap-3">
                <div>
                    <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">💡 Important Information</h4>
                    <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1.5">
                        <li><strong>Daily Re-authentication:</strong> Tokens expire after 24 hours. Re-authenticate daily before market hours.</li>
                        <li><strong>Paper Trading:</strong> Even with real data, you're still paper trading. No real orders placed.</li>
                        <li><strong>Market Hours:</strong> Real data only available 9:15 AM - 3:30 PM IST, Monday-Friday.</li>
                        <li><strong>Fallback:</strong> System automatically uses simulated data if API fails.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

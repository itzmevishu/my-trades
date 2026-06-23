<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fyers Authentication {{ $success ? 'Success' : 'Failed' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-green-400 font-mono">
    <div class="min-h-screen flex items-center justify-center p-8">
        <div class="max-w-2xl w-full">
            <!-- Success/Error Card -->
            <div class="bg-gray-800 border-2 {{ $success ? 'border-green-500' : 'border-red-500' }} rounded-lg p-8 shadow-2xl">
                <!-- Icon -->
                <div class="text-center mb-6">
                    @if($success)
                        <div class="text-8xl mb-4">✅</div>
                        <h1 class="text-4xl font-bold text-green-400">Authentication Successful!</h1>
                    @else
                        <div class="text-8xl mb-4">❌</div>
                        <h1 class="text-4xl font-bold text-red-400">Authentication Failed</h1>
                    @endif
                </div>

                <!-- Message -->
                <div class="bg-gray-900 border border-gray-700 rounded p-6 mb-6">
                    <p class="text-lg text-center">{{ $message }}</p>
                    
                    @if($success && isset($token_preview))
                        <div class="mt-4 text-center">
                            <p class="text-sm text-gray-500 mb-2">Token Preview:</p>
                            <p class="text-xs text-green-500 font-mono">{{ $token_preview }}</p>
                        </div>
                    @endif
                </div>

                <!-- Status Details -->
                @if($success)
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between border-b border-gray-700 pb-2">
                            <span class="text-gray-400">Status:</span>
                            <span class="font-bold text-green-400">✅ Authenticated</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-700 pb-2">
                            <span class="text-gray-400">Token Validity:</span>
                            <span class="font-bold text-emerald-400">24 hours</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-700 pb-2">
                            <span class="text-gray-400">Stored In:</span>
                            <span class="font-bold text-emerald-400">Cache</span>
                        </div>
                    </div>
                @endif

                <!-- Next Steps -->
                @if($success)
                    <div class="bg-green-900/20 border border-green-800 rounded p-6 mb-6">
                        <h3 class="text-xl font-bold text-green-400 mb-4">📋 Next Steps:</h3>
                        <ol class="list-decimal list-inside space-y-2 text-green-300">
                            <li>Go to Settings and enable <strong>use_real_data</strong></li>
                            <li>System will now fetch real market data from Fyers</li>
                            <li>Still paper trading (100% safe)</li>
                            <li>Test during market hours (9:15 AM - 3:30 PM)</li>
                        </ol>
                    </div>
                @else
                    <div class="bg-red-900/20 border border-red-800 rounded p-6 mb-6">
                        <h3 class="text-xl font-bold text-red-400 mb-4">💡 What to do:</h3>
                        <ul class="list-disc list-inside space-y-2 text-red-300">
                            <li>Try generating a new auth URL</li>
                            <li>Make sure you authorized the app</li>
                            <li>Check your Fyers API credentials in settings</li>
                            <li>Run: <code class="bg-gray-900 px-2 py-1 rounded">php artisan fyers:auth url</code></li>
                        </ul>
                    </div>
                @endif

                <!-- Quick Actions -->
                <div class="grid grid-cols-2 gap-4">
                    <a href="/admin" class="bg-emerald-700 hover:bg-emerald-600 text-white text-center font-bold py-3 px-6 rounded transition-colors">
                        📊 Dashboard
                    </a>
                    <a href="/admin/settings" class="bg-cyan-700 hover:bg-cyan-600 text-white text-center font-bold py-3 px-6 rounded transition-colors">
                        ⚙️ Settings
                    </a>
                </div>

                @if($success)
                    <div class="mt-6 text-center text-sm text-gray-500">
                        <p>🤖 Token will auto-expire in 24 hours</p>
                        <p class="mt-1">Re-authenticate daily before trading hours</p>
                    </div>
                @endif
            </div>

            <!-- Command Reference -->
            @if($success)
                <div class="mt-6 bg-gray-800 border border-gray-700 rounded p-6">
                    <h3 class="text-lg font-bold text-green-400 mb-3">🔧 Useful Commands:</h3>
                    <div class="space-y-2 text-sm font-mono">
                        <div class="bg-gray-900 p-3 rounded">
                            <span class="text-gray-500"># Check auth status</span><br>
                            <span class="text-green-400">php artisan fyers:auth status</span>
                        </div>
                        <div class="bg-gray-900 p-3 rounded">
                            <span class="text-gray-500"># Test with real data</span><br>
                            <span class="text-green-400">php artisan trading:scan</span>
                        </div>
                        <div class="bg-gray-900 p-3 rounded">
                            <span class="text-gray-500"># Logout (revoke token)</span><br>
                            <span class="text-green-400">php artisan fyers:auth logout</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>

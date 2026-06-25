<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚀 {{ config('app.name') }} - AI Trading Terminal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes matrix {
            0% { transform: translateY(-100%); }
            100% { transform: translateY(100%); }
        }
        .matrix-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
            opacity: 0.1;
        }
        .matrix-char {
            position: absolute;
            color: #00ff00;
            font-family: monospace;
            font-size: 20px;
            animation: matrix 3s linear infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse {
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .slide-in {
            animation: slideIn 0.5s ease-out;
        }
        @keyframes glow {
            0%, 100% { box-shadow: 0 0 5px #00ff00, 0 0 10px #00ff00; }
            50% { box-shadow: 0 0 20px #00ff00, 0 0 30px #00ff00; }
        }
        .glow {
            animation: glow 2s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-black text-green-400 font-mono overflow-x-hidden">
    <!-- Matrix Background -->
    <div class="matrix-bg" id="matrix"></div>

    <div class="relative z-10 min-h-screen p-8">
        <!-- Header -->
        <div class="max-w-7xl mx-auto mb-12 slide-in">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-6xl font-bold mb-2 text-green-400">
                        🚀 {{ config('app.name') }}
                    </h1>
                    <p class="text-xl text-green-300">
                        AI-Powered Bank Nifty Options Trading Terminal
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">SYSTEM STATUS</div>
                    <div class="flex items-center gap-2 justify-end mt-1">
                        <div class="w-3 h-3 bg-green-500 rounded-full pulse"></div>
                        <span class="text-green-400 font-bold">OPERATIONAL</span>
                    </div>
                </div>
            </div>
            <div class="border-t-2 border-green-800"></div>
        </div>

        <!-- Live Stats Grid -->
        <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <!-- Total Trades -->
            <div class="bg-gray-900 border-2 border-green-800 rounded-lg p-6 glow hover:scale-105 transition-transform">
                <div class="text-gray-500 text-sm mb-2">TOTAL TRADES</div>
                <div class="text-5xl font-bold text-green-400">{{ $stats['total_trades'] }}</div>
                <div class="text-sm text-emerald-500 mt-2">
                    ↑ W: {{ $stats['wins'] }} | L: {{ $stats['losses'] }}
                </div>
            </div>

            <!-- Win Rate -->
            <div class="bg-gray-900 border-2 border-green-800 rounded-lg p-6 glow hover:scale-105 transition-transform">
                <div class="text-gray-500 text-sm mb-2">WIN RATE</div>
                <div class="text-5xl font-bold {{ $stats['win_rate'] >= 60 ? 'text-green-400' : ($stats['win_rate'] >= 50 ? 'text-yellow-400' : 'text-red-400') }}">
                    {{ number_format($stats['win_rate'], 1) }}%
                </div>
                <div class="text-sm text-emerald-500 mt-2">
                    TARGET: &gt; 60%
                </div>
            </div>

            <!-- Total P&L -->
            <div class="bg-gray-900 border-2 border-green-800 rounded-lg p-6 glow hover:scale-105 transition-transform">
                <div class="text-gray-500 text-sm mb-2">TOTAL P&amp;L</div>
                <div class="text-5xl font-bold {{ $stats['total_pnl'] >= 0 ? 'text-green-400' : 'text-red-400' }}">
                    ₹{{ number_format(abs($stats['total_pnl'])) }}
                </div>
                <div class="text-sm text-emerald-500 mt-2">
                    AVG: ₹{{ number_format($stats['avg_pnl']) }}
                </div>
            </div>

            <!-- Strategy Version -->
            <div class="bg-gray-900 border-2 border-green-800 rounded-lg p-6 glow hover:scale-105 transition-transform">
                <div class="text-gray-500 text-sm mb-2">AI STRATEGY</div>
                <div class="text-5xl font-bold text-emerald-400">v{{ $stats['strategy_version'] }}</div>
                <div class="text-sm text-emerald-500 mt-2">
                    🤖 LEARNING MODE
                </div>
            </div>
        </div>

        <!-- System Info -->
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-6 mb-12">
            <!-- Trading Mode -->
            <div class="bg-gray-900 border-2 {{ $mode['is_safe'] ? 'border-green-600' : 'border-red-600' }} rounded-lg p-6">
                <h3 class="text-2xl font-bold mb-4 text-green-400">⚙️ TRADING MODE</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Mode:</span>
                        <span class="font-bold {{ $mode['paper_mode'] ? 'text-green-400' : 'text-red-400' }}">
                            {{ $mode['paper_mode'] ? '📄 PAPER TRADING' : '🔴 LIVE TRADING' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Live Enabled:</span>
                        <span class="font-bold {{ $mode['live_enabled'] ? 'text-red-400' : 'text-green-400' }}">
                            {{ $mode['live_enabled'] ? '🔓 UNLOCKED' : '🔒 LOCKED' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Capital:</span>
                        <span class="font-bold text-emerald-400">₹{{ number_format($mode['capital']) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Risk/Trade:</span>
                        <span class="font-bold text-emerald-400">{{ $mode['risk_pct'] }}%</span>
                    </div>
                </div>
            </div>

            <!-- Market Status -->
            <div class="bg-gray-900 border-2 border-green-800 rounded-lg p-6">
                <h3 class="text-2xl font-bold mb-4 text-green-400">📊 MARKET STATUS</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Current Time:</span>
                        <span class="font-bold text-emerald-400" id="current-time">{{ $market['current_time'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Trading Hours:</span>
                        <span class="font-bold text-emerald-400">{{ setting('trading_start_time') }} - {{ setting('trading_end_time') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Status:</span>
                        <span class="font-bold {{ $market['in_trading_window'] ? 'text-green-400 pulse' : 'text-yellow-400' }}">
                            {{ $market['in_trading_window'] ? '🟢 OPEN' : '🟡 CLOSED' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Today's Trades:</span>
                        <span class="font-bold text-emerald-400">{{ $market['today_trades'] }}/1</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="max-w-7xl mx-auto mb-12">
            <h3 class="text-3xl font-bold mb-6 text-green-400">⚡ QUICK ACCESS</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="/admin" class="bg-green-900 hover:bg-green-800 border-2 border-green-600 rounded-lg p-8 text-center transition-all hover:scale-105 glow">
                    <div class="text-5xl mb-3">📊</div>
                    <div class="text-xl font-bold text-green-400">DASHBOARD</div>
                    <div class="text-sm text-gray-400 mt-2">View all trades & stats</div>
                </a>
                <a href="/admin/trades" class="bg-emerald-900 hover:bg-emerald-800 border-2 border-emerald-600 rounded-lg p-8 text-center transition-all hover:scale-105 glow">
                    <div class="text-5xl mb-3">💹</div>
                    <div class="text-xl font-bold text-emerald-400">TRADES</div>
                    <div class="text-sm text-gray-400 mt-2">Manage positions</div>
                </a>
                <a href="/admin/settings" class="bg-cyan-900 hover:bg-cyan-800 border-2 border-cyan-600 rounded-lg p-8 text-center transition-all hover:scale-105 glow">
                    <div class="text-5xl mb-3">⚙️</div>
                    <div class="text-xl font-bold text-cyan-400">SETTINGS</div>
                    <div class="text-sm text-gray-400 mt-2">Configure system</div>
                </a>
            </div>
        </div>

        <!-- Trading Philosophy -->
        <div class="max-w-7xl mx-auto">
            <div class="bg-gray-900 border-2 border-green-800 rounded-lg p-8">
                <h3 class="text-2xl font-bold mb-4 text-green-400">💡 SYSTEM PRINCIPLES</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                    <div>
                        <div class="text-4xl mb-2">🎯</div>
                        <div class="font-bold text-emerald-400 mb-1">PRECISION ENTRY</div>
                        <div class="text-sm text-gray-400">Pattern + EMA + AI Score</div>
                    </div>
                    <div>
                        <div class="text-4xl mb-2">🛡️</div>
                        <div class="font-bold text-emerald-400 mb-1">RISK MANAGEMENT</div>
                        <div class="text-sm text-gray-400">1% Risk | 2:1 R:R Target</div>
                    </div>
                    <div>
                        <div class="text-4xl mb-2">🧠</div>
                        <div class="font-bold text-emerald-400 mb-1">CONTINUOUS LEARNING</div>
                        <div class="text-sm text-gray-400">AI Adapts Every 10 Trades</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="max-w-7xl mx-auto mt-12 text-center text-gray-600 text-sm">
            <div class="border-t border-green-900 pt-6">
                <p>⚠️ {{ $mode['paper_mode'] ? 'PAPER TRADING MODE - NO REAL MONEY' : 'LIVE TRADING MODE - REAL MONEY AT RISK' }}</p>
                <p class="mt-2">Bank Nifty Options • Intraday Only • Max 2 Lots • AI-Powered Decision Making</p>
                <p class="mt-2 text-green-700">Built with ❤️ by Vishal • Powered by Claude AI</p>
            </div>
        </div>
    </div>

    <script>
        // Matrix rain effect
        function createMatrixRain() {
            const container = document.getElementById('matrix');
            const chars = '01BNFYRSℝ₹⚡📊📈📉';
            
            for (let i = 0; i < 50; i++) {
                const char = document.createElement('div');
                char.className = 'matrix-char';
                char.textContent = chars[Math.floor(Math.random() * chars.length)];
                char.style.left = Math.random() * 100 + '%';
                char.style.animationDuration = (Math.random() * 2 + 2) + 's';
                char.style.animationDelay = Math.random() * 2 + 's';
                container.appendChild(char);
            }
        }

        // Update clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleString('en-IN', {
                timeZone: 'Asia/Kolkata',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            document.getElementById('current-time').textContent = timeString;
        }

        // Initialize
        createMatrixRain();
        updateClock();
        setInterval(updateClock, 1000);

        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

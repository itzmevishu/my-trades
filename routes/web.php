<?php

use Illuminate\Support\Facades\Route;
use App\Models\Trade;
use App\Models\StrategyConfig;
use App\Services\Fyers\FyersAuthService;
use Illuminate\Http\Request;

Route::get('/', function () {
    $trades = Trade::all();
    $wins = $trades->where('outcome', 'WIN')->count();
    $losses = $trades->where('outcome', 'LOSS')->count();
    $total = $trades->count();
    $winRate = $total > 0 ? ($wins / $total) * 100 : 0;
    $totalPnL = $trades->sum('pnl');
    $avgPnL = $total > 0 ? $totalPnL / $total : 0;
    
    $currentStrategy = StrategyConfig::where('is_active', true)->first();
    $strategyVersion = $currentStrategy ? $currentStrategy->version : 1;
    
    $now = now('Asia/Kolkata');
    
    // Get trading hours from settings (existing: trading_start_time, trading_end_time)
    $tradingStart = now('Asia/Kolkata')->setTimeFromTimeString(setting('trading_start_time', '11:15:00'));
    $tradingEnd = now('Asia/Kolkata')->setTimeFromTimeString(setting('trading_end_time', '14:00:00'));
    $inTradingWindow = $now->between($tradingStart, $tradingEnd) && $now->isWeekday();
    $todayTrades = Trade::whereDate('entry_time', $now->toDateString())->count();
    
    return view('trader-home', [
        'stats' => [
            'total_trades' => $total,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $winRate,
            'total_pnl' => $totalPnL,
            'avg_pnl' => $avgPnL,
            'strategy_version' => $strategyVersion,
        ],
        'mode' => [
            'paper_mode' => setting('paper_trade_mode', true),
            'live_enabled' => setting('live_trading_enabled', false),
            'capital' => setting('capital_amount', 300000),
            'risk_pct' => setting('risk_percentage', 1.0),
            'is_safe' => setting('paper_trade_mode', true) || !setting('live_trading_enabled', false),
        ],
        'market' => [
            'current_time' => $now->format('h:i:s A'),
            'in_trading_window' => $inTradingWindow,
            'today_trades' => $todayTrades,
        ],
    ]);
});

Route::get('/callback', function (Request $request) {
    $authCode = $request->query('auth_code');
    $status = $request->query('s');
    $state = $request->query('state');
    
    if ($status !== 'ok' || !$authCode) {
        return view('fyers-auth-result', [
            'success' => false,
            'message' => 'Authentication failed or was cancelled.',
        ]);
    }
    
    $authService = new FyersAuthService();
    $result = $authService->exchangeAuthCode($authCode);
    
    if ($result['success']) {
        return view('fyers-auth-result', [
            'success' => true,
            'message' => 'Fyers authentication successful! Access token stored.',
            'token_preview' => substr($result['token'], 0, 30) . '...',
        ]);
    } else {
        return view('fyers-auth-result', [
            'success' => false,
            'message' => 'Failed to exchange auth code: ' . ($result['error'] ?? 'Unknown error'),
        ]);
    }
});

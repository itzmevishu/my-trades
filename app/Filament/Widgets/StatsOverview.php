<?php

namespace App\Filament\Widgets;

use App\Models\Trade;
use App\Models\StrategyConfig;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalTrades = Trade::count();
        $wins = Trade::where('outcome', 'win')->count();
        $losses = Trade::where('outcome', 'loss')->count();
        $winRate = $totalTrades > 0 ? round(($wins / $totalTrades) * 100, 1) : 0;
        
        $totalPnL = Trade::sum('pnl_inr');
        $avgPnL = $totalTrades > 0 ? Trade::avg('pnl_inr') : 0;
        
        $currentStrategy = StrategyConfig::where('is_active', true)->first();
        $strategyVersion = $currentStrategy ? $currentStrategy->version : 0;
        
        // Recent performance (last 10 trades)
        $recentTrades = Trade::orderBy('date', 'desc')->take(10)->get();
        $recentWinRate = $recentTrades->count() > 0 
            ? round(($recentTrades->where('outcome', 'win')->count() / $recentTrades->count()) * 100, 1) 
            : 0;
        
        return [
            Stat::make('Total Trades', $totalTrades)
                ->description($wins . ' wins, ' . $losses . ' losses')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
                
            Stat::make('Win Rate', $winRate . '%')
                ->description('Overall success rate')
                ->descriptionIcon($winRate >= 60 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($winRate >= 60 ? 'success' : ($winRate >= 50 ? 'warning' : 'danger'))
                ->chart($this->getWinRateChart()),
                
            Stat::make('Total P&L', '₹' . number_format($totalPnL, 2))
                ->description('Avg: ₹' . number_format($avgPnL, 2) . ' per trade')
                ->descriptionIcon($totalPnL >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($totalPnL >= 0 ? 'success' : 'danger')
                ->chart($this->getPnLChart()),
                
            Stat::make('Strategy Version', 'v' . $strategyVersion)
                ->description('AI-optimized weights')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info'),
                
            Stat::make('Recent Form', $recentWinRate . '%')
                ->description('Last 10 trades')
                ->descriptionIcon($recentWinRate >= $winRate ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($recentWinRate >= 60 ? 'success' : ($recentWinRate >= 50 ? 'warning' : 'danger')),
                
            Stat::make('Active Patterns', $currentStrategy ? count($currentStrategy->pattern_weights) : 0)
                ->description('Avoid list: ' . ($currentStrategy ? count($currentStrategy->avoid_setups) : 0))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('warning'),
        ];
    }
    
    protected function getWinRateChart(): array
    {
        // Get last 7 trades and calculate cumulative win rate
        $trades = Trade::orderBy('date', 'asc')->take(7)->get();
        $chart = [];
        
        foreach ($trades as $index => $trade) {
            $upToNow = Trade::orderBy('date', 'asc')->take($index + 1)->get();
            $wins = $upToNow->where('outcome', 'win')->count();
            $total = $upToNow->count();
            $chart[] = $total > 0 ? round(($wins / $total) * 100) : 0;
        }
        
        return $chart;
    }
    
    protected function getPnLChart(): array
    {
        // Get last 7 trades P&L
        return Trade::orderBy('date', 'desc')
            ->take(7)
            ->pluck('pnl_inr')
            ->reverse()
            ->map(fn ($value) => round($value))
            ->toArray();
    }
}

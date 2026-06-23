<?php

namespace App\Filament\Widgets;

use App\Models\Trade;
use Filament\Widgets\ChartWidget;

class TradingChart extends ChartWidget
{
    protected static ?string $heading = 'P&L Performance';
    
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        // Get last 30 trades for chart
        $trades = Trade::orderBy('date', 'asc')
            ->take(30)
            ->get();
            
        $cumulativePnL = 0;
        $labels = [];
        $data = [];
        
        foreach ($trades as $trade) {
            $cumulativePnL += $trade->pnl_inr;
            $labels[] = $trade->date->format('M d');
            $data[] = round($cumulativePnL, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Cumulative P&L (₹)',
                    'data' => $data,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

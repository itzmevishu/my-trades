<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Claude\ClaudeAPIService;
use App\Models\Trade;
use App\Models\ScanLog;
use App\Models\DailyReport;
use Carbon\Carbon;

/**
 * Trading Report Command
 * 
 * Generate daily/weekly/monthly trading reports with Claude AI insights.
 * 
 * Usage:
 *   php artisan trading:report daily
 *   php artisan trading:report weekly
 *   php artisan trading:report monthly
 */
class TradingReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'trading:report {type=daily : Report type (daily, weekly, monthly)}';

    /**
     * The console command description.
     */
    protected $description = 'Generate trading performance report';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');

        if (!in_array($type, ['daily', 'weekly', 'monthly'])) {
            $this->error('❌ Invalid report type. Use: daily, weekly, or monthly');
            return Command::FAILURE;
        }

        $this->info("📊 Generating {$type} trading report...");
        $this->newLine();

        try {
            // Get date range based on report type
            [$startDate, $endDate] = $this->getDateRange($type);

            $this->line("Period: {$startDate} to {$endDate}");
            $this->newLine();

            // Fetch scan logs for the period
            $scanLogs = ScanLog::whereBetween('scan_date', [$startDate, $endDate])
                ->orderBy('scan_date', 'asc')
                ->orderBy('scan_time', 'asc')
                ->get();

            // Display scan activity first
            if ($scanLogs->isNotEmpty()) {
                $this->displayScanActivity($scanLogs);
                $this->newLine();
            }

            // Fetch trades in date range
            $trades = Trade::where('status', 'closed')
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date', 'asc')
                ->get();

            if ($trades->isEmpty() && $scanLogs->isEmpty()) {
                $this->warn('⚠️  No activity found for this period');
                return Command::SUCCESS;
            }

            if ($trades->isEmpty()) {
                $this->warn('⚠️  No trades executed in this period (see scan activity above)');
                return Command::SUCCESS;
            }

            $this->info("Found {$trades->count()} trade(s)");
            $this->newLine();

            // Calculate statistics
            $stats = $this->calculateStatistics($trades);

            // Display statistics
            $this->displayStatistics($stats);

            // Generate Claude analysis
            $this->info('🤖 Generating Claude AI analysis...');
            $claudeService = new ClaudeAPIService();

            $tradesData = $trades->map(function($trade) {
                return [
                    'date' => $trade->date,
                    'candle_pattern' => $trade->candle_pattern,
                    'outcome' => $trade->outcome,
                    'pnl' => $trade->pnl_inr,
                    'rr_achieved' => $trade->rr_achieved
                ];
            })->toArray();

            $claudeAnalysis = $claudeService->generateReport($tradesData, $type);

            $this->newLine();
            $this->info('📝 Claude Analysis:');
            $this->newLine();
            $this->line($claudeAnalysis);
            $this->newLine();

            // Save report to database
            $this->saveReport($type, $stats, $claudeAnalysis);

            $this->info('✅ Report generated and saved successfully!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Report generation failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Get date range based on report type
     */
    private function getDateRange(string $type): array
    {
        $now = Carbon::now();

        switch ($type) {
            case 'daily':
                $startDate = $now->toDateString();
                $endDate = $now->toDateString();
                break;

            case 'weekly':
                $startDate = $now->startOfWeek()->toDateString();
                $endDate = $now->endOfWeek()->toDateString();
                break;

            case 'monthly':
                $startDate = $now->startOfMonth()->toDateString();
                $endDate = $now->endOfMonth()->toDateString();
                break;

            default:
                $startDate = $now->toDateString();
                $endDate = $now->toDateString();
        }

        return [$startDate, $endDate];
    }

    /**
     * Calculate trade statistics
     */
    private function calculateStatistics($trades): array
    {
        $totalTrades = $trades->count();
        $wins = $trades->where('outcome', 'win')->count();
        $losses = $trades->where('outcome', 'loss')->count();
        $breakevens = $trades->where('outcome', 'breakeven')->count();

        $winRate = $totalTrades > 0 ? round(($wins / $totalTrades) * 100, 1) : 0;
        $totalPnL = $trades->sum('pnl_inr');
        $avgPnL = $totalTrades > 0 ? round($totalPnL / $totalTrades, 2) : 0;

        $avgRR = $totalTrades > 0 ? round($trades->avg('rr_achieved'), 2) : 0;

        $bestTrade = $trades->sortByDesc('pnl_inr')->first();
        $worstTrade = $trades->sortBy('pnl_inr')->first();

        // Pattern breakdown
        $patternStats = [];
        foreach ($trades->groupBy('candle_pattern') as $pattern => $patternTrades) {
            $patternWins = $patternTrades->where('outcome', 'win')->count();
            $patternTotal = $patternTrades->count();
            $patternWinRate = $patternTotal > 0 ? round(($patternWins / $patternTotal) * 100, 1) : 0;

            $patternStats[$pattern] = [
                'count' => $patternTotal,
                'win_rate' => $patternWinRate,
                'total_pnl' => $patternTrades->sum('pnl_inr')
            ];
        }

        return [
            'total_trades' => $totalTrades,
            'wins' => $wins,
            'losses' => $losses,
            'breakevens' => $breakevens,
            'win_rate' => $winRate,
            'total_pnl' => $totalPnL,
            'avg_pnl' => $avgPnL,
            'avg_rr' => $avgRR,
            'best_trade' => $bestTrade,
            'worst_trade' => $worstTrade,
            'pattern_stats' => $patternStats
        ];
    }

    /**
     * Display statistics
     */
    private function displayStatistics(array $stats): void
    {
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Trades', $stats['total_trades']],
                ['Wins', $stats['wins']],
                ['Losses', $stats['losses']],
                ['Breakevens', $stats['breakevens']],
                ['Win Rate', $stats['win_rate'] . '%'],
                ['Total P&L', '₹' . number_format($stats['total_pnl'], 2)],
                ['Average P&L', '₹' . number_format($stats['avg_pnl'], 2)],
                ['Average R:R', $stats['avg_rr']],
            ]
        );
        $this->newLine();

        if ($stats['best_trade']) {
            $this->info("🏆 Best Trade: ₹" . number_format($stats['best_trade']->pnl_inr, 2) . 
                       " ({$stats['best_trade']->candle_pattern})");
        }

        if ($stats['worst_trade']) {
            $this->warn("📉 Worst Trade: ₹" . number_format($stats['worst_trade']->pnl_inr, 2) . 
                       " ({$stats['worst_trade']->candle_pattern})");
        }

        $this->newLine();

        // Pattern breakdown
        if (!empty($stats['pattern_stats'])) {
            $this->info('📊 Pattern Breakdown:');
            $this->newLine();

            $rows = [];
            foreach ($stats['pattern_stats'] as $pattern => $data) {
                $rows[] = [
                    $pattern,
                    $data['count'],
                    $data['win_rate'] . '%',
                    '₹' . number_format($data['total_pnl'], 2)
                ];
            }

            $this->table(
                ['Pattern', 'Trades', 'Win Rate', 'Total P&L'],
                $rows
            );
            $this->newLine();
        }
    }

    /**
     * Display scan activity statistics
     */
    private function displayScanActivity($scanLogs): void
    {
        $this->info('🔍 Scan Activity Analysis:');
        $this->newLine();

        $totalScans = $scanLogs->count();
        $tradesTaken = $scanLogs->where('result', 'trade_taken')->count();
        $noPattern = $scanLogs->where('result', 'no_pattern')->count();
        $rejectedEma = $scanLogs->where('result', 'rejected_ema')->count();
        $rejectedScore = $scanLogs->where('result', 'rejected_score')->count();
        $alreadyTraded = $scanLogs->where('result', 'already_traded')->count();
        $outsideWindow = $scanLogs->where('result', 'outside_window')->count();

        // Calculate percentages
        $getPercent = function($count) use ($totalScans) {
            return $totalScans > 0 ? round(($count / $totalScans) * 100, 1) : 0;
        };

        $this->table(
            ['Result', 'Count', 'Percentage'],
            [
                ['Total Scans', $totalScans, '100%'],
                ['✅ Trades Taken', $tradesTaken, $getPercent($tradesTaken) . '%'],
                ['❌ No Pattern Found', $noPattern, $getPercent($noPattern) . '%'],
                ['❌ EMA Confluence Failed', $rejectedEma, $getPercent($rejectedEma) . '%'],
                ['❌ Claude Score Too Low', $rejectedScore, $getPercent($rejectedScore) . '%'],
                ['⚠️  Already Traded', $alreadyTraded, $getPercent($alreadyTraded) . '%'],
                ['⏰ Outside Window', $outsideWindow, $getPercent($outsideWindow) . '%'],
            ]
        );
        $this->newLine();

        // Show pattern detection breakdown
        $patternsDetected = $scanLogs->whereNotNull('pattern_detected');
        if ($patternsDetected->isNotEmpty()) {
            $this->info('📊 Patterns Detected (including rejected):');
            $this->newLine();

            $patternBreakdown = [];
            foreach ($patternsDetected->groupBy('pattern_detected') as $pattern => $logs) {
                $avgEmaConfluence = round($logs->avg('ema_confluence_count'), 1);
                $avgClaudeScore = $logs->whereNotNull('claude_score')->isNotEmpty() 
                    ? round($logs->whereNotNull('claude_score')->avg('claude_score'), 1) 
                    : 'N/A';
                
                $patternBreakdown[] = [
                    ucwords(str_replace('_', ' ', $pattern)),
                    $logs->count(),
                    $avgEmaConfluence,
                    $avgClaudeScore
                ];
            }

            $this->table(
                ['Pattern', 'Detected', 'Avg EMA Confluence', 'Avg Claude Score'],
                $patternBreakdown
            );
            $this->newLine();
        }

        // Show top rejection reasons for context
        if ($rejectedEma > 0 || $rejectedScore > 0) {
            $this->info('🔍 Why Trades Were Rejected:');
            $this->newLine();
            
            $rejectedLogs = $scanLogs->whereIn('result', ['rejected_ema', 'rejected_score'])->take(5);
            foreach ($rejectedLogs as $log) {
                $this->line("  [{$log->scan_time}] {$log->pattern_detected} - {$log->rejection_reason}");
            }
            if ($scanLogs->whereIn('result', ['rejected_ema', 'rejected_score'])->count() > 5) {
                $this->line('  ... and ' . ($scanLogs->whereIn('result', ['rejected_ema', 'rejected_score'])->count() - 5) . ' more');
            }
            $this->newLine();
        }
    }

    /**
     * Save report to database
     */
    private function saveReport(string $type, array $stats, string $claudeAnalysis): void
    {
        DailyReport::create([
            'report_type' => $type,
            'market_context' => 'Bank Nifty Options Trading',
            'trade_outcome' => "{$stats['wins']} wins, {$stats['losses']} losses, {$stats['breakevens']} breakevens",
            'claude_analysis' => $claudeAnalysis,
            'pnl_summary' => [
                'total_trades' => $stats['total_trades'],
                'win_rate' => $stats['win_rate'],
                'total_pnl' => $stats['total_pnl'],
                'avg_pnl' => $stats['avg_pnl'],
                'avg_rr' => $stats['avg_rr'],
                'pattern_stats' => $stats['pattern_stats']
            ]
        ]);
    }
}

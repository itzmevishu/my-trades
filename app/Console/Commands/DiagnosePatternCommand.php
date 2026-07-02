<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Fyers\FyersDataService;
use App\Services\Analysis\PriceActionAnalyzer;
use App\Models\ScanLog;
use Carbon\Carbon;

/**
 * Diagnose Price Action Analysis
 *
 * Deep diagnostic tool for the PriceActionAnalyzer engine. Instead of checking
 * rigid candlestick definitions, it shows how the engine *reads the market*:
 * trend, market structure, location, momentum, volatility, behavioural events
 * and the final confluence-based assessment with human reasoning.
 *
 * Usage:
 *   php artisan trading:diagnose
 *   php artisan trading:diagnose --date=2026-06-30
 *   php artisan trading:diagnose --candles=8
 */
class DiagnosePatternCommand extends Command
{
    protected $signature = 'trading:diagnose {--date= : Date to analyze (YYYY-MM-DD format)} {--time= : Specific time to analyze (HH:MM format)} {--candles=5 : Number of recent candles to display}';

    protected $description = 'Deep diagnostic analysis of the price action engine';

    public function handle(): int
    {
        $this->info('🔍 Price Action Engine Diagnostic');
        $this->newLine();

        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $time = $this->option('time');
        $numCandles = (int) $this->option('candles');

        $this->line('📅 Analysis Date: ' . $date->format('Y-m-d'));
        if ($time) {
            $this->line('⏰ Analysis Time: ' . $time);
        }
        $this->newLine();

        $this->showScanLogs($date);

        // Fetch market data
        $this->info('📊 Fetching Real Market Data from Fyers API...');

        try {
            $fyersData = new FyersDataService();
            $candles = $fyersData->fetchCandles('NSE:NIFTYBANK-INDEX', '15', 250);
            $this->info('✅ Successfully fetched real market data');
        } catch (\Exception $e) {
            $this->error('❌ Failed to fetch market data: ' . $e->getMessage());
            $this->newLine();
            $this->line('💡 To fix this:');
            $this->line('1. Authenticate with Fyers: Visit /fyers/auth in your browser');
            $this->line('2. Make sure Fyers credentials are configured in .env');
            $this->line('3. Check if access token has expired');
            return Command::FAILURE;
        }

        if (!$candles || count($candles) < 2) {
            $this->error('❌ Insufficient candle data');
            return Command::FAILURE;
        }

        $this->line('Candles loaded: ' . count($candles));
        $this->newLine();

        $this->showRecentCandles($candles, $numCandles);

        // Run the price action engine
        $this->info('🎯 Running Price Action Analysis...');
        $this->newLine();

        $engine = new PriceActionAnalyzer();
        $analysis = $engine->analyze($candles);

        $this->showAssessment($analysis);
        $this->showAnalyzerBreakdown($analysis);
        $this->showEvents($analysis);

        $this->newLine();
        $this->info('💡 Next Steps:');
        $this->line('1. Review the reasoning narrative — it explains the trade story.');
        $this->line('2. Check trend + structure first; they frame everything else.');
        $this->line('3. Tune adaptive parameters via settings (pa_* keys) if needed.');
        $this->line('4. Run: php artisan trading:scan --verbose for live debugging.');

        return Command::SUCCESS;
    }

    private function showScanLogs(Carbon $date): void
    {
        $this->info('📋 Today\'s Scan Logs:');

        $logs = ScanLog::whereDate('scan_date', $date->toDateString())
            ->orderBy('scan_time')
            ->get();

        if ($logs->isEmpty()) {
            $this->warn('  No scans recorded today');
            $this->newLine();
            return;
        }

        $table = [];
        foreach ($logs as $log) {
            $table[] = [
                Carbon::parse($log->scan_time)->format('H:i:s'),
                $log->result,
                $log->pattern_detected ?? '-',
                $log->pattern_direction ?? '-',
                $log->current_price ? '₹' . number_format($log->current_price, 2) : '-',
                substr($log->rejection_reason ?? '-', 0, 50) . '...',
            ];
        }

        $this->table(
            ['Time', 'Result', 'Signal', 'Direction', 'Price', 'Reason'],
            $table
        );
        $this->newLine();
    }

    private function showRecentCandles(array $candles, int $count): void
    {
        $this->info("📈 Recent {$count} Candles:");

        $recent = array_slice($candles, -$count);
        $table = [];

        foreach ($recent as $i => $candle) {
            $isGreen = $candle['close'] > $candle['open'];
            $body = abs($candle['close'] - $candle['open']);
            $upperWick = $candle['high'] - max($candle['open'], $candle['close']);
            $lowerWick = min($candle['open'], $candle['close']) - $candle['low'];

            $table[] = [
                count($recent) - $count + $i,
                $isGreen ? '🟢' : '🔴',
                number_format($candle['open'], 2),
                number_format($candle['high'], 2),
                number_format($candle['low'], 2),
                number_format($candle['close'], 2),
                number_format($body, 2),
                number_format($upperWick, 2),
                number_format($lowerWick, 2),
            ];
        }

        $this->table(
            ['#', 'Color', 'Open', 'High', 'Low', 'Close', 'Body', 'UWick', 'LWick'],
            $table
        );
        $this->newLine();
    }

    /**
     * Show the final confluence-based assessment.
     */
    private function showAssessment(array $analysis): void
    {
        $confidence = $analysis['confidence'];
        $color = match (true) {
            $confidence >= 80 => 'green',
            $confidence >= 70 => 'cyan',
            $confidence >= 60 => 'yellow',
            default => 'red',
        };

        $this->line("📊 <fg={$color}>Confidence: {$confidence}/100 (Grade: {$analysis['grade']})</>");
        $this->line('📈 Direction: ' . ucfirst($analysis['direction']));
        $this->line("🎯 <fg={$color}>{$analysis['recommendation']}</>");
        $this->line("🧭 Frame agreement: {$analysis['frame_agreement']}");
        $this->newLine();

        $trend = $analysis['trend'];
        $structure = $analysis['market_structure'];
        $sr = $analysis['support_resistance'];

        $this->table(['Dimension', 'Read'], [
            ['Trend', ucfirst($trend['direction']) . " / {$trend['strength']} ({$trend['state']})"],
            ['Structure', $structure['state']
                . ($structure['bos'] ? ' • BOS' : '')
                . ($structure['choch'] ? ' • CHoCH' : '')
                . (!empty($structure['pullback']) ? ' • pullback' : '')
                . (!empty($structure['retest']) ? ' • retest' : '')],
            ['Location', ($sr['support'] ? "support {$sr['support']}" : ($sr['resistance'] ? "resistance {$sr['resistance']}" : 'open space')) . " (reaction: {$sr['reaction']})"],
            ['Momentum', ucfirst($analysis['momentum']['direction'] ?? 'neutral')],
            ['Volatility', $analysis['volatility']],
            ['Candlestick', empty($analysis['candlestick_confirmation']) ? 'none' : implode(', ', $analysis['candlestick_confirmation'])],
        ]);
        $this->newLine();

        $this->info('🧠 Reasoning:');
        $this->line('  ' . wordwrap($analysis['reasoning'], 100, "\n  "));
        $this->newLine();
    }

    /**
     * Show each analyzer's independent read (direction + conviction).
     */
    private function showAnalyzerBreakdown(array $analysis): void
    {
        if (empty($analysis['analyzers'])) {
            return;
        }

        $this->info('🔬 Analyzer Breakdown:');
        $table = [];
        foreach ($analysis['analyzers'] as $key => $res) {
            $table[] = [
                ucwords(str_replace('_', ' ', $key)),
                ucfirst($res['direction']),
                number_format($res['strength'] * 100, 0) . '%',
                empty($res['observations']) ? '-' : implode(', ', array_slice($res['observations'], 0, 4)),
            ];
        }

        $this->table(['Analyzer', 'Bias', 'Conviction', 'Observations'], $table);
        $this->newLine();
    }

    /**
     * Show the behavioural events detected (the "story", not candle names).
     */
    private function showEvents(array $analysis): void
    {
        $this->info('⚡ Behavioural Events:');
        if (empty($analysis['price_action'])) {
            $this->warn('  No significant price-action events on the latest candle.');
            $this->newLine();
            return;
        }

        foreach ($analysis['price_action'] as $event) {
            $this->line('  • ' . str_replace('_', ' ', $event));
        }
        $this->newLine();
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Fyers\FyersDataService;
use App\Services\Analysis\PatternDetector;
use App\Services\Analysis\EMACalculator;
use App\Services\Analysis\AdvancedPatternScorer;
use App\Models\ScanLog;
use Carbon\Carbon;

/**
 * Diagnose Pattern Detection Issues
 * 
 * Deep analysis tool to understand why patterns were missed.
 * Shows detailed candle data, pattern checks, EMA values, and rejection reasons.
 * 
 * Usage: 
 *   php artisan trading:diagnose           # Today's data
 *   php artisan trading:diagnose --date=2026-06-30
 *   php artisan trading:diagnose --time=14:30  # Specific time today
 */
class DiagnosePatternCommand extends Command
{
    protected $signature = 'trading:diagnose {--date= : Date to analyze (YYYY-MM-DD format)} {--time= : Specific time to analyze (HH:MM format)} {--candles=5 : Number of recent candles to display} {--scan-history : Scan last 3 days for missed patterns}';

    protected $description = 'Deep diagnostic analysis of pattern detection';

    public function handle(): int
    {
        $this->info('🔍 Pattern Detection Diagnostic');
        $this->newLine();

        // Parse date/time
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $time = $this->option('time');
        $numCandles = (int) $this->option('candles');

        $this->line("📅 Analysis Date: " . $date->format('Y-m-d'));
        if ($time) {
            $this->line("⏰ Analysis Time: " . $time);
        }
        $this->newLine();

        // Show scan logs for today
        $this->showScanLogs($date);

        // Get market data
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

        $this->line("Candles loaded: " . count($candles));
        $this->newLine();

        // Show recent candles
        $this->showRecentCandles($candles, $numCandles);

        // Run pattern detection with detailed logging
        $this->info('🎯 Running Pattern Detection...');
        $this->newLine();
        
        $patternDetector = new PatternDetector();
        $pattern = $patternDetector->detectPattern($candles);

        if ($pattern) {
            $this->info("✅ PATTERN DETECTED: " . strtoupper(str_replace('_', ' ', $pattern)));
            $direction = $this->getPatternDirection($pattern);
            $this->line("Direction: " . strtoupper($direction));
        } else {
            $this->warn("⚠️  NO PATTERN DETECTED");
        }
        $this->newLine();

        // Show detailed rejection reasons
        $this->showRejectionReasons($patternDetector);

        // Show EMA analysis
        $this->showEMAAnalysis($candles);

        // Run Advanced Pattern Scorer (NEW - Sophisticated Scoring)
        $this->newLine();
        $this->info('🎯 Advanced Pattern Scoring (Weighted System):');
        $this->runAdvancedScoring($candles);

        // Run individual pattern checks
        $this->runDetailedPatternChecks($candles);

        // Historical scan if requested
        if ($this->option('scan-history')) {
            $this->newLine();
            $this->info('📅 Scanning Last 3 Days for Bearish Patterns...');
            $this->scanHistoricalPatterns($candles);
        }

        // Suggestions
        $this->newLine();
        $this->info('💡 Next Steps:');
        $this->line('1. Review the rejection reasons above');
        $this->line('2. Check if pattern criteria are too strict');
        $this->line('3. Verify candle data quality (OHLC values)');
        $this->line('4. Consider adjusting thresholds in PatternDetector.php');
        $this->line('5. Run with --scan-history to find all patterns in last 3 days');
        $this->line('6. Run: php artisan trading:scan --verbose for live debugging');

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
            ['Time', 'Result', 'Pattern', 'Direction', 'Price', 'Rejection Reason'],
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

    private function showRejectionReasons(PatternDetector $detector): void
    {
        $reasons = $detector->getRejectionReasons();
        
        if (empty($reasons)) {
            $this->line('No rejection reasons available (all patterns passed initial checks)');
            return;
        }

        $this->warn('🚫 Rejection Reasons:');
        foreach ($reasons as $reason) {
            $this->line("  • " . $reason);
        }
        $this->newLine();
    }

    private function showEMAAnalysis(array $candles): void
    {
        $this->info('📊 EMA Analysis:');
        
        $emaCalc = new EMACalculator();
        $emas = $emaCalc->calculateMultipleEMAs($candles);
        $currentPrice = $candles[count($candles) - 1]['close'];

        $ema20Dist = (($currentPrice - $emas['ema_20']) / $emas['ema_20']) * 100;
        $ema100Dist = (($currentPrice - $emas['ema_100']) / $emas['ema_100']) * 100;
        $ema200Dist = (($currentPrice - $emas['ema_200']) / $emas['ema_200']) * 100;

        $table = [
            ['Current Price', '₹' . number_format($currentPrice, 2), '-', '-'],
            ['20 EMA', '₹' . number_format($emas['ema_20'], 2), number_format($ema20Dist, 2) . '%', $this->getEMAStatus($ema20Dist)],
            ['100 EMA', '₹' . number_format($emas['ema_100'], 2), number_format($ema100Dist, 2) . '%', $this->getEMAStatus($ema100Dist)],
            ['200 EMA', '₹' . number_format($emas['ema_200'], 2), number_format($ema200Dist, 2) . '%', $this->getEMAStatus($ema200Dist)],
        ];

        $this->table(['Indicator', 'Value', 'Distance', 'Status'], $table);

        // EMA Confluence
        $confluence = 0;
        if (abs($ema20Dist) <= 3) $confluence++;
        if (abs($ema100Dist) <= 3) $confluence++;
        if (abs($ema200Dist) <= 3) $confluence++;

        $this->line("EMA Confluence: {$confluence}/3 EMAs within 3% of price");
        $this->newLine();
    }

    private function getEMAStatus(float $distance): string
    {
        $abs = abs($distance);
        if ($abs <= 1) return '✅ Very Close';
        if ($abs <= 3) return '🟡 Close';
        if ($abs <= 5) return '🟠 Medium';
        return '🔴 Far';
    }

    private function runDetailedPatternChecks(array $candles): void
    {
        if (count($candles) < 2) return;

        $this->info('🔬 Detailed Pattern Checks:');
        $this->newLine();

        $current = end($candles);
        $previous = $candles[count($candles) - 2];

        // Bearish Engulfing Check
        $this->line('🔻 Bearish Engulfing:');
        $this->checkBearishEngulfing($current, $previous);
        $this->newLine();

        // Bullish Engulfing Check
        $this->line('🔺 Bullish Engulfing:');
        $this->checkBullishEngulfing($current, $previous);
        $this->newLine();

        // Bearish Pinbar Check
        $this->line('🔻 Bearish Pinbar (Shooting Star):');
        $this->checkBearishPinbar($current);
        $this->newLine();

        // Bullish Pinbar Check
        $this->line('🔺 Bullish Pinbar (Hammer):');
        $this->checkBullishPinbar($current);
        $this->newLine();
    }

    private function checkBearishEngulfing(array $current, array $previous): void
    {
        $checks = [];

        // Check 1: Previous bullish
        $prevBullish = $previous['close'] > $previous['open'];
        $checks[] = [
            'Previous candle is bullish',
            $prevBullish ? '✅' : '❌',
            "C:" . round($previous['close'], 2) . " vs O:" . round($previous['open'], 2)
        ];

        // Check 2: Current bearish
        $curBearish = $current['close'] < $current['open'];
        $checks[] = [
            'Current candle is bearish',
            $curBearish ? '✅' : '❌',
            "C:" . round($current['close'], 2) . " vs O:" . round($current['open'], 2)
        ];

        // Check 3: Current engulfs previous
        $currentBodyTop = max($current['open'], $current['close']);
        $currentBodyBottom = min($current['open'], $current['close']);
        $previousBodyTop = max($previous['open'], $previous['close']);
        $previousBodyBottom = min($previous['open'], $previous['close']);
        
        $engulfs = $currentBodyTop > $previousBodyTop && $currentBodyBottom < $previousBodyBottom;
        $checks[] = [
            'Current body engulfs previous',
            $engulfs ? '✅' : '❌',
            "Cur:" . round($currentBodyBottom, 2) . "-" . round($currentBodyTop, 2) . " vs Prev:" . round($previousBodyBottom, 2) . "-" . round($previousBodyTop, 2)
        ];

        $this->table(['Check', 'Status', 'Details'], $checks);
    }

    private function checkBullishEngulfing(array $current, array $previous): void
    {
        $checks = [];

        // Check 1: Previous bearish
        $prevBearish = $previous['close'] < $previous['open'];
        $checks[] = [
            'Previous candle is bearish',
            $prevBearish ? '✅' : '❌',
            "C:" . round($previous['close'], 2) . " vs O:" . round($previous['open'], 2)
        ];

        // Check 2: Current bullish
        $curBullish = $current['close'] > $current['open'];
        $checks[] = [
            'Current candle is bullish',
            $curBullish ? '✅' : '❌',
            "C:" . round($current['close'], 2) . " vs O:" . round($current['open'], 2)
        ];

        // Check 3: Current engulfs previous
        $currentBodyTop = max($current['open'], $current['close']);
        $currentBodyBottom = min($current['open'], $current['close']);
        $previousBodyTop = max($previous['open'], $previous['close']);
        $previousBodyBottom = min($previous['open'], $previous['close']);
        
        $engulfs = $currentBodyTop > $previousBodyTop && $currentBodyBottom < $previousBodyBottom;
        $checks[] = [
            'Current body engulfs previous',
            $engulfs ? '✅' : '❌',
            "Cur:" . round($currentBodyBottom, 2) . "-" . round($currentBodyTop, 2) . " vs Prev:" . round($previousBodyBottom, 2) . "-" . round($previousBodyTop, 2)
        ];

        $this->table(['Check', 'Status', 'Details'], $checks);
    }

    private function checkBearishPinbar(array $current): void
    {
        $body = abs($current['close'] - $current['open']);
        $upperWick = $current['high'] - max($current['open'], $current['close']);
        $lowerWick = min($current['open'], $current['close']) - $current['low'];
        $totalRange = $current['high'] - $current['low'];

        $checks = [];

        // Check 1: Bearish candle
        $isBearish = $current['close'] < $current['open'];
        $checks[] = [
            'Candle is bearish',
            $isBearish ? '✅' : '❌',
            "C:" . round($current['close'], 2) . " vs O:" . round($current['open'], 2)
        ];

        // Check 2: Body not too small
        $bodyPct = $totalRange > 0 ? ($body / $totalRange) * 100 : 0;
        $bodyOk = $body >= $totalRange * 0.1;
        $checks[] = [
            'Body size adequate (>10% of range)',
            $bodyOk ? '✅' : '❌',
            round($bodyPct, 1) . "% of range"
        ];

        // Check 3: Long upper wick (2x body)
        $upperWickRatio = $body > 0 ? $upperWick / $body : 0;
        $upperWickOk = $upperWick >= $body * 2;
        $checks[] = [
            'Upper wick is long (≥2x body)',
            $upperWickOk ? '✅' : '❌',
            round($upperWickRatio, 2) . "x body size"
        ];

        // Check 4: Small lower wick (<0.5x body)
        $lowerWickRatio = $body > 0 ? $lowerWick / $body : 0;
        $lowerWickOk = $lowerWick <= $body * 0.5;
        $checks[] = [
            'Lower wick is small (≤0.5x body)',
            $lowerWickOk ? '✅' : '❌',
            round($lowerWickRatio, 2) . "x body size"
        ];

        // Check 5: Body in lower portion
        $bodyPosition = $totalRange > 0 ? (max($current['open'], $current['close']) - $current['low']) / $totalRange : 0;
        $positionOk = $bodyPosition <= 0.33;
        $checks[] = [
            'Body in lower 33% of range',
            $positionOk ? '✅' : '❌',
            round($bodyPosition * 100, 1) . "% from bottom"
        ];

        $this->table(['Check', 'Status', 'Details'], $checks);
    }

    private function checkBullishPinbar(array $current): void
    {
        $body = abs($current['close'] - $current['open']);
        $upperWick = $current['high'] - max($current['open'], $current['close']);
        $lowerWick = min($current['open'], $current['close']) - $current['low'];
        $totalRange = $current['high'] - $current['low'];

        $checks = [];

        // Check 1: Bullish candle
        $isBullish = $current['close'] > $current['open'];
        $checks[] = [
            'Candle is bullish',
            $isBullish ? '✅' : '❌',
            "C:" . round($current['close'], 2) . " vs O:" . round($current['open'], 2)
        ];

        // Check 2: Body not too small
        $bodyPct = $totalRange > 0 ? ($body / $totalRange) * 100 : 0;
        $bodyOk = $body >= $totalRange * 0.1;
        $checks[] = [
            'Body size adequate (>10% of range)',
            $bodyOk ? '✅' : '❌',
            round($bodyPct, 1) . "% of range"
        ];

        // Check 3: Long lower wick (2x body)
        $lowerWickRatio = $body > 0 ? $lowerWick / $body : 0;
        $lowerWickOk = $lowerWick >= $body * 2;
        $checks[] = [
            'Lower wick is long (≥2x body)',
            $lowerWickOk ? '✅' : '❌',
            round($lowerWickRatio, 2) . "x body size"
        ];

        // Check 4: Small upper wick (<0.5x body)
        $upperWickRatio = $body > 0 ? $upperWick / $body : 0;
        $upperWickOk = $upperWick <= $body * 0.5;
        $checks[] = [
            'Upper wick is small (≤0.5x body)',
            $upperWickOk ? '✅' : '❌',
            round($upperWickRatio, 2) . "x body size"
        ];

        // Check 5: Body in upper portion
        $bodyPosition = $totalRange > 0 ? (max($current['open'], $current['close']) - $current['low']) / $totalRange : 0;
        $positionOk = $bodyPosition >= 0.66;
        $checks[] = [
            'Body in upper 66% of range',
            $positionOk ? '✅' : '❌',
            round($bodyPosition * 100, 1) . "% from bottom"
        ];

        $this->table(['Check', 'Status', 'Details'], $checks);
    }

    private function scanHistoricalPatterns(array $candles): void
    {
        // We have 250 candles (15-min each), covering last ~2.6 days of trading
        // Scan through all windows to find patterns
        $patternDetector = new PatternDetector();
        $foundPatterns = [];
        
        // Start from candle 200 onwards (last ~50 candles = ~12 hours)
        $startIdx = max(0, count($candles) - 288); // Last 3 days worth (3*24*60/15 = 288 candles)
        
        $this->line("Analyzing " . (count($candles) - $startIdx) . " candles (last 3 trading days)...");
        $this->newLine();
        
        for ($i = $startIdx; $i < count($candles); $i++) {
            if ($i < 2) continue; // Need at least 2 candles for pattern detection
            
            // Get window of candles up to current position
            $window = array_slice($candles, max(0, $i - 50), $i + 1);
            
            // Detect pattern at this point
            $pattern = $patternDetector->detectPattern($window);
            
            if ($pattern) {
                $direction = $this->getPatternDirection($pattern);
                $candle = $candles[$i];
                
                $foundPatterns[] = [
                    'index' => $i,
                    'pattern' => $pattern,
                    'direction' => $direction,
                    'candle' => $candle,
                ];
            }
        }
        
        // Display found patterns
        if (empty($foundPatterns)) {
            $this->warn('  No patterns detected in the last 3 days of data');
            return;
        }
        
        $this->info("✅ Found " . count($foundPatterns) . " patterns in historical data:");
        $this->newLine();
        
        // Group by pattern type
        $bearishPatterns = array_filter($foundPatterns, fn($p) => $p['direction'] === 'bearish');
        $bullishPatterns = array_filter($foundPatterns, fn($p) => $p['direction'] === 'bullish');
        $neutralPatterns = array_filter($foundPatterns, fn($p) => $p['direction'] === 'neutral');
        
        if (!empty($bearishPatterns)) {
            $this->line('🔻 BEARISH PATTERNS (' . count($bearishPatterns) . '):');
            $table = [];
            foreach ($bearishPatterns as $p) {
                $table[] = [
                    $p['index'],
                    str_replace('_', ' ', ucwords($p['pattern'])),
                    number_format($p['candle']['open'], 2),
                    number_format($p['candle']['high'], 2),
                    number_format($p['candle']['low'], 2),
                    number_format($p['candle']['close'], 2),
                ];
            }
            $this->table(['Candle #', 'Pattern', 'Open', 'High', 'Low', 'Close'], $table);
            $this->newLine();
        }
        
        if (!empty($bullishPatterns)) {
            $this->line('🔺 BULLISH PATTERNS (' . count($bullishPatterns) . '):');
            $table = [];
            foreach ($bullishPatterns as $p) {
                $table[] = [
                    $p['index'],
                    str_replace('_', ' ', ucwords($p['pattern'])),
                    number_format($p['candle']['open'], 2),
                    number_format($p['candle']['high'], 2),
                    number_format($p['candle']['low'], 2),
                    number_format($p['candle']['close'], 2),
                ];
            }
            $this->table(['Candle #', 'Pattern', 'Open', 'High', 'Low', 'Close'], $table);
            $this->newLine();
        }
        
        if (!empty($neutralPatterns)) {
            $this->line('⚪ NEUTRAL PATTERNS (' . count($neutralPatterns) . '):');
            $table = [];
            foreach ($neutralPatterns as $p) {
                $table[] = [
                    $p['index'],
                    str_replace('_', ' ', ucwords($p['pattern'])),
                    number_format($p['candle']['open'], 2),
                    number_format($p['candle']['high'], 2),
                    number_format($p['candle']['low'], 2),
                    number_format($p['candle']['close'], 2),
                ];
            }
            $this->table(['Candle #', 'Pattern', 'Open', 'High', 'Low', 'Close'], $table);
            $this->newLine();
        }
        
        // Summary
        $this->info('📊 Pattern Distribution:');
        $this->line('  🔻 Bearish: ' . count($bearishPatterns));
        $this->line('  🔺 Bullish: ' . count($bullishPatterns));
        $this->line('  ⚪ Neutral: ' . count($neutralPatterns));
    }

    private function getPatternDirection(string $pattern): string
    {
        if (str_contains($pattern, 'bullish')) return 'bullish';
        if (str_contains($pattern, 'bearish')) return 'bearish';
        return 'neutral';
    }

    private function runAdvancedScoring(array $candles): void
    {
        $scorer = new AdvancedPatternScorer();
        $result = $scorer->scoreSetup($candles);

        // Display score with valid colors
        $scoreColor = match(true) {
            $result['score'] >= 90 => 'green',
            $result['score'] >= 80 => 'cyan',
            $result['score'] >= 70 => 'yellow',
            $result['score'] >= 60 => 'yellow',
            default => 'red'
        };

        $this->newLine();
        $this->line("📊 <fg={$scoreColor}>Final Score: {$result['score']}/100 (Grade: {$result['grade']})</>");
        $this->line("🎯 <fg={$scoreColor}>{$result['recommendation']}</>");
        $this->line("📈 Direction: " . ucfirst($result['direction']));
        $this->newLine();

        // Show breakdown
        $this->line('💯 Score Breakdown:');
        foreach ($result['breakdown'] as $category => $detail) {
            $this->line("   " . $detail);
        }
        
        $this->newLine();
        
        // Interpretation
        if ($result['score'] >= 80) {
            $this->info('✅ Excellent setup - High probability trade');
        } elseif ($result['score'] >= 70) {
            $this->comment('🟡 Good setup - Consider entry with tight SL');
        } elseif ($result['score'] >= 60) {
            $this->comment('⚠️  Marginal setup - Watchlist only');
        } else {
            $this->error('❌ Low probability - Skip this setup');
        }
    }
}


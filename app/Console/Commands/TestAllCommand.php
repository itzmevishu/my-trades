<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Trade;
use App\Models\StrategyConfig;
use App\Models\Setting;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive System Validation
 * 
 * Tests all major features:
 * - Database connectivity
 * - Redis connectivity
 * - Model relationships
 * - Data integrity
 * - Services availability
 * - Configuration validity
 * 
 * Usage: php artisan test:all
 */
class TestAllCommand extends Command
{
    protected $signature = 'test:all {--show-passed : Show all passed tests}';
    protected $description = 'Run comprehensive system validation tests';
    
    private int $passedTests = 0;
    private int $failedTests = 0;
    private array $errors = [];

    public function handle(): int
    {
        $this->info('🧪 Running Comprehensive System Tests...');
        $this->newLine();
        
        // Run all test suites
        $this->testDatabaseConnectivity();
        $this->testRedisConnectivity();
        $this->testModels();
        $this->testDataIntegrity();
        $this->testServices();
        $this->testConfiguration();
        $this->testScheduler();
        
        // Display results
        $this->displayResults();
        
        return $this->failedTests === 0 ? Command::SUCCESS : Command::FAILURE;
    }
    
    private function testDatabaseConnectivity(): void
    {
        $this->section('DATABASE CONNECTIVITY');
        
        try {
            DB::connection()->getPdo();
            $this->passTest('Database connection established');
            
            $tables = ['trades', 'strategy_configs', 'settings', 'daily_reports'];
            foreach ($tables as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $this->passTest("Table '{$table}' exists");
                } else {
                    $this->failTest("Table '{$table}' missing");
                }
            }
        } catch (\Exception $e) {
            $this->failTest('Database connection failed: ' . $e->getMessage());
        }
    }
    
    private function testRedisConnectivity(): void
    {
        $this->section('REDIS CONNECTIVITY');
        
        // Check if Redis extension is loaded
        if (!extension_loaded('redis')) {
            $this->warnTest('Redis PHP extension not installed (acceptable on local dev)');
            $this->warnTest('Install with: pecl install redis');
            return;
        }
        
        try {
            $response = Redis::ping();
            if ($response === 'PONG' || $response === '+PONG') {
                $this->passTest('Redis connection established');
            } else {
                $this->failTest('Redis ping returned unexpected response');
            }
            
            // Test set/get
            Redis::set('test:key', 'test_value', 'EX', 10);
            $value = Redis::get('test:key');
            if ($value === 'test_value') {
                $this->passTest('Redis read/write operations working');
                Redis::del('test:key');
            } else {
                $this->failTest('Redis read/write operations failed');
            }
        } catch (\Exception $e) {
            $this->failTest('Redis connection failed: ' . $e->getMessage());
        }
    }
    
    private function testModels(): void
    {
        $this->section('MODEL VALIDATION');
        
        // Test Trade model
        try {
            $trade = Trade::first();
            if ($trade) {
                $this->passTest('Trade model functional');
                
                // Test fillable fields
                $fillable = ['date', 'direction', 'instrument', 'strike', 'candle_pattern', 'outcome'];
                foreach ($fillable as $field) {
                    if ($trade->$field !== null) {
                        $this->passTest("Trade field '{$field}' accessible");
                    }
                }
            } else {
                $this->warnTest('No trades in database (expected if fresh install)');
            }
        } catch (\Exception $e) {
            $this->failTest('Trade model error: ' . $e->getMessage());
        }
        
        // Test StrategyConfig model
        try {
            $strategy = StrategyConfig::where('is_active', true)->first();
            if ($strategy) {
                $this->passTest('StrategyConfig model functional');
                
                // Test JSON casting
                if (is_array($strategy->pattern_weights)) {
                    $this->passTest('Pattern weights JSON casting working');
                } else {
                    $this->failTest('Pattern weights JSON casting failed');
                }
                
                if (is_array($strategy->avoid_setups)) {
                    $this->passTest('Avoid setups JSON casting working');
                } else {
                    $this->failTest('Avoid setups JSON casting failed');
                }
            } else {
                $this->warnTest('No active strategy (expected if fresh install)');
            }
        } catch (\Exception $e) {
            $this->failTest('StrategyConfig model error: ' . $e->getMessage());
        }
        
        // Test Setting model
        try {
            Setting::firstOrCreate(
                ['key' => 'test_key'],
                ['value' => 'test_value', 'description' => 'Test setting']
            );
            $this->passTest('Setting model functional');
            Setting::where('key', 'test_key')->delete();
        } catch (\Exception $e) {
            $this->failTest('Setting model error: ' . $e->getMessage());
        }
    }
    
    private function testDataIntegrity(): void
    {
        $this->section('DATA INTEGRITY');
        
        // Check for trades with required fields
        $invalidTrades = Trade::whereNull('date')
            ->orWhereNull('direction')
            ->orWhereNull('candle_pattern')
            ->count();
        
        if ($invalidTrades === 0) {
            $this->passTest('All trades have required fields');
        } else {
            $this->failTest("{$invalidTrades} trades missing required fields");
        }
        
        // Check for valid outcomes
        $invalidOutcomes = Trade::whereNotIn('outcome', ['win', 'loss', 'breakeven'])
            ->whereNotNull('outcome')
            ->count();
        
        if ($invalidOutcomes === 0) {
            $this->passTest('All trade outcomes are valid');
        } else {
            $this->failTest("{$invalidOutcomes} trades have invalid outcomes");
        }
        
        // Check for valid directions
        $invalidDirections = Trade::whereNotIn('direction', ['long', 'short'])
            ->count();
        
        if ($invalidDirections === 0) {
            $this->passTest('All trade directions are valid');
        } else {
            $this->failTest("{$invalidDirections} trades have invalid directions");
        }
        
        // Check strategy version uniqueness
        $duplicateVersions = StrategyConfig::select('version')
            ->groupBy('version')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        
        if ($duplicateVersions === 0) {
            $this->passTest('All strategy versions are unique');
        } else {
            $this->failTest("Found {$duplicateVersions} duplicate strategy versions");
        }
        
        // Check only one active strategy
        $activeStrategies = StrategyConfig::where('is_active', true)->count();
        
        if ($activeStrategies === 1) {
            $this->passTest('Exactly one active strategy');
        } elseif ($activeStrategies === 0) {
            $this->warnTest('No active strategy (expected if fresh install)');
        } else {
            $this->failTest("Multiple active strategies ({$activeStrategies})");
        }
    }
    
    private function testServices(): void
    {
        $this->section('SERVICE AVAILABILITY');
        
        $services = [
            'App\Services\Learning\LearningEngine',
            'App\Services\Trading\PaperTradingService',
            'App\Services\Trading\RiskEngine',
            'App\Services\Claude\ClaudeAPIService',
            'App\Services\Analysis\PriceActionAnalyzer',
            'App\Services\Analysis\EMACalculator',
            'App\Services\Fyers\FyersAuthService',
            'App\Services\Fyers\FyersDataService',
        ];
        
        foreach ($services as $service) {
            try {
                $instance = app($service);
                $this->passTest(class_basename($service) . ' service available');
            } catch (\Exception $e) {
                $this->failTest(class_basename($service) . ' service failed: ' . $e->getMessage());
            }
        }
    }
    
    private function testConfiguration(): void
    {
        $this->section('CONFIGURATION');
        
        // Check .env variables
        $requiredEnv = [
            'DB_CONNECTION' => 'mysql',
            'REDIS_CLIENT' => 'phpredis',
            'CACHE_DRIVER' => 'redis',
            'QUEUE_CONNECTION' => 'redis',
        ];
        
        foreach ($requiredEnv as $key => $expectedValue) {
            $actualValue = env($key);
            if ($actualValue === $expectedValue) {
                $this->passTest("{$key} configured correctly");
            } else {
                $this->warnTest("{$key} = {$actualValue} (expected: {$expectedValue} for production)");
            }
        }
        
        // Check timezone
        $timezone = config('app.timezone');
        if ($timezone === 'Asia/Kolkata') {
            $this->passTest('Timezone set to Asia/Kolkata');
        } else {
            $this->warnTest('Timezone not set to Asia/Kolkata: ' . $timezone);
        }
    }
    
    private function testScheduler(): void
    {
        $this->section('SCHEDULED TASKS');
        
        // Get scheduled tasks
        $schedule = app()->make(\Illuminate\Console\Scheduling\Schedule::class);
        $events = $schedule->events();
        
        $expectedTasks = [
            'trading:scan',
            'trading:monitor',
            'trading:report',
            'trading:learn',
        ];
        
        $foundTasks = [];
        foreach ($events as $event) {
            $command = $event->command ?? '';
            foreach ($expectedTasks as $task) {
                if (str_contains($command, $task)) {
                    $foundTasks[] = $task;
                }
            }
        }
        
        foreach ($expectedTasks as $task) {
            if (in_array($task, $foundTasks)) {
                $this->passTest("Scheduled task '{$task}' configured");
            } else {
                $this->failTest("Scheduled task '{$task}' missing");
            }
        }
        
        $this->info("  Total scheduled tasks: " . count($events));
    }
    
    private function displayResults(): void
    {
        $this->newLine();
        $this->line(str_repeat('=', 60));
        
        $total = $this->passedTests + $this->failedTests;
        $percentage = $total > 0 ? round(($this->passedTests / $total) * 100, 1) : 0;
        
        $this->info("📊 TEST RESULTS");
        $this->newLine();
        
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Passed', $this->passedTests],
                ['❌ Failed', $this->failedTests],
                ['📈 Success Rate', $percentage . '%'],
            ]
        );
        
        if ($this->failedTests === 0) {
            $this->newLine();
            $this->info('🎉 ALL TESTS PASSED! System is healthy.');
        } else {
            $this->newLine();
            $this->error('⚠️  SOME TESTS FAILED');
            
            if (!empty($this->errors)) {
                $this->newLine();
                $this->warnTest('Failed Tests:');
                foreach ($this->errors as $error) {
                    $this->line("  • {$error}");
                }
            }
        }
        
        $this->newLine();
    }
    
    private function section(string $title): void
    {
        $this->newLine();
        $this->line(str_repeat('─', 60));
        $this->info($title);
        $this->line(str_repeat('─', 60));
    }
    
    private function passTest(string $message): void
    {
        $this->passedTests++;
        if ($this->option('show-passed')) {
            $this->line("  <fg=green>✓</> {$message}");
        }
    }
    
    private function failTest(string $message): void
    {
        $this->failedTests++;
        $this->errors[] = $message;
        $this->line("  <fg=red>✗</> {$message}");
    }
    
    private function warnTest(string $message): void
    {
        $this->line("  <fg=yellow>⚠</> {$message}");
    }
}

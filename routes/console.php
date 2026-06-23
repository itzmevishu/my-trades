<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================================================
// TRADING AUTOMATION - Scheduled Tasks
// ============================================================================

/**
 * Entry Signal Scanning
 * 
 * Runs every 15 minutes during trading hours (11:15 AM - 2:00 PM IST)
 * Scans for entry signals and executes trades if valid setup found.
 * 
 * Cron: Every 15 minutes, Mon-Fri, 11 AM-2 PM
 */
Schedule::command('trading:scan')
    ->everyFifteenMinutes()
    ->between('11:15', '14:00')
    ->weekdays()
    ->timezone('Asia/Kolkata')
    ->name('Entry Signal Scan')
    ->withoutOverlapping()
    ->onSuccess(function () {
        logger()->info('Trading scan completed successfully');
    })
    ->onFailure(function () {
        logger()->error('Trading scan failed');
    });

/**
 * Position Monitoring
 * 
 * Runs every minute throughout market hours (9:15 AM - 3:30 PM IST)
 * Monitors open positions and manages exits (SL/target/partial/EOD).
 * 
 * Cron: Every minute, Mon-Fri, 9 AM-3:30 PM
 */
Schedule::command('trading:monitor')
    ->everyMinute()
    ->between('09:15', '15:30')
    ->weekdays()
    ->timezone('Asia/Kolkata')
    ->name('Position Monitor')
    ->withoutOverlapping()
    ->onSuccess(function () {
        logger()->info('Position monitoring completed');
    })
    ->onFailure(function () {
        logger()->error('Position monitoring failed');
    });

/**
 * End-of-Day Report
 * 
 * Generates daily trading report with Claude AI insights.
 * Runs at 4:00 PM IST after market close.
 * 
 * Cron: Mon-Fri, 4:00 PM
 */
Schedule::command('trading:report daily')
    ->dailyAt('16:00')
    ->weekdays()
    ->timezone('Asia/Kolkata')
    ->name('Daily Report Generation')
    ->onSuccess(function () {
        logger()->info('Daily report generated successfully');
    })
    ->onFailure(function () {
        logger()->error('Daily report generation failed');
    });

/**
 * Weekly Report
 * 
 * Generates weekly performance summary.
 * Runs every Saturday at 10:00 AM IST.
 * 
 * Cron: Saturday, 10:00 AM
 */
Schedule::command('trading:report weekly')
    ->weeklyOn(6, '10:00')
    ->timezone('Asia/Kolkata')
    ->name('Weekly Report Generation');

/**
 * Monthly Report
 * 
 * Generates monthly performance analysis.
 * Runs on 1st of every month at 10:00 AM IST.
 * 
 * Cron: 1st of month, 10:00 AM
 */
Schedule::command('trading:report monthly')
    ->monthlyOn(1, '10:00')
    ->timezone('Asia/Kolkata')
    ->name('Monthly Report Generation');

/**
 * Learning Cycle (Auto)
 * 
 * Automatically triggered after every 10th trade by the monitor command.
 * This is a backup check that runs daily at 5:00 PM.
 * 
 * Cron: Mon-Fri, 5:00 PM
 */
Schedule::command('trading:learn')
    ->dailyAt('17:00')
    ->weekdays()
    ->timezone('Asia/Kolkata')
    ->name('Learning Cycle Check')
    ->skip(function () {
        // Skip if not due (shouldTriggerLearningCycle checks internally)
        $engine = new \App\Services\Learning\LearningEngine();
        return !$engine->shouldTriggerLearningCycle();
    });

// ============================================================================
// NOTES:
// ============================================================================
// 
// 1. All times are in Asia/Kolkata timezone (IST)
// 2. Trading window: 11:15 AM - 2:00 PM (entry signals)
// 3. Monitoring window: 9:15 AM - 3:30 PM (position management)
// 4. withoutOverlapping() prevents concurrent execution
// 5. Run: php artisan schedule:list to see all scheduled tasks
// 6. Run: php artisan schedule:work to test locally (runs every minute)
// 7. On production: Add cron job: * * * * * php artisan schedule:run
// ============================================================================

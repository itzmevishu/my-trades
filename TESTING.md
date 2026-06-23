# Testing Guide for BankNifty AI Trading Tool

## 📋 Overview

This guide covers all testing procedures for validating the trading system before production deployment.

---

## 🧪 Test Suites

### 1. Extended Test Data (30 Trades)

**Purpose:** Test multiple learning cycles and pattern evolution

```bash
# Seed 30 trades over 6 weeks
php artisan db:seed --class=ExtendedTestSeeder
```

**What it creates:**
- 30 trades spanning 6 weeks (June 10 - July 19, 2026)
- Triggers 3 learning cycles (after 10, 20, 30 trades)
- Tests all exit types (SL_HIT, TARGET_HIT, PARTIAL_EXIT, EOD_EXIT)
- Validates avoid list logic for poor performing patterns
- Mix of winning and losing patterns

**Expected Outcome:**
- Bullish/Bearish Engulfing: High win rate → weight increases
- Bullish Pinbar: Good performance → weight increases
- Inside Bar Breakout: Poor performance → weight decreases → avoid list
- Bearish Pinbar: Poor performance → weight decreases → avoid list

---

### 2. Learning Cycle Tests

**Test learning at different stages:**

```bash
# Test after first 10 trades
php artisan test:learning 10

# Test after 20 trades
php artisan test:learning 20

# Test after all 30 trades
php artisan test:learning 30
```

**What it validates:**
- ✅ Pattern performance analysis
- ✅ Weight adjustments (+10% for good, -20% for poor)
- ✅ Avoid list additions (patterns with <30% win rate)
- ✅ Strategy versioning (v1 → v2 → v3 → v4)
- ✅ Active strategy switching
- ✅ Audit trail maintenance

**Expected Results:**

#### After 10 Trades
```
Strategy v1 → v2
- Bullish Engulfing: 1.0 → 1.1 (100% win rate)
- Bearish Engulfing: 1.0 → 1.1 (100% win rate)
- Bullish Pinbar: 1.0 → 1.1 (100% win rate)
- Inside Bar Breakout: 1.0 → 0.8 (0% win rate)
- Bearish Pinbar: 1.0 → 0.8 (0% win rate)
```

#### After 20 Trades
```
Strategy v2 → v3
- Inside Bar Breakout: 0.8 → avoid list (0% in 20 trades)
- Bearish Pinbar: 0.8 → avoid list (0% in 20 trades)
- Others: Further refinement based on continued performance
```

#### After 30 Trades
```
Strategy v3 → v4
- Strong patterns continue getting boosted
- Weak patterns remain in avoid list
- Overall system optimized for best performers
```

---

### 3. Comprehensive System Validation

**Run all validation tests:**

```bash
# Full system check
php artisan test:all

# Verbose output (shows all passed tests)
php artisan test:all --verbose
```

**What it tests:**

#### Database Connectivity
- ✅ MySQL connection established
- ✅ All required tables exist (trades, strategy_configs, settings, daily_reports)
- ✅ Read/write operations functional

#### Redis Connectivity
- ✅ Redis connection established
- ✅ PING/PONG response
- ✅ SET/GET operations working
- ✅ Key expiration working

#### Model Validation
- ✅ Trade model functional
- ✅ StrategyConfig model functional
- ✅ Setting model functional
- ✅ JSON casting working (pattern_weights, avoid_setups)
- ✅ Fillable fields accessible

#### Data Integrity
- ✅ All trades have required fields
- ✅ All outcomes are valid (win/loss/breakeven)
- ✅ All directions are valid (long/short)
- ✅ Strategy versions are unique
- ✅ Only one active strategy at a time

#### Service Availability
- ✅ LearningEngine service
- ✅ PaperTradingService
- ✅ ClaudeAPIService
- ✅ PatternScanner
- ✅ TechnicalIndicators
- ✅ RiskManager
- ✅ PositionMonitor
- ✅ TradeExecutor

#### Configuration
- ✅ Database connection (mysql)
- ✅ Redis client (phpredis)
- ✅ Cache driver (redis)
- ✅ Queue connection (redis)
- ✅ Timezone (Asia/Kolkata)

#### Scheduled Tasks
- ✅ trading:scan configured
- ✅ trading:monitor configured
- ✅ trading:report configured
- ✅ trading:learn configured

---

### 4. Individual Command Tests

**Test each command independently:**

```bash
# Entry scanning (should show no signal during off-hours)
php artisan trading:scan

# Position monitoring (shows open positions)
php artisan trading:monitor

# Learning cycle (manual trigger)
php artisan trading:learn --force

# Daily report
php artisan trading:report daily

# Weekly report
php artisan trading:report weekly

# Monthly report
php artisan trading:report monthly
```

---

### 5. Scheduler Verification

**Check scheduled tasks:**

```bash
# List all scheduled tasks
php artisan schedule:list

# Run scheduler once (for testing)
php artisan schedule:run

# Watch scheduler in real-time
watch -n 60 'php artisan schedule:run'
```

**Expected Schedule:**

| Command | Frequency | Time | Days |
|---------|-----------|------|------|
| trading:scan | Every 15 min | 11:15-14:00 | Mon-Fri |
| trading:monitor | Every 1 min | 9:15-15:30 | Mon-Fri |
| trading:report daily | Daily | 16:00 | Mon-Fri |
| trading:report weekly | Weekly | Sat 10:00 | Sat |
| trading:report monthly | Monthly | 1st 10:00 | 1st of month |
| trading:learn | Daily | 17:00 | Mon-Fri |

---

## 🔄 Complete Test Workflow

**Recommended sequence for thorough testing:**

### Step 1: Fresh Start
```bash
# Clear existing data
php artisan migrate:fresh

# Seed settings
php artisan db:seed --class=SettingsSeeder
```

### Step 2: Load Test Data
```bash
# Seed 30 realistic trades
php artisan db:seed --class=ExtendedTestSeeder
```

### Step 3: Validate System
```bash
# Run comprehensive tests
php artisan test:all --verbose
```

### Step 4: Test Learning Cycles
```bash
# Test first learning cycle (after 10 trades)
php artisan test:learning 10

# Test second learning cycle (after 20 trades)
php artisan test:learning 20

# Test third learning cycle (after 30 trades)
php artisan test:learning 30
```

### Step 5: Test Commands
```bash
# Test each command
php artisan trading:scan
php artisan trading:monitor
php artisan trading:report daily
php artisan schedule:list
```

### Step 6: Verify Data
```bash
# Check database
php artisan tinker

>>> Trade::count()
=> 30

>>> StrategyConfig::count()
=> 4 (v1, v2, v3, v4)

>>> StrategyConfig::where('is_active', true)->value('version')
=> 4

>>> StrategyConfig::latest('version')->first()->avoid_setups
=> ["inside_bar_breakout", "bearish_pinbar"]
```

---

## 📊 Expected Test Results

### Extended Test Seeder Results
```
Total Trades: 30
Wins: 20
Losses: 10
Win Rate: 66.7%
Total P&L: ₹4,850

Pattern Breakdown:
- Bullish Engulfing: 9 trades, 9 wins (100%)
- Bearish Engulfing: 7 trades, 7 wins (100%)
- Bullish Pinbar: 6 trades, 6 wins (100%)
- Inside Bar Breakout: 4 trades, 0 wins (0%) → AVOID LIST
- Bearish Pinbar: 4 trades, 0 wins (0%) → AVOID LIST
```

### Learning Cycle Validation
```
✅ Strategy v2 created after 10 trades
✅ Strategy v3 created after 20 trades
✅ Strategy v4 created after 30 trades
✅ Pattern weights evolving correctly
✅ Avoid list populated with poor performers
✅ Only one active strategy at a time
```

### System Validation Results
```
Passed: 40+ tests
Failed: 0 tests
Success Rate: 100%

✅ Database connectivity
✅ Redis connectivity
✅ All models functional
✅ Data integrity maintained
✅ All services available
✅ Configuration correct
✅ Scheduler configured
```

---

## 🐛 Troubleshooting

### Issue: Tests Failing

**Database connectivity issues:**
```bash
# Check MySQL is running
sudo systemctl status mysql

# Test connection
php artisan tinker
>>> \DB::connection()->getPdo();
```

**Redis connectivity issues:**
```bash
# Check Redis is running
redis-cli ping

# Restart if needed
sudo systemctl restart redis-server
```

**Model errors:**
```bash
# Clear caches
php artisan config:clear
php artisan cache:clear

# Re-run migrations
php artisan migrate:fresh
php artisan db:seed --class=SettingsSeeder
```

### Issue: Learning Cycle Not Triggering

```bash
# Check trade count
php artisan tinker
>>> Trade::count()

# Force learning cycle
php artisan trading:learn --force

# Check strategy versions
>>> StrategyConfig::pluck('version', 'is_active')
```

### Issue: Scheduler Not Running

```bash
# Check crontab
crontab -l

# Add if missing
* * * * * cd /path/to/my-trades && php artisan schedule:run >> /dev/null 2>&1

# Test manually
php artisan schedule:run
```

---

## ✅ Pre-Deployment Checklist

Before deploying to production:

- [ ] All tests passing (php artisan test:all)
- [ ] Extended test data working (30 trades seeded)
- [ ] Learning cycles validated (3 cycles tested)
- [ ] Commands functional (scan, monitor, report, learn)
- [ ] Scheduler configured (6 tasks scheduled)
- [ ] Database migrations clean
- [ ] Redis working
- [ ] Services available
- [ ] Configuration correct
- [ ] Logs accessible
- [ ] Documentation complete

---

## 📈 Performance Benchmarks

Expected performance on AWS Lightsail ($3.50/month):

- **Entry scan:** <2 seconds
- **Position monitor:** <1 second
- **Learning cycle:** <5 seconds
- **Report generation:** <3 seconds (without Claude API)
- **Database queries:** <100ms
- **Redis operations:** <10ms

---

## 🎯 Next Steps After Testing

1. ✅ All tests passing
2. → Deploy to AWS Lightsail
3. → Configure Fyers API credentials
4. → Whitelist static IP
5. → Enable cron jobs
6. → Test with live market data (paper mode)
7. → Monitor for 1 week
8. → Enable live trading (if confident)

---

**Testing is complete when:**
- `php artisan test:all` shows 100% pass rate
- Multiple learning cycles validated
- All commands executable
- Scheduler configured
- Data integrity maintained
- System performance acceptable

🚀 **Ready for production deployment!**

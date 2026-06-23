# Local Development Guide
## BankNifty AI Trading Tool - macOS Setup

**The Static IP Question:** "How do I develop locally if Fyers API requires static IP?"

**The Answer:** You don't need Fyers API for local development! 🎉

---

## 🎯 Development Strategy

### What You CAN Test Locally (90% of development):
✅ Database operations (trades, configs, settings)  
✅ Model relationships and queries  
✅ Service layer logic (calculations, risk engine)  
✅ Pattern detection algorithms  
✅ EMA calculations  
✅ Claude AI integration (only needs API key, no IP restriction)  
✅ Learning engine logic  
✅ Paper trading simulations  
✅ Admin dashboard (Filament)  
✅ Queue jobs  
✅ Scheduled tasks  
✅ Report generation  
✅ All business logic  

### What You CANNOT Test Locally:
❌ Live Fyers API calls (market data, order placement)  
❌ Real-time WebSocket feeds  
❌ Actual order execution  

### Solution:
**Use paper trading mode with simulated/cached data!**

---

## 📋 Local Environment Setup (macOS)

### Prerequisites Check

```bash
# Check PHP version (need 8.2+)
php -v

# Check Composer
composer --version

# Check MySQL
mysql --version

# Check Redis
redis-cli --version
```

### Option 1: Laravel Herd (RECOMMENDED - Easiest)

**Herd includes everything you need:**
- ✅ PHP 8.2+
- ✅ MySQL
- ✅ Redis
- ✅ Nginx
- ✅ Auto-serves Laravel apps

```bash
# 1. Download Herd (free)
# Go to: https://herd.laravel.com/
# Install the macOS app

# 2. Herd auto-detects your ~/Sites folder
# Your app at ~/Sites/my-trades will be available at:
# http://my-trades.test

# 3. That's it! Herd handles everything automatically
```

### Option 2: Homebrew (Manual Setup)

```bash
# 1. Install PHP 8.2
brew install php@8.2
brew link php@8.2

# 2. Install Composer
brew install composer

# 3. Install MySQL
brew install mysql
brew services start mysql

# 4. Install Redis
brew install redis
brew services start redis

# 5. Verify installations
php -v
composer --version
mysql --version
redis-cli ping  # Should return: PONG
```

---

## 🚀 Setting Up Your Local App

### Step 1: Install Dependencies

```bash
cd ~/Sites/my-trades

# Install PHP dependencies
composer install

# If you get memory errors:
# php -d memory_limit=-1 /usr/local/bin/composer install
```

### Step 2: Configure Environment

```bash
# Copy example .env
cp .env.example .env

# Edit .env for LOCAL development
nano .env
```

**Local .env configuration:**

```env
APP_NAME="BankNifty Trading (Local)"
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=Asia/Kolkata
APP_URL=http://my-trades.test  # Or http://localhost:8000

# Database (Local MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_trades_local
DB_USERNAME=root
DB_PASSWORD=  # Usually empty for Herd, or 'root' for Homebrew

# Redis (Local)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=file  # Use file for local to avoid Redis sessions
QUEUE_CONNECTION=sync  # Use sync for local (no worker needed)

# Fyers API - LEAVE EMPTY FOR LOCAL
FYERS_NEW_APP_ID=
FYERS_CLIENT_ID=
FYERS_SECRET_KEY=

# Claude API - ADD YOUR KEY (Works from any IP!)
CLAUDE_API_KEY=your_claude_api_key_here

# SEBI Compliance - NOT NEEDED FOR LOCAL
STATIC_IP_ADDRESS=127.0.0.1
SEBI_COMPLIANT_MODE=false

# Trading Mode - ALWAYS PAPER TRADING LOCALLY
PAPER_TRADE_MODE=true
LIVE_TRADING_ENABLED=false
```

### Step 3: Generate App Key

```bash
php artisan key:generate
```

### Step 4: Create Database

```bash
# If using Herd (built-in MySQL):
# 1. Open Herd app
# 2. Click "Database" → Opens TablePlus/DBngin
# 3. Create database: my_trades_local

# If using Homebrew MySQL:
mysql -u root -p
# (Enter password if you set one, or just press Enter)
```

**In MySQL prompt:**

```sql
CREATE DATABASE my_trades_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SHOW DATABASES;  -- Verify it's created
EXIT;
```

### Step 5: Run Migrations & Seeders

```bash
# Run all migrations
php artisan migrate

# You should see:
# ✅ create_users_table
# ✅ create_cache_table
# ✅ create_jobs_table
# ✅ create_trades_table
# ✅ create_strategy_configs_table
# ✅ create_learning_logs_table
# ✅ create_daily_reports_table
# ✅ create_candle_cache_table
# ✅ create_market_calendar_table
# ✅ create_settings_table

# Seed settings (41 config values)
php artisan db:seed --class=SettingsSeeder

# Verify
php artisan tinker
>>> \App\Models\Setting::count();
# Should return: 41
>>> exit
```

### Step 6: Set Permissions

```bash
# Make storage writable
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Step 7: Start Development Server

```bash
# Option A: If using Herd
# Just visit: http://my-trades.test
# (Herd auto-serves from ~/Sites)

# Option B: If using Homebrew
php artisan serve
# Visit: http://localhost:8000
```

### Step 8: Test Database Connection

```bash
php artisan tinker

>>> \DB::connection()->getPdo();
# Should show: PDO object ✅

>>> \App\Models\Trade::count();
# Should return: 0 (no trades yet)

>>> \App\Models\Setting::where('key', 'capital_amount')->first()->value;
# Should return: "300000" ✅

>>> exit
```

---

## 🧪 Local Development Workflow

### Phase 1: Build Without Fyers API (Weeks 1-8)

**You can build 90% of the system without Fyers API:**

```bash
# Week 3: Data Pipeline (Local Mode)
# Instead of calling Fyers API, use cached/simulated data

# Example: Simulate candle data for testing
php artisan tinker
>>> use App\Models\CandleCache;
>>> CandleCache::create([
...   'symbol' => 'NSE:NIFTYBANK-INDEX',
...   'timeframe' => '15m',
...   'open' => 48500.00,
...   'high' => 48550.00,
...   'low' => 48480.00,
...   'close' => 48530.00,
...   'volume' => 1500000,
...   'candle_timestamp' => now()
... ]);
>>> exit
```

**Create test data for development:**

```php
// database/seeders/TestDataSeeder.php
// Create sample candles, test trades, etc.
```

### Phase 2: Mock Fyers API Responses

**Create a FyersSimulator for local testing:**

```php
// app/Services/Fyers/FyersSimulator.php
class FyersSimulator
{
    public static function getSimulatedCandles($symbol, $timeframe, $limit)
    {
        // Return realistic fake candle data
        // Based on Bank Nifty's typical behavior
        return [
            [
                'timestamp' => now()->subMinutes(15),
                'open' => 48500,
                'high' => 48550,
                'low' => 48480,
                'close' => 48530,
                'volume' => 1500000
            ],
            // ... more candles
        ];
    }
    
    public static function simulatePaperOrder($direction, $strike, $premium)
    {
        // Simulate order placement
        return [
            'status' => 'success',
            'order_id' => 'PAPER_' . time(),
            'filled_premium' => $premium * 1.002  // 0.2% slippage
        ];
    }
}
```

**Use simulator in local environment:**

```php
// app/Services/Fyers/FyersDataService.php
public function fetchCandles($symbol, $timeframe, $limit)
{
    if (config('app.env') === 'local') {
        // Use simulator for local development
        return FyersSimulator::getSimulatedCandles($symbol, $timeframe, $limit);
    }
    
    // Real API call for production
    return $this->callFyersAPI(...);
}
```

### Phase 3: Claude API Testing (Works Locally!)

**Claude API doesn't care about your IP:**

```bash
# Test Claude integration locally
php artisan tinker

>>> use App\Services\Claude\ClaudeAPIService;
>>> $claude = new ClaudeAPIService();
>>> $result = $claude->scoreSetup([
...     'candle_pattern' => 'bullish_engulfing',
...     'ema_confluence' => true,
...     'htf_bias' => 'bullish',
...     'market_condition' => 'trending'
... ]);
>>> dd($result);
# Should return Claude's analysis! ✅
```

### Phase 4: Test All Local Features

```bash
# Test models
php artisan tinker
>>> $trade = \App\Models\Trade::create([...]);

# Test EMA calculator (with cached candles)
>>> use App\Services\Analysis\EMACalculator;
>>> $ema = new EMACalculator();
>>> $result = $ema->calculateEMA($candles, 20);

# Test pattern detector
>>> use App\Services\Analysis\PatternDetector;
>>> $detector = new PatternDetector();
>>> $pattern = $detector->detectPattern($candle);

# Test risk engine
>>> use App\Services\Trading\RiskEngine;
>>> $risk = new RiskEngine();
>>> $position = $risk->calculateRisk(300000, 1.0, 50);
```

---

## 🔄 When You Need Real Fyers API

### Option 1: Deploy to Lightsail for API Testing

**Once you've built features locally, test with real API on server:**

```bash
# 1. Push code to GitHub
git add .
git commit -m "Feature: Pattern detection complete"
git push origin main

# 2. Deploy to Lightsail
ssh bitnami@YOUR_LIGHTSAIL_IP
cd /opt/bitnami/apache2/htdocs/my-trades
git pull origin main
composer install --no-dev
php artisan migrate --force
php artisan config:cache

# 3. Test with real Fyers API (server has whitelisted static IP)
php artisan tinker
>>> // Run your tests with real API
```

### Option 2: SSH Tunnel (Advanced)

**Route local traffic through Lightsail server:**

```bash
# On your Mac, create SSH tunnel
ssh -D 8080 -C -q -N bitnami@YOUR_LIGHTSAIL_IP

# Configure your app to use SOCKS proxy
# This makes Fyers think requests come from Lightsail IP
# (Complex setup, only if really needed)
```

### Option 3: VPN with Static IP (Expensive)

**Commercial VPN with dedicated IP:**
- Cost: ₹500-1000/month
- Examples: NordVPN, PureVPN dedicated IP
- Not recommended (just deploy to Lightsail instead)

---

## 🎯 Recommended Development Flow

### BEST PRACTICE:

```
┌─────────────────────────────────────────────────┐
│ LOCAL DEVELOPMENT (Your Mac)                    │
│ ✅ Build features                                │
│ ✅ Test logic with simulated data                │
│ ✅ Use Claude API (works from any IP)            │
│ ✅ Test models, calculations, UI                 │
│ ✅ Git commit when feature complete              │
└─────────────────────────────────────────────────┘
                     ↓ git push
┌─────────────────────────────────────────────────┐
│ LIGHTSAIL (Production/Staging)                   │
│ ✅ Deploy code                                   │
│ ✅ Test with REAL Fyers API                      │
│ ✅ Verify end-to-end flow                        │
│ ✅ Static IP whitelisted                         │
└─────────────────────────────────────────────────┘
```

### Your Typical Day:

**9:00 AM - Morning:**
```bash
# On your Mac
cd ~/Sites/my-trades
git pull  # Get latest
php artisan serve  # Start local server
code .  # Open VS Code
```

**9:30 AM - 5:00 PM - Development:**
- Write code locally
- Test with simulated data
- Use `php artisan tinker` for quick tests
- View logs: `tail -f storage/logs/laravel.log`
- Make database changes
- Test in browser: `http://localhost:8000`

**5:00 PM - Deploy to Lightsail:**
```bash
git add .
git commit -m "Feature: Pattern detector with 5-candle lookback"
git push origin main

# SSH to Lightsail
ssh bitnami@YOUR_IP
cd /opt/bitnami/apache2/htdocs/my-trades
git pull && composer install && php artisan migrate --force && sudo systemctl restart apache2

# Test with real Fyers API
# Check logs: tail -f storage/logs/laravel.log
```

---

## 🧪 Testing Strategy

### Unit Tests (Run Locally)

```bash
# Create tests
php artisan make:test EMACalculatorTest --unit

# Run tests
php artisan test

# Test specific file
php artisan test --filter=EMACalculatorTest
```

**Example test:**

```php
// tests/Unit/EMACalculatorTest.php
public function test_calculates_20_ema_correctly()
{
    $candles = CandleCache::factory()->count(50)->create();
    $calculator = new EMACalculator();
    
    $ema = $calculator->calculateEMA($candles, 20);
    
    $this->assertIsNumeric($ema);
    $this->assertGreaterThan(0, $ema);
}
```

### Feature Tests (Run Locally with Simulated Data)

```bash
php artisan make:test PaperTradingTest

# tests/Feature/PaperTradingTest.php
public function test_creates_paper_trade_successfully()
{
    $trade = Trade::create([
        'date' => today(),
        'direction' => 'CALL',
        'strike_price' => 48500,
        'entry_premium' => 150.00,
        // ...
    ]);
    
    $this->assertDatabaseHas('trades', [
        'strike_price' => 48500,
        'mode' => 'paper'
    ]);
}
```

### Integration Tests (Run on Lightsail)

```bash
# SSH to Lightsail
ssh bitnami@YOUR_IP

# Run integration tests with real API
php artisan test --group=integration
```

---

## 📊 Development Tools

### Laravel Tinker (Your Best Friend)

```bash
php artisan tinker

# Quick database queries
>>> \App\Models\Trade::count()
>>> \App\Models\Setting::where('key', 'capital_amount')->first()

# Test services
>>> $risk = new \App\Services\Trading\RiskEngine();
>>> $risk->calculateRisk(300000, 1.0, 50);

# Create test data
>>> \App\Models\Trade::factory()->count(10)->create()

# Clear caches
>>> \Artisan::call('cache:clear')
>>> \Artisan::call('config:clear')
```

### Database GUI

**Option 1: TablePlus (Free tier available)**
- Download: https://tableplus.com/
- Connect to: localhost:3306
- Database: my_trades_local

**Option 2: phpMyAdmin**
```bash
# If using MAMP/XAMPP, already included
# Access: http://localhost/phpmyadmin
```

### Log Monitoring

```bash
# Watch Laravel logs in real-time
tail -f storage/logs/laravel.log

# Clear old logs
echo "" > storage/logs/laravel.log
```

### Redis GUI

**Option 1: RedisInsight (Free)**
- Download: https://redis.io/insight/
- Connect to: localhost:6379

**Option 2: CLI**
```bash
redis-cli

# Check keys
KEYS *

# Get value
GET fyers:orders:2026-06-23

# Monitor commands
MONITOR
```

---

## 🐛 Common Local Development Issues

### Issue 1: "Class not found"
```bash
# Clear autoload cache
composer dump-autoload
```

### Issue 2: "SQLSTATE[HY000] [1045] Access denied"
```bash
# Check .env database credentials
cat .env | grep DB_

# Test MySQL connection
mysql -u root -p my_trades_local
```

### Issue 3: "Redis connection refused"
```bash
# Check if Redis is running
redis-cli ping

# Start Redis (if using Homebrew)
brew services start redis

# Check port
lsof -i :6379
```

### Issue 4: "419 Page Expired" (CSRF token)
```bash
# Clear sessions
php artisan session:clear

# Clear cache
php artisan cache:clear
php artisan config:clear
```

### Issue 5: "Storage not writable"
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

---

## 📚 Quick Reference Commands

```bash
# Start development server
php artisan serve  # http://localhost:8000

# Run migrations
php artisan migrate
php artisan migrate:fresh  # Drop all tables and re-run

# Seed database
php artisan db:seed
php artisan db:seed --class=SettingsSeeder

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Run queue workers (optional for local)
php artisan queue:work

# Run scheduler (optional for local)
php artisan schedule:work

# Interactive shell
php artisan tinker

# Run tests
php artisan test

# View routes
php artisan route:list

# Create model, migration, controller
php artisan make:model Trade -mcr

# Check Laravel status
php artisan about
```

---

## 🎯 Summary: Local vs Production

| Feature | Local (Your Mac) | Production (Lightsail) |
|---------|------------------|------------------------|
| **IP Address** | Dynamic (home internet) | Static (whitelisted) |
| **Fyers API** | ❌ Simulated/Mocked | ✅ Real API calls |
| **Claude API** | ✅ Works (no IP restriction) | ✅ Works |
| **Database** | Local MySQL | Lightsail MySQL |
| **Redis** | Local Redis | Lightsail Redis |
| **Trading Mode** | Paper only | Paper + Live capable |
| **Purpose** | Development & testing | Real trading |
| **Cost** | FREE | ₹400/month |

---

## ✅ You're Ready When:

- [x] Local server runs: `http://localhost:8000` or `http://my-trades.test`
- [x] Database migrations successful
- [x] Settings seeded (41 configs)
- [x] Tinker can query models
- [x] Redis responding to `PING`
- [x] Claude API key added (for AI features)
- [x] Can create test trades in database
- [x] Logs working: `storage/logs/laravel.log`

**Now you can build 90% of the system locally without Fyers API!** 🚀

---

## 🚀 Next Steps

1. **TODAY:** Set up local environment (30 minutes)
2. **This Week:** Build Phase 1 features locally with simulated data
3. **Weekend:** Deploy to Lightsail and get static IP
4. **Next Week:** Test with real Fyers API on Lightsail

**Don't wait for Lightsail to start coding!** You can build everything locally first. 💪

---

*Last updated: 2026-06-23*

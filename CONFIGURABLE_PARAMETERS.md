# 🎛️ Configurable Trading Parameters

## Overview

All critical trading parameters are stored as settings in the database and can be modified through the admin panel without code changes.

---

## ⏰ Trading Hours

| Setting | Default | Description |
|---------|---------|-------------|
| `trading_start_time` | `11:15:00` | When to START taking entry signals (HH:MM:SS) |
| `trading_end_time` | `14:00:00` | When to STOP taking entry signals (HH:MM:SS) |
| `eod_exit_time` | `15:15:00` | Force exit all positions at this time (HH:MM:SS) |

**NSE Market Hours**: 09:15 AM - 03:30 PM IST (fixed by exchange)

**Why these times?**
- **09:15-11:15** (2h): Market opens, high volatility, wait for settling → Monitor only
- **11:15-14:00** (2h 45m): Entry window, stable price action → Take entries
- **14:00-15:15** (1h 15m): Near close, time decay risk → Monitor exits only
- **15:15**: Force exit all remaining positions before market close

---

## 💰 Position Sizing & Risk

| Setting | Default | Description |
|---------|---------|-------------|
| `banknifty_lot_size` | `15` | Number of contracts per lot |
| `max_lots` | `2` | Maximum lots per trade (risk cap) |
| `capital_amount` | `300000` | Total trading capital (₹) |
| `risk_percentage` | `1.0` | Risk per trade as % of capital |

**Example Calculation:**
- Capital: ₹3,00,000
- Risk: 1% = ₹3,000 per trade
- SL Distance: 10 points
- Lot Size: 15
- Risk per lot: 10 × 15 = ₹150
- Lots = ₹3,000 / ₹150 = **20 lots** → Capped to **max_lots (2)**

---

## 🎯 Risk:Reward Parameters

| Setting | Default | Description |
|---------|---------|-------------|
| `target_rr` | `2.0` | Target Risk:Reward ratio (2:1) |
| `minimum_target_premium` | `5.0` | Minimum target in points (prevents unrealistic targets) |
| `sl_delta_assumption` | `0.5` | SL buffer in points for premium calculation |
| `partial_exit_rr` | `1.0` | R:R to trigger 50% position exit |

**Target Calculation:**
```
Entry: 100 points
SL: 90 points (distance = 10 points)
Target RR: 2.0
Target: 100 + (10 × 2) = 120 points
```

---

## 📊 Timeframe & Scanning

| Setting | Default | Description |
|---------|---------|-------------|
| `trading_timeframe` | `15m` | Candle timeframe for pattern detection |
| `candle_lookback` | `250` | Number of candles to fetch (for EMAs) |
| `scan_interval_minutes` | `15` | How often to scan for entry signals |
| `monitor_interval_minutes` | `1` | How often to check positions |

**Supported Timeframes:** `1m`, `5m`, `15m`, `1h`, `1D`

---

## 🔄 How to Change Settings

### Method 1: Admin Panel (Recommended)
1. Navigate to **Admin → Settings**
2. Find the setting you want to change
3. Update the value
4. Save

### Method 2: Database (Advanced)
```sql
UPDATE settings 
SET value = '11:30' 
WHERE key = 'entry_window_start';
```

### Method 3: Artisan Command
```bash
# View all settings
php artisan tinker
>>> \App\Models\Setting::all();

# Update a setting
>>> \App\Models\Setting::setValue('max_lots', 3);
```

---

## ⚠️ Important Notes

### Scheduler Times (⚠️ Limitation)
The Laravel scheduler in `routes/console.php` still has hardcoded times:
```php
->between('09:15', '15:30')  // ⚠️ Hardcoded
```

**Why?** Laravel's Schedule facade doesn't support dynamic times from database.

**Workaround:**
- For now, edit `routes/console.php` manually to change schedule times
- Or run scheduler manually: `php artisan trading:scan`
- **Future Enhancement:** Replace with Queue-based dynamic scheduler

### Settings Cache
- Settings are cached for performance
- Changes take effect immediately in the application
- No need to restart services

### Risk Management
- `max_lots` is a **hard limit** for safety
- Even if calculated lots > max_lots, it will be capped
- Never trade more than you can afford to lose

---

## 🧪 Testing Different Configurations

### Conservative Profile
```php
risk_percentage: 0.5%
max_lots: 1
target_rr: 2.5
```

### Balanced Profile (Default)
```php
risk_percentage: 1.0%
max_lots: 2
target_rr: 2.0
```

### Aggressive Profile (⚠️ Higher Risk)
```php
risk_percentage: 2.0%
max_lots: 3
target_rr: 1.5
```

---

## 📋 Settings Checklist

Before going live, verify these settings:

- [ ] `capital_amount` - Set to your actual trading capital
- [ ] `risk_percentage` - Comfortable risk per trade (start with 0.5-1%)
- [ ] `max_lots` - Position size limit (start with 1-2)
- [ ] `entry_window_start/end` - Trading hours that work for you
- [ ] `target_rr` - Realistic profit expectations (2:1 recommended)
- [ ] `banknifty_lot_size` - Verify with NSE (changes rarely, currently 15)
- [ ] `paper_trade_mode` - Keep TRUE until confident
- [ ] `live_trading_enabled` - Keep FALSE in paper mode

---

## 🔐 Safety Features

1. **Settings Validation**: Invalid values are rejected
2. **Type Checking**: Integer, float, string, boolean enforced
3. **Defaults**: Fallback values if setting not found
4. **Audit Trail**: Setting changes logged
5. **Hard Limits**: Max lots enforced in code

---

## 🚀 Quick Start Commands

```bash
# Run migration to add new settings
php artisan migrate

# View all trading settings
php artisan tinker
>>> \App\Models\Setting::where('key', 'like', '%lot%')->get();

# Test with custom settings
php artisan trading:scan --dry-run

# Monitor what scheduler will run
php artisan schedule:list
```

---

## 📖 Related Documentation

- [TRADE_PLACEMENT_LOGIC.md](./TRADE_PLACEMENT_LOGIC.md) - How trades are executed
- [TESTING.md](./TESTING.md) - Testing procedures
- [DEPLOYMENT_READINESS.md](./DEPLOYMENT_READINESS.md) - Production checklist

---

**Last Updated**: 2026-06-25  
**Migration**: `2026_06_25_100000_add_trading_hours_and_parameters_settings.php`

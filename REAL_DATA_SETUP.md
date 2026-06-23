# 🔌 Real Market Data Integration

## Goal
Connect to **real Fyers API** for Bank Nifty data while keeping **paper trading** for order execution.

---

## Prerequisites

### 1. Fyers Account Setup
- [ ] Create Fyers account: https://fyers.in/
- [ ] Complete KYC verification
- [ ] Generate API credentials from: https://myapi.fyers.in/

### 2. API Credentials Required
You'll need:
- **App ID** (Client ID)
- **Secret Key**
- **Redirect URI** (can be http://localhost:8001/callback)

### 3. Network Requirements
Fyers requires **static IP** OR use one of these workarounds:
- **Option A:** Use mobile hotspot (generally static for session)
- **Option B:** Get static IP from ISP
- **Option C:** Use a VPS with static IP (AWS Lightsail)
- **Option D:** Use Fyers web API (less restrictions)

---

## Step 1: Add Fyers Credentials to Settings

Go to: `http://127.0.0.1:8001/admin/settings`

Add/Edit these settings:

| Setting Key | Value | Type | Description |
|------------|-------|------|-------------|
| `fyers_client_id` | YOUR_APP_ID | string | Fyers App ID from myapi.fyers.in |
| `fyers_secret_key` | YOUR_SECRET | string | Fyers Secret Key |
| `fyers_redirect_uri` | http://localhost:8001/callback | string | OAuth callback URL |
| `use_real_data` | `true` | boolean | Enable real market data |

---

## Step 2: Authentication Flow

Fyers uses OAuth2 authentication. Here's how it works:

### One-Time Setup:
```bash
# Generate auth URL
php artisan fyers:auth

# This will output a URL like:
# https://api.fyers.in/api/v2/generate-authcode?client_id=XXX&redirect_uri=YYY&response_type=code&state=ZZZ

# Open this URL in browser
# Login with Fyers credentials
# Authorize the app
# Copy the auth code from redirect URL
```

### Daily Authentication (SEBI Requirement):
Since April 2026, Fyers requires **daily 2FA authentication**:
```bash
# Every day before market hours (before 9:15 AM)
php artisan fyers:authenticate
```

---

## Step 3: Implementation

I'll update the code to:
1. ✅ Check `use_real_data` setting
2. ✅ Use `FyersDataService` for real candles/prices
3. ✅ Keep `FyersSimulator` for paper orders
4. ✅ Add fallback to simulator if API fails

### What Changes:
```
BEFORE:
PaperTradingService → FyersSimulator (fake everything)

AFTER:
PaperTradingService → FyersDataService (real data)
                    → FyersSimulator (paper orders only)
```

---

## Step 4: Testing Real Data

### Quick Test:
```bash
# Test real candle fetching
php artisan fyers:test-candles

# Test spot price
php artisan fyers:test-spot

# Test option premium
php artisan fyers:test-premium
```

### Run Full Scan:
```bash
# This will use real data but paper trade
php artisan trading:scan
```

---

## Expected Behavior

### With Real Data:
- ✅ Bank Nifty spot from live market
- ✅ Real OHLC candles (15-min)
- ✅ Actual option premiums (ATM CE/PE)
- ✅ Real patterns from market data
- ✅ Genuine EMA calculations
- ⚠️ **Still paper trading** (no real orders)

### If API Fails:
- Falls back to FyersSimulator
- Logs warning
- Continues with simulated data
- System doesn't crash

---

## Benefits of Real Data

| Aspect | Simulated | Real Data |
|--------|-----------|-----------|
| **Patterns** | Perfect setups | Messy, realistic |
| **Volatility** | Predictable | Market-driven |
| **Slippage** | Fixed % | Actual spreads |
| **Testing** | Controlled | Real-world |
| **Confidence** | Low | High |

---

## Costs

- **Fyers Account:** FREE
- **API Access:** FREE
- **Real-time Data:** FREE with trading account
- **Orders:** None (paper trading only)

---

## Safety

✅ **Still 100% Safe:**
- No real orders placed
- No money at risk
- Paper trade mode stays ON
- Real data for learning only

---

## Next Steps

1. **Get Fyers credentials** (15 mins)
2. **Add to settings** (2 mins)
3. **Test authentication** (5 mins)
4. **Run with real data** (immediate)
5. **Validate for 1-2 weeks**
6. **Compare vs simulated results**

---

## Troubleshooting

### "Static IP required"
- Use mobile hotspot
- Or deploy to AWS first (has static IP)
- Or use Fyers web API

### "Authentication failed"
- Check credentials correct
- Verify redirect URI matches
- Try regenerating API keys

### "Rate limit exceeded"
- Fyers has limits: 10 req/sec
- System respects this automatically
- If hit, waits and retries

### "Market closed"
- Data only available during market hours
- Falls back to simulator outside hours
- Normal behavior

---

## Ready to Implement?

I can:
1. ✅ Update `FyersDataService` with real API calls
2. ✅ Add authentication commands
3. ✅ Implement fallback logic
4. ✅ Add test commands

**Type "yes" to implement real data integration!**

Or just get your Fyers credentials first, then we'll integrate.

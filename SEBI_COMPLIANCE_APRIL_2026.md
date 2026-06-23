# SEBI Algo Trading Regulations - Impact Assessment
## Effective April 1, 2026 (CRITICAL)

**Date:** 2026-06-23  
**Status:** ⚠️ MANDATORY COMPLIANCE REQUIRED  
**Deadline:** March 31, 2026 (7 days away!)

---

## 🚨 URGENT: SEBI Rule Changes

Starting **April 1, 2026**, SEBI has introduced mandatory regulations for retail algo trading. **Your current implementation plan MUST be updated** to comply, or the system will stop working.

---

## 📋 New Mandatory Requirements

### 1. Static IP Address (CRITICAL)
**Rule:** API only accepts orders from registered App ID tied to whitelisted static IP.

**Impact on Your System:**
- ❌ Cannot run from home with dynamic IP
- ❌ Cannot run from multiple locations
- ✅ Must use single static IP (cloud hosting or static ISP)

**Implementation Changes Required:**
```php
// Add to Settings
Setting::setValue('static_ip_address', 'xxx.xxx.xxx.xxx', 'string', 'Whitelisted static IP for Fyers API');
Setting::setValue('fyers_app_id', '', 'string', 'New Fyers App ID (post-April 1 2026)');

// Add validation in FyersAuthService
public function validateStaticIP(): bool
{
    $currentIP = request()->ip();
    $whitelistedIP = Setting::getValue('static_ip_address');
    
    if ($currentIP !== $whitelistedIP) {
        $this->logError("IP mismatch: {$currentIP} not whitelisted");
        throw new \Exception('Orders must originate from whitelisted static IP');
    }
    
    return true;
}
```

**Action Items:**
- [ ] Get static IP from cloud provider (Laravel Cloud provides this)
- [ ] Register static IP in Fyers API Dashboard
- [ ] Update `.env` with new static IP

---

### 2. Daily 2FA Authentication (CRITICAL)
**Rule:** Refresh-token flow discontinued. Must complete 2FA once per day.

**Impact on Your System:**
- ❌ Cannot run 24/7 unattended with token refresh
- ✅ Must authenticate daily before market open (9:00 AM)
- ✅ Authentication valid for rest of trading day

**Implementation Changes Required:**

**Update Scheduler:**
```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Pre-market authentication at 8:30 AM IST
    $schedule->call(function () {
        $authService = app(FyersAuthService::class);
        
        // Check if already authenticated today
        if (!$authService->isAuthenticatedToday()) {
            // Send notification to user for manual 2FA
            // System cannot trade until 2FA completed
            $this->notifyUser2FARequired();
        }
    })->dailyAt('08:30')->timezone('Asia/Kolkata');
}
```

**New Method in FyersAuthService:**
```php
public function isAuthenticatedToday(): bool
{
    $lastAuth = Setting::getValue('last_2fa_auth_date');
    return $lastAuth && Carbon::parse($lastAuth)->isToday();
}

public function complete2FA(string $totpCode): bool
{
    // Complete 2FA with Fyers
    // On success:
    Setting::setValue('last_2fa_auth_date', now()->toDateString(), 'string');
    return true;
}
```

**Action Items:**
- [ ] Add 2FA notification system (email/SMS)
- [ ] Create dashboard widget showing auth status
- [ ] Add manual 2FA completion UI
- [ ] Block trading if 2FA not completed by 9:00 AM

---

### 3. Rate Limit: 10 Orders Per Second (IMPORTANT)
**Rule:** Maximum 10 orders/second including placement, SL, target, modifications.

**Impact on Your System:**
- ✅ Low impact - Your system does max 1 trade per day
- ⚠️ Affects exit management (entry + SL + target = 3 orders)
- ⚠️ Affects partial exits (additional 2 orders)

**Implementation Changes Required:**

**Add Rate Limiter:**
```php
// New Service: app/Services/Fyers/RateLimiter.php
namespace App\Services\Fyers;

use Illuminate\Support\Facades\Redis;

class RateLimiter
{
    const MAX_ORDERS_PER_SECOND = 10;
    
    public function checkLimit(): bool
    {
        $key = 'fyers:orders:' . now()->format('Y-m-d-H-i-s');
        $count = Redis::incr($key);
        Redis::expire($key, 1); // 1 second TTL
        
        return $count <= self::MAX_ORDERS_PER_SECOND;
    }
    
    public function waitForSlot(): void
    {
        while (!$this->checkLimit()) {
            usleep(100000); // Wait 100ms
        }
    }
}

// Use in OrderService
public function placeOrder($orderData)
{
    $rateLimiter = new RateLimiter();
    $rateLimiter->waitForSlot(); // Blocks until slot available
    
    return $this->executeFyersOrder($orderData);
}
```

**Action Items:**
- [ ] Implement rate limiter service
- [ ] Add delay between entry/SL/target orders (200ms minimum)
- [ ] Log rate limit hits for monitoring

---

### 4. No Market Orders → MPP Orders (IMPORTANT)
**Rule:** Market orders automatically converted to Market Price Protection orders.

**Impact on Your System:**
- ⚠️ EOD exit at 3:15 PM uses market orders
- ⚠️ MPP adds price protection band - may not fill if slippage too high
- ✅ Partial exits and regular exits less affected

**Implementation Changes Required:**

**Update Trade Placement Logic:**
```php
// In TRADE_PLACEMENT_LOGIC.md Section 3.2 - Update EOD Exit

FUNCTION executeEODExit(trade):
    """
    Hard exit at 3:15 PM - NOW USES MPP INSTEAD OF MARKET
    """
    
    lotsToExit = trade.lotsRemaining OR trade.lots
    currentPremium = getOptionLTP(trade.symbol)
    
    IF isPaperTrade():
        exitFill = currentPremium * (1 - 0.01)
    ELSE:
        // MPP order instead of market order
        // MPP adds ~3-5% price protection band
        exitFill = placeOrder({
            symbol: trade.symbol,
            qty: lotsToExit * 15,
            type: "MARKET", // Fyers converts to MPP automatically
            side: "SELL",
            productType: "INTRADAY"
        })
        
        // MPP may reject if price moves > 5% during execution
        // Add retry with wider limit order as fallback
        IF exitFill.status == "REJECTED":
            exitFill = placeLimitOrder({
                price: currentPremium * 0.90 // Accept 10% slippage for guaranteed exit
            })
```

**Action Items:**
- [ ] Update EOD exit to handle MPP behavior
- [ ] Add fallback limit order if MPP rejected
- [ ] Test MPP order fills in paper trading
- [ ] Document MPP slippage expectations (3-5%)

---

### 5. No AMO Orders (LOW IMPACT)
**Rule:** offlineOrder parameter must always be false.

**Impact on Your System:**
- ✅ No impact - Your system only trades during market hours
- ✅ No pre-market or post-market orders planned

**Implementation:**
```php
// Ensure all orders set offlineOrder = false
$orderData = [
    'symbol' => $symbol,
    'qty' => $qty,
    'type' => $type,
    'side' => $side,
    'offlineOrder' => false, // MANDATORY
];
```

---

### 6. Third-Party Platform Restrictions (CRITICAL)
**Rule:** External platforms must be empanelled and hosted within broker infrastructure.

**Impact on Your System:**
- ✅ You're building custom system, not using third-party
- ✅ Hosted on Laravel Cloud = external server but direct API integration
- ⚠️ Must use new App credentials (not old API keys)

**Action Items:**
- [ ] Get new App credentials from Fyers API Dashboard
- [ ] Activate new trading app (post-April 1 format)
- [ ] Update `.env` with new credentials

---

## 🛠️ Required Actions Before March 31, 2026

### CRITICAL (Do This Week)
1. **Static IP Setup**
   - [ ] Confirm Laravel Cloud provides static IP
   - [ ] Get static IP address
   - [ ] Whitelist in Fyers API Dashboard
   - [ ] Test API connectivity from static IP

2. **New App Credentials**
   - [ ] Login to Fyers API Dashboard
   - [ ] Create new App for post-April 1 framework
   - [ ] Activate trading app
   - [ ] Download new credentials
   - [ ] Update `.env` file

3. **2FA Daily Auth**
   - [ ] Build 2FA notification system
   - [ ] Add authentication status dashboard
   - [ ] Test daily auth flow
   - [ ] Create manual 2FA completion UI

### HIGH PRIORITY (Next Week)
4. **Rate Limiter**
   - [ ] Implement RateLimiter service
   - [ ] Add rate limit checks to all order functions
   - [ ] Test with multiple rapid orders
   - [ ] Add monitoring/logging

5. **MPP Order Handling**
   - [ ] Update EOD exit to handle MPP
   - [ ] Add limit order fallback
   - [ ] Test MPP behavior in paper mode
   - [ ] Document slippage expectations

### MEDIUM PRIORITY
6. **Update Documentation**
   - [ ] Update PRD with SEBI constraints
   - [ ] Update TRADE_PLACEMENT_LOGIC.md
   - [ ] Add SEBI compliance checklist
   - [ ] Update error handling for new rules

---

## 📊 Updated Architecture

### New Authentication Flow
```
8:30 AM (Daily):
┌─────────────────────────────────┐
│ System checks 2FA status        │
└───────────┬─────────────────────┘
            │
     ┌──────▼──────┐
     │ Authenticated?│
     └──────┬──────┘
            │
    ┌───────▼────────┐
    │ NO             │ YES
    │ ↓              │ ↓
    │ Send 2FA       │ Proceed with
    │ notification   │ pre-market setup
    │ ↓              │
    │ Wait for user  │
    │ completion     │
    └────────────────┘
```

### New Order Flow (With Rate Limiting)
```
Order Request
    ↓
Check Rate Limit (10/sec)
    ↓
Wait if needed (100ms check)
    ↓
Place Order (MPP for market orders)
    ↓
Log order count
    ↓
Return confirmation
```

---

## 💰 Cost Impact

### Static IP
- **Laravel Cloud:** Included ✅
- **Alternative (VPS):** ₹500-1,000/month

### Daily 2FA
- No additional cost, but requires daily manual action
- **Recommendation:** Set alarm for 8:30 AM IST to complete 2FA

### Development Impact
- Additional 3-4 days for compliance implementation
- Rate limiter: 1 day
- 2FA flow: 2 days
- MPP handling: 1 day

---

## 🎯 Compliance Checklist

Before April 1, 2026:
- [ ] Static IP obtained and whitelisted
- [ ] New Fyers App credentials activated
- [ ] Daily 2FA flow implemented and tested
- [ ] Rate limiter service deployed
- [ ] MPP order handling updated
- [ ] All orders set offlineOrder = false
- [ ] System tested end-to-end with new rules
- [ ] Documentation updated
- [ ] Backup plan if compliance issues arise

---

## ⚠️ Risks & Mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| Miss April 1 deadline | System stops working | Complete setup this week |
| Forget daily 2FA | No trading for the day | Set recurring alarm, email notification |
| Static IP changes | Orders rejected | Monitor IP, alert on change |
| MPP rejection at EOD | Position held overnight | Fallback limit order with wide band |
| Rate limit hit | Order rejected | Rate limiter + retry queue |

---

## 🆚 Alternative: FYERS Automate

Fyers offers "Automate" - a no-code platform that:
- ✅ Already compliant with new rules
- ✅ Runs within Fyers infrastructure
- ✅ No static IP needed
- ✅ Handles 2FA automatically
- ⚠️ Less flexible than custom code
- ⚠️ Cannot integrate Claude AI easily
- ⚠️ Cannot implement self-learning engine

**Recommendation:** Stick with custom build but ensure SEBI compliance.

Your system is more sophisticated (Claude AI, self-learning, full control). Worth the extra compliance effort.

---

## 📝 Updated Settings (Add These)

```php
// In SettingsSeeder.php, add:

// SEBI Compliance
Setting::setValue('sebi_compliant_mode', true, 'boolean', 'SEBI April 2026 framework enabled');
Setting::setValue('static_ip_address', '', 'string', 'Whitelisted static IP for Fyers API');
Setting::setValue('fyers_new_app_id', '', 'string', 'Fyers App ID (post-April 1 2026)');
Setting::setValue('last_2fa_auth_date', '', 'string', 'Last successful 2FA authentication date');
Setting::setValue('require_daily_2fa', true, 'boolean', 'Require daily 2FA before trading');
Setting::setValue('max_orders_per_second', 10, 'integer', 'SEBI rate limit');
Setting::setValue('use_mpp_orders', true, 'boolean', 'Use MPP instead of market orders');
```

---

## 🚀 Immediate Action Plan

### This Week (CRITICAL)
**Day 1 (Today):**
- [ ] Contact Laravel Cloud support - confirm static IP
- [ ] Login to Fyers API Dashboard
- [ ] Review new App creation process

**Day 2:**
- [ ] Get static IP from hosting provider
- [ ] Create new Fyers App
- [ ] Whitelist static IP
- [ ] Test API connectivity

**Day 3:**
- [ ] Implement RateLimiter service
- [ ] Add 2FA notification system
- [ ] Update SettingsSeeder

**Day 4:**
- [ ] Build 2FA status dashboard widget
- [ ] Test complete auth flow
- [ ] Update order placement for MPP

**Day 5:**
- [ ] End-to-end testing with new rules
- [ ] Update all documentation
- [ ] Deploy to production

**Day 6-7:**
- [ ] Buffer for issues
- [ ] Final testing
- [ ] Go live with new framework

---

## 📚 Resources

- **Fyers Blog:** https://fyers.in/blog/sebi-algo-trading-rules-and-regulations-in-india/
- **API Dashboard:** https://fyers.in/web/api-dashboard
- **App Activation:** https://support.fyers.in/portal/en/kb/articles/how-do-i-activate-the-new-app-for-api-trading-after-april-1-2026

---

## ✅ Bottom Line

**Your system CAN work under new rules**, but requires:
1. Static IP (easy with Laravel Cloud)
2. Daily 2FA at 8:30 AM (manual action)
3. Rate limiting (code update)
4. MPP order handling (code update)
5. New App credentials (one-time setup)

**Estimated additional effort:** 3-4 days  
**Deadline:** March 31, 2026 (7 days away!)  
**Recommendation:** **Prioritize this over Phase 1 development**

---

**Status:** ⚠️ URGENT - Must complete before March 31  
**Next Step:** Get static IP and new App credentials TODAY

---

*Document created: 2026-06-23*  
*Compliance deadline: 2026-03-31 (7 days remaining)*

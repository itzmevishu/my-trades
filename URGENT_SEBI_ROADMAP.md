# 🚨 URGENT: Revised Roadmap - SEBI Compliance Priority

**Date:** 2026-06-23  
**Critical Change:** SEBI rules effective April 1, 2026 (7 days!)

---

## ⚠️ PRIORITY SHIFT

The original roadmap assumed we had 14 weeks. However, **SEBI's new algo trading rules take effect on April 1, 2026** - just **7 days away**.

**We must prioritize compliance IMMEDIATELY** or the system will not be able to place orders.

---

## 🎯 NEW Priority Order

### Phase 0A: SEBI Compliance (URGENT - This Week)
**Duration:** 3-4 days  
**Deadline:** March 31, 2026

**Must Complete:**
1. ✅ Static IP setup and whitelisting
2. ✅ New Fyers App credentials
3. ✅ Daily 2FA authentication flow
4. ✅ Rate limiter implementation (10 orders/sec)
5. ✅ MPP order handling
6. ✅ Validation and testing

**Status:** 
- ✅ RateLimiter service created
- ✅ Settings updated with SEBI config
- ⬜ Static IP needed
- ⬜ New App credentials needed
- ⬜ 2FA flow implementation needed

---

### Revised Schedule

```
WEEK 1 (THIS WEEK - URGENT):
├── Days 1-2: SEBI Compliance Setup
│   ├── Get static IP from Laravel Cloud
│   ├── Register in Fyers API Dashboard
│   ├── Get new App credentials
│   └── Whitelist IP
├── Days 3-4: Compliance Implementation
│   ├── 2FA daily auth flow
│   ├── MPP order handling
│   ├── Rate limiter integration
│   └── End-to-end testing
└── Days 5-7: Buffer & Testing
    └── Final validation before April 1

WEEK 2-3: Data Pipeline (Original Phase 1)
├── Fyers integration (OAuth, candles)
├── EMA calculations
├── Pattern detection
└── HTF analysis

WEEKS 4-14: Continue as per original roadmap
```

---

## 📋 Updated Checklist

### CRITICAL (Before April 1, 2026)
- [ ] **Static IP Setup**
  - [ ] Contact Laravel Cloud support
  - [ ] Get static IP address
  - [ ] Update Fyers API Dashboard
  - [ ] Test API connectivity
  - [ ] Add to `.env`: `STATIC_IP_ADDRESS=xxx.xxx.xxx.xxx`

- [ ] **New Fyers App**
  - [ ] Login to https://fyers.in/web/api-dashboard
  - [ ] Create new App (post-April 1 format)
  - [ ] Activate trading app
  - [ ] Download new credentials
  - [ ] Add to `.env`: `FYERS_NEW_APP_ID=xxxxx`

- [ ] **Daily 2FA Flow**
  - [ ] Add auth status check to pre-market scheduler
  - [ ] Create notification system (email/SMS)
  - [ ] Build dashboard widget for auth status
  - [ ] Add manual 2FA completion interface
  - [ ] Test daily auth cycle

- [ ] **Rate Limiter**
  - [x] Create RateLimiter service ✅
  - [ ] Integrate with OrderService
  - [ ] Test with rapid orders
  - [ ] Add monitoring/logging

- [ ] **MPP Orders**
  - [ ] Update EOD exit logic
  - [ ] Add limit order fallback
  - [ ] Test MPP rejection handling
  - [ ] Document slippage expectations

- [ ] **Final Testing**
  - [ ] Test complete order flow
  - [ ] Verify rate limiting works
  - [ ] Confirm IP validation
  - [ ] Validate 2FA requirement

---

## 🔴 Critical Path

```
Today (June 23):
└── Get static IP + new App credentials

June 24:
└── Implement 2FA flow + rate limiter integration

June 25:
└── MPP handling + testing

June 26-30:
└── End-to-end testing + fixes

March 31:
└── Deadline! System must be compliant
```

---

## 💡 Key Changes to Implementation

### 1. FyersAuthService - Add 2FA Check
```php
public function ensureDailyAuth(): bool
{
    if (!$this->isAuthenticatedToday()) {
        $this->logError('2FA not completed today - trading blocked');
        $this->sendNotification('Complete 2FA to enable trading');
        return false;
    }
    return true;
}
```

### 2. OrderService - Add Rate Limiting
```php
use App\Services\Fyers\RateLimiter;

public function placeOrder($orderData)
{
    // Check 2FA first
    if (!$this->authService->ensureDailyAuth()) {
        throw new \Exception('2FA required');
    }
    
    // Wait for rate limit slot
    $rateLimiter = new RateLimiter();
    $rateLimiter->waitForSlot();
    
    // Place order
    return $this->executeFyersOrder($orderData);
}
```

### 3. Exit Logic - Use MPP
```php
public function executeEODExit($trade)
{
    // MPP order (Fyers auto-converts market orders)
    $order = $this->orderService->placeOrder([
        'type' => 'MARKET',
        'symbol' => $trade->symbol,
        'qty' => $trade->lots * 15,
        'side' => 'SELL',
        'offlineOrder' => false, // MANDATORY
    ]);
    
    // Fallback if MPP rejects
    if ($order->status === 'REJECTED') {
        $this->placeLimitOrderFallback($trade);
    }
}
```

---

## 📊 Impact on Development Timeline

**Original Plan:** 14 weeks to Phase 10  
**Revised Plan:** 14 weeks + 1 week SEBI compliance

**New Timeline:**
- Week 1: SEBI Compliance ⚠️ URGENT
- Week 2-15: Original roadmap continues

**Go-Live Date:** Still achievable for 30-trade paper validation

---

## 🎯 Success Criteria

Before proceeding to Phase 1:
- ✅ Static IP configured and whitelisted
- ✅ New Fyers App activated with credentials
- ✅ Daily 2FA flow implemented and tested
- ✅ Rate limiter deployed and working
- ✅ MPP order handling tested
- ✅ System places test order successfully
- ✅ All SEBI rules validated

**Only then** can we proceed with original Phase 1 (Data Pipeline).

---

## 📞 Immediate Actions

**RIGHT NOW:**
1. Open https://fyers.in/web/api-dashboard
2. Check if your account is ready for new framework
3. Create new App if not already done
4. Contact Laravel Cloud for static IP confirmation

**TODAY:**
1. Get static IP address
2. Whitelist in Fyers Dashboard
3. Download new App credentials
4. Update `.env` file

**THIS WEEK:**
1. Complete all compliance implementation
2. Test end-to-end
3. Be ready before March 31

---

## ⚠️ Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Miss April 1 deadline | Medium | **CRITICAL** | Drop everything, prioritize this |
| Static IP not available | Low | High | Laravel Cloud should provide |
| 2FA causes daily delays | High | Medium | Set alarm, automate notification |
| MPP rejects EOD exit | Low | High | Limit order fallback ready |
| Rate limit blocks trade | Low | Medium | Rate limiter handles it |

---

## 🆘 If You Miss the Deadline

If compliance not ready by April 1:
1. System **cannot place any orders**
2. Trading suspended until compliant
3. Must use Fyers Automate as interim (but lose Claude AI, learning engine)
4. Significant setback to development

**Conclusion:** **Cannot afford to miss this deadline.**

---

## ✅ Bottom Line

**STOP** all other work.  
**FOCUS** on SEBI compliance.  
**DEADLINE:** March 31, 2026 (7 days).

Once compliant, resume original roadmap.

---

**Priority Level:** 🔴 CRITICAL  
**Estimated Effort:** 3-4 days  
**Deadline:** 7 days remaining  
**Status:** In progress

**Next immediate step:** Get static IP and new Fyers credentials TODAY.

---

*Updated: 2026-06-23*  
*Previous roadmap: IMPLEMENTATION_ROADMAP.md (still valid after compliance)*

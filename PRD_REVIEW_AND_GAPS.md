# PRD Review & Implementation Gap Analysis
## BankNifty AI Trading Tool

**Review Date:** 2026-06-23  
**PRD Version:** 1.0  
**Reviewer:** System Analysis

---

## Executive Summary

The PRD is comprehensive and well-structured. However, several critical implementation details are missing that would be required during development. This document identifies these gaps and provides recommendations.

---

## 1. ✅ COMPLETE REQUIREMENTS

### Well-Defined Sections:
- ✅ Trading Strategy (Entry/Exit rules clearly specified)
- ✅ Risk Management (Position sizing formula detailed)
- ✅ Claude AI Integration (Clear role definitions)
- ✅ Self-Learning Engine (Learning cycle logic defined)
- ✅ Database Schema (All tables specified)
- ✅ Dashboard Specification (Filament pages outlined)
- ✅ System Architecture Flow (Daily operating flow charted)
- ✅ Acceptance Criteria (Paper trade gate defined)

---

## 2. ⚠️ MISSING OR INCOMPLETE REQUIREMENTS

### 2.1 Trade Placement Logic - Critical Gaps

#### Entry Signal Detection
- ❌ **Missing**: Precise candle position relative to EMA 20 tolerance
  - *Issue*: "at or above EMA 20" is ambiguous. How close is "at"?
  - *Recommendation*: Define tolerance (e.g., within 0.2% of EMA 20 value)

- ❌ **Missing**: Handling partial candle data during live trading
  - *Issue*: Do we wait for candle close or use live price?
  - *Recommendation*: Specify "wait for 15m candle close confirmation"

#### Strike Selection Algorithm
- ⚠️ **Incomplete**: ATM strike selection when price is exactly between strikes
  - *Current*: "Closest strike to current index price"
  - *Issue*: What if Bank Nifty is at 51,250 and strikes are 51,200 and 51,300?
  - *Recommendation*: Specify rounding rule (round to nearest 100, prefer higher/lower strike)

- ❌ **Missing**: Strike liquidity validation
  - *Issue*: What if ATM strike has no liquidity or wide bid-ask spread?
  - *Recommendation*: Add validation for minimum volume and max spread %

#### Premium-Based Stop Loss Calculation
- ⚠️ **Incomplete**: Real-time delta fetching mechanism
  - *Current*: Assumes delta ≈ 0.5 for ATM
  - *Issue*: Delta changes continuously. When/how do we fetch actual delta?
  - *Recommendation*: Specify if using Greeks from Fyers API or fixed assumption

- ❌ **Missing**: SL adjustment for partial exits
  - *Issue*: After 50% exit at 1:1 RR, how is trailing SL calculated?
  - *Recommendation*: Define trailing SL formula (e.g., move to breakeven, then trail by 50% of profit)

### 2.2 Exception Trade Validation

- ❌ **Missing**: Claude's resistance/support level validation criteria
  - *Issue*: "multiple significant resistance/support levels" is subjective
  - *Recommendation*: Define what makes a level "significant" (e.g., 3+ touches, major round number, previous day high/low)

- ❌ **Missing**: Exception trade frequency limit
  - *Issue*: Could system take exception trades every day?
  - *Recommendation*: Add limit (e.g., max 2 exception trades per 10 trades, or require 3+ regular winning trades before next exception)

### 2.3 Market Data & API Integration

- ❌ **Missing**: Fyers API rate limits and handling
  - *Issue*: No specification for API call limits, retry logic, or fallback
  - *Recommendation*: Define max API calls per minute, exponential backoff strategy

- ❌ **Missing**: Candle data validation rules
  - *Issue*: What if API returns invalid/missing candle data?
  - *Recommendation*: Add validation (OHLC relationships, no zero values, timestamp sequence)

- ❌ **Missing**: Historical candle data requirement
  - *Issue*: How many candles needed to calculate EMA 200?
  - *Recommendation*: Specify minimum candles (e.g., 200+ candles for EMA 200)

### 2.4 Order Execution Details

- ❌ **Missing**: Paper trade simulation mechanics
  - *Issue*: How are fills simulated? At LTP? With slippage?
  - *Recommendation*: Define paper trade fill logic (e.g., fill at LTP with 0.5% slippage simulation)

- ❌ **Missing**: Order placement retry logic
  - *Issue*: What if order placement fails in paper/live mode?
  - *Recommendation*: Define retry attempts, timeout, and fallback (log as failed trade)

- ❌ **Missing**: Partial fill handling
  - *Issue*: In live mode, what if only 1 of 2 lots gets filled?
  - *Recommendation*: Specify if accepting partial fills or cancel and retry

### 2.5 Exit Management

- ❌ **Missing**: SL hit detection mechanism
  - *Issue*: Polling interval? WebSocket? LTP vs bid/ask?
  - *Recommendation*: Use WebSocket for real-time monitoring, check LTP against SL premium level every tick

- ❌ **Missing**: Simultaneous SL and Target hit scenario
  - *Issue*: Market can gap through both levels
  - *Recommendation*: Define priority (SL takes precedence, or whichever timestamps first)

- ❌ **Missing**: Hard EOD exit (3:15 PM) mechanism
  - *Issue*: Market order? Limit order? What if no liquidity?
  - *Recommendation*: Use market order for guaranteed exit, log actual fill price

### 2.6 Learning Engine Specifics

- ⚠️ **Incomplete**: Learning cycle trigger timing
  - *Current*: "Every 10 completed trades"
  - *Issue*: Does this run immediately after 10th trade or at EOD?
  - *Recommendation*: Specify trigger timing (e.g., "EOD after day when 10th trade completes")

- ❌ **Missing**: Learning engine override mechanism
  - *Issue*: What if learning engine suggests bad config change?
  - *Recommendation*: Add manual review flag for first 3 learning cycles before auto-apply

- ❌ **Missing**: Rollback mechanism for bad config
  - *Issue*: If new config causes losses, how to revert?
  - *Recommendation*: Keep config version history, allow manual rollback, or auto-rollback if win rate drops 20%+

### 2.7 Error Handling & Recovery

- ❌ **Missing**: System failure recovery protocol
  - *Issue*: What if system crashes mid-trade?
  - *Recommendation*: Define recovery process (check Redis for active trade, resume monitoring)

- ❌ **Missing**: Notification system specification
  - *Issue*: How does trader know about trades, errors, system status?
  - *Recommendation*: Define notification channels (email, Telegram listed as future, but need alerts for critical events)

- ❌ **Missing**: Duplicate trade prevention mechanism
  - *Issue*: "One-trade-per-day lock in Redis" mentioned but not detailed
  - *Recommendation*: Define Redis key structure, TTL, lock/unlock logic, distributed lock handling

### 2.8 Settings & Configuration

- ❌ **Missing**: Configuration change impact handling
  - *Issue*: If capital changes mid-day, does it affect active trade?
  - *Recommendation*: Define config change rules (only apply next trading day, or block changes during market hours)

- ❌ **Missing**: Paper to live transition safeguards
  - *Issue*: Beyond 30 trades criteria, what prevents accidental live toggle?
  - *Recommendation*: Add confirmation dialog, require password, or add "staging mode" with 1 lot only

### 2.9 Monitoring & Observability

- ❌ **Missing**: Logging specification
  - *Issue*: What gets logged, where, and with what retention?
  - *Recommendation*: Define log levels, what to log (all API calls, Claude responses, trade decisions), retention policy

- ❌ **Missing**: Performance metrics and alerting
  - *Issue*: No uptime monitoring, latency tracking, or alerting thresholds
  - *Recommendation*: Define SLAs (e.g., signal processing < 5 seconds, 99.9% uptime)

### 2.10 Security & Authentication

- ❌ **Missing**: Fyers credential management
  - *Issue*: How are API keys stored? Encrypted? Rotated?
  - *Recommendation*: Use Laravel encrypted config, environment variables, never commit keys

- ❌ **Missing**: Dashboard authentication
  - *Issue*: Single user? Multi-user? Two-factor auth?
  - *Recommendation*: Implement Laravel auth, at minimum basic password protection

---

## 3. 🔍 TRADE PLACEMENT LOGIC - DETAILED REVIEW

### 3.1 Current Specification Analysis

The PRD defines trade placement in multiple sections. Let me consolidate and analyze:

#### Entry Signal Flow (Reconstructed)
```
1. Time Check: Is current time between 11:15 AM - 2:00 PM? ✓
2. Trade Lock: Has a trade been taken today? ✓
3. Market Filter: Is today expiry/RBI/Budget day? ✓
4. Candle Fetch: Get latest 15m candle (must be closed) ? [MISSING]
5. EMA Calculation: Compute EMA 20/100/200 ✓
6. Pattern Detection: Identify candle pattern ✓
7. HTF Analysis: Check Monthly/Weekly/Daily alignment ✓
8. Primary Rule Check:
   - Long: Price at/above EMA 20 + HTF bullish ✓
   - Short: Price at/below EMA 20 + HTF bearish ✓
9. Exception Rule Check:
   - Counter-EMA + Claude resistance/support validation ? [VAGUE]
10. Claude Scoring: Get confluence score (6+ or 7.5+ for exception) ✓
11. Risk Calculation:
    - ATM strike selection ? [INCOMPLETE]
    - Index SL level determination ? [MISSING]
    - Premium SL calculation ✓
    - Lot size calculation ✓
12. Order Placement:
    - Paper trade: ? [MECHANISM MISSING]
    - Set trade lock in Redis ✓
13. Exit Monitor Activation:
    - SL monitoring ? [MECHANISM MISSING]
    - Target monitoring ? [MECHANISM MISSING]
    - EOD exit ? [MECHANISM MISSING]
```

### 3.2 Critical Logic Gaps in Trade Placement

#### 🚨 Gap 1: Index SL Level Determination
**Issue**: PRD says "premium-based SL at swing high/low" but doesn't specify:
- How to identify swing high/low programmatically?
- How many candles back to look?
- What if no clear swing level exists?

**Recommendation**:
```
Define swing identification algorithm:
- For Long: SL = Lowest low of last N candles on 15m chart
- For Short: SL = Highest high of last N candles on 15m chart
- Default N = 5 candles (75 minutes)
- Minimum SL distance: 50 points from entry
- Maximum SL distance: 200 points from entry (1% risk protection)
```

#### 🚨 Gap 2: Real-Time Premium Monitoring
**Issue**: No specification for how to monitor option premium in real-time

**Recommendation**:
```
Use Fyers WebSocket for real-time data:
- Subscribe to selected strike symbol on trade entry
- Monitor LTP (Last Traded Price) every tick
- Compare LTP vs SL premium level
- If LTP <= SL premium: trigger exit
- If LTP >= Target premium: trigger exit
- Implement 3-second confirmation window to avoid false triggers
```

#### 🚨 Gap 3: Partial Exit Mechanics
**Issue**: "Exit 50% at 1:1 RR, trail remaining" - not detailed

**Recommendation**:
```
Partial Exit Logic:
1. When LTP >= Entry Premium + Risk Amount (1:1 RR reached):
   - Exit 50% of lots (round down if odd)
   - Update SL to Entry Premium (breakeven)
   - Calculate new trailing SL: Entry + (Current Profit × 0.5)
2. Update trailing SL every 15m candle close if in profit
3. Exit remaining 50% when:
   - Trailing SL hit OR
   - 2:1 RR target hit OR
   - 3:15 PM hard exit
```

#### 🚨 Gap 4: HTF Alignment Algorithm
**Issue**: "All four timeframes must align" - no algorithm specified

**Recommendation**:
```
HTF Alignment Detection:
1. Monthly Trend: EMA 20 slope + close vs EMA 200
   - Bullish: EMA 20 > EMA 200 and candle closed above EMA 20
   - Bearish: EMA 20 < EMA 200 and candle closed below EMA 20
2. Weekly Trend: Same as monthly
3. Daily Trend: Same as monthly
4. 15m Trend: Price position relative to EMA 20 (entry trigger)

Alignment Check:
- Bullish Alignment: All 4 timeframes show bullish trend
- Bearish Alignment: All 4 timeframes show bearish trend
- No Alignment: Skip trade (standard rule)
- Exception: Claude override with ≥7.5 score
```

#### 🚨 Gap 5: Claude API Integration Flow
**Issue**: No specification for API call structure, prompt engineering, or response parsing

**Recommendation**:
```
Claude API Call Structure:

1. Pre-Trade Analysis Request:
   Payload: {
     "model": "claude-sonnet-4.5",
     "messages": [{
       "role": "user",
       "content": "Analyze this Bank Nifty setup..."
     }],
     "max_tokens": 2000
   }

2. Structured Prompt Template:
   """
   You are a price action expert analyzing a Bank Nifty options setup.
   
   Market Data:
   - Monthly Trend: {monthly_bias}
   - Weekly Trend: {weekly_bias}
   - Daily Trend: {daily_bias}
   - 15m Candle: {pattern_name}
   - Price vs EMA 20: {distance}
   - EMA Configuration: {ema_config}
   
   Historical Context:
   - Recent pattern performance: {pattern_stats}
   - Session slot performance: {session_stats}
   
   Task:
   1. Score this setup 1-10 based on confluence factors
   2. Provide plain English reasoning
   3. Identify any resistance/support if counter-EMA
   4. Return JSON: {"score": X.X, "reasoning": "...", "exception_valid": true/false}
   """

3. Response Parsing:
   - Extract JSON from Claude response
   - Validate score is between 1-10
   - Store full reasoning text
   - Use exception_valid flag for rare counter-EMA trades
```

---

## 4. 📋 IMPLEMENTATION PRIORITY MATRIX

### Phase 0: Foundation Setup (Week 1)
**Priority: CRITICAL**
- [ ] Database migrations for all tables
- [ ] Base models: Trade, StrategyConfig, LearningLog, DailyReport
- [ ] Settings table/config system
- [ ] Fyers API authentication service skeleton
- [ ] Redis setup for trade lock mechanism

### Phase 1: Data Pipeline (Week 2)
**Priority: CRITICAL**
- [ ] Fyers candle fetcher (15m, Daily, Weekly, Monthly)
- [ ] EMA calculation service (20, 100, 200)
- [ ] Candle cache system
- [ ] Data validation layer
- [ ] Market calendar integration

### Phase 2: Signal Generation (Week 3)
**Priority: CRITICAL**
- [ ] Pattern detector (engulfing, pin bar, inside bar, EMA rejection)
- [ ] HTF analyzer with alignment algorithm
- [ ] Entry rule engine (primary long/short)
- [ ] Time window validator (11:15 AM - 2:00 PM)
- [ ] One-trade-per-day lock system

### Phase 3: Claude Integration (Week 4)
**Priority: HIGH
- [ ] Claude API service wrapper
- [ ] Prompt engineering for confluence scoring
- [ ] Response parsing and validation
- [ ] Exception trade validator
- [ ] Claude reasoning storage

### Phase 4: Risk Management (Week 5)
**Priority: CRITICAL**
- [ ] Swing high/low detection algorithm
- [ ] Premium-based SL calculator
- [ ] Position sizing engine
- [ ] ATM strike selector
- [ ] Lot size validator

### Phase 5: Trade Execution (Week 6)
**Priority: CRITICAL**
- [ ] Paper trade service (fill simulation)
- [ ] Order placement wrapper
- [ ] Trade logger (full context storage)
- [ ] Entry confirmation system
- [ ] Trade state machine

### Phase 6: Exit Management (Week 7)
**Priority: CRITICAL**
- [ ] WebSocket premium monitor
- [ ] SL hit detector
- [ ] Target hit detector
- [ ] Partial exit executor
- [ ] EOD hard exit (3:15 PM)
- [ ] Trailing SL updater

### Phase 7: Learning Engine (Week 8)
**Priority: HIGH**
- [ ] Trade history analyzer
- [ ] 10-trade trigger system
- [ ] Claude learning API integration
- [ ] Config update applier
- [ ] Learning log writer
- [ ] Rollback mechanism

### Phase 8: Dashboard (Week 9-10)
**Priority: MEDIUM**
- [ ] Filament setup
- [ ] Home/Live page (system status, active trade)
- [ ] Trades page (list, filters, expandable details)
- [ ] Analysis page (metrics, charts, equity curve)
- [ ] Learning Log page
- [ ] Reports page
- [ ] Settings page

### Phase 9: Reporting (Week 11)
**Priority: MEDIUM**
- [ ] Daily report generator
- [ ] Weekly report generator
- [ ] Monthly report generator
- [ ] Report display UI
- [ ] Historical report archive

### Phase 10: Testing & Validation (Week 12)
**Priority: CRITICAL**
- [ ] Unit tests for all services
- [ ] Integration tests for trade flow
- [ ] Paper trade validation (30+ trades)
- [ ] Acceptance criteria verification
- [ ] Performance testing

---

## 5. 🎯 RECOMMENDATIONS FOR IMMEDIATE ACTION

### Before Development Starts:

1. **Define Missing Algorithms** (Critical)
   - Document swing high/low detection logic
   - Define HTF alignment scoring algorithm
   - Specify strike selection tie-breaking rules
   - Create Claude prompt templates

2. **API Integration Specifications** (Critical)
   - Document all Fyers API endpoints needed
   - Define rate limit handling strategy
   - Create error code mapping and retry logic
   - Plan WebSocket connection management

3. **Error Handling Playbook** (Critical)
   - Define behavior for each failure scenario
   - Create notification priority matrix
   - Establish recovery procedures
   - Plan graceful degradation paths

4. **Testing Strategy** (High Priority)
   - Define paper trade validation criteria
   - Create test data sets for pattern detection
   - Plan mock API responses for testing
   - Establish performance benchmarks

5. **Security Hardening** (High Priority)
   - Plan credential storage and rotation
   - Define dashboard access controls
   - Establish audit logging requirements
   - Plan backup and disaster recovery

---

## 6. 📊 RISK ASSESSMENT

### High-Risk Areas Requiring Clarification:

| Risk Area | Impact | Likelihood | Mitigation Needed |
|-----------|--------|------------|-------------------|
| Ambiguous EMA position logic | High | High | Define tolerance threshold |
| Missing SL level algorithm | Critical | High | Document swing detection |
| No WebSocket monitoring spec | Critical | Medium | Define real-time monitoring |
| Vague exception trade rules | Medium | High | Quantify "significant levels" |
| No API failure handling | High | Medium | Define retry and fallback |
| Missing partial fill logic | Medium | Low | Specify order management |
| No config change safeguards | Medium | Medium | Block mid-day changes |
| Weak live trading gate | High | Low | Add multi-step confirmation |

---

## 7. ✅ CONCLUSION

### PRD Quality: **85/100**
- Strong strategic vision ✓
- Well-defined business logic ✓
- Clear acceptance criteria ✓
- Good documentation structure ✓
- Missing critical implementation details ⚠️

### Readiness for Development: **60%**
- Core strategy logic is clear
- Technical implementation gaps exist
- Algorithm specifications needed
- Error handling undefined
- Testing strategy incomplete

### Next Steps:
1. Review this gap analysis with stakeholders
2. Fill in missing algorithm specifications
3. Document API integration details
4. Create error handling playbook
5. Begin Phase 0 foundation work

---

**Analysis Complete** | Generated: 2026-06-23

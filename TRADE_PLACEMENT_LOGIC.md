# Trade Placement Logic - Detailed Specification
## BankNifty AI Trading Tool

**Version:** 1.0  
**Date:** 2026-06-23

---

## Overview

This document provides a comprehensive, implementation-ready specification for the trade placement logic. It fills in all gaps identified in the PRD review and provides algorithmic details for developers.

---

## 1. SIGNAL DETECTION FLOW

### 1.1 Complete Entry Signal Algorithm

```
FUNCTION checkForEntrySignal():
    
    // ===== STEP 1: PREREQUISITE CHECKS =====
    IF NOT isMarketOpen():
        RETURN "Market closed"
    
    IF NOT isInTradingWindow():  // 11:15 AM - 2:00 PM
        RETURN "Outside trading window"
    
    IF hasTradeToday():  // Check Redis lock
        RETURN "Trade already taken today"
    
    IF isHighImpactDay():  // Check market calendar
        RETURN "High impact event - skip trading"
    
    IF isDaysToExpiry() < 1:  // Expiry day
        RETURN "Expiry day - skip trading"
    
    // ===== STEP 2: FETCH MARKET DATA =====
    candles15m = fetchCandles("BANKNIFTY", "15m", limit=200)
    candlesDaily = fetchCandles("BANKNIFTY", "1D", limit=200)
    candlesWeekly = fetchCandles("BANKNIFTY", "1W", limit=200)
    candlesMonthly = fetchCandles("BANKNIFTY", "1M", limit=200)
    
    IF any candle data is invalid:
        RETURN "Invalid market data"
    
    // ===== STEP 3: WAIT FOR CANDLE CLOSE =====
    latestCandle = candles15m[0]
    IF NOT isCandleClosed(latestCandle, "15m"):
        RETURN "Waiting for 15m candle close"
    
    // ===== STEP 4: COMPUTE EMAs =====
    ema20_15m = calculateEMA(candles15m, 20)
    ema100_15m = calculateEMA(candles15m, 100)
    ema200_15m = calculateEMA(candles15m, 200)
    
    ema20_daily = calculateEMA(candlesDaily, 20)
    ema200_daily = calculateEMA(candlesDaily, 200)
    
    ema20_weekly = calculateEMA(candlesWeekly, 20)
    ema200_weekly = calculateEMA(candlesWeekly, 200)
    
    ema20_monthly = calculateEMA(candlesMonthly, 20)
    ema200_monthly = calculateEMA(candlesMonthly, 200)
    
    // ===== STEP 5: PATTERN DETECTION =====
    pattern = detectPattern(candles15m)  // Returns: engulfing, pin_bar, inside_bar, ema_rejection, doji
    
    // ===== STEP 6: HTF TREND ANALYSIS =====
    htfBias = analyzeHTFTrend(
        candlesMonthly, ema20_monthly, ema200_monthly,
        candlesWeekly, ema20_weekly, ema200_weekly,
        candlesDaily, ema20_daily, ema200_daily
    )
    // Returns: "bullish", "bearish", "neutral"
    
    // ===== STEP 7: PRIMARY ENTRY RULES =====
    currentClose = latestCandle.close
    direction = NULL
    isException = FALSE
    
    // Check Long Setup
    IF isNearEMA(currentClose, ema20_15m, tolerance=0.3%):  // Within 0.3% of EMA 20
        IF currentClose >= ema20_15m:  // At or above
            IF htfBias == "bullish":
                direction = "LONG"
    
    // Check Short Setup
    IF isNearEMA(currentClose, ema20_15m, tolerance=0.3%):
        IF currentClose <= ema20_15m:  // At or below
            IF htfBias == "bearish":
                direction = "SHORT"
    
    // Check Exception Trade (Counter-EMA)
    IF direction == NULL:
        exceptionAnalysis = checkExceptionTrade(candles15m, currentClose, ema20_15m, htfBias)
        IF exceptionAnalysis.isValid:
            direction = exceptionAnalysis.direction
            isException = TRUE
    
    IF direction == NULL:
        RETURN "No valid setup"
    
    // ===== STEP 8: CLAUDE CONFLUENCE SCORING =====
    setupData = {
        "direction": direction,
        "pattern": pattern,
        "htfBias": htfBias,
        "priceVsEMA20": currentClose - ema20_15m,
        "emaConfig": {
            "ema20": ema20_15m,
            "ema100": ema100_15m,
            "ema200": ema200_15m
        },
        "candles": candles15m[0:10],  // Last 10 candles for context
        "isException": isException,
        "sessionSlot": getCurrentSessionSlot(),  // 11:15-12:00, 12:00-13:00, 13:00-14:00
        "historicalPatternStats": getPatternStats(pattern)
    }
    
    claudeResponse = callClaudeAPI(setupData)
    score = claudeResponse.score
    reasoning = claudeResponse.reasoning
    
    // ===== STEP 9: SCORE VALIDATION =====
    minScore = getMinScoreThreshold()  // From settings, default 6.0
    
    IF isException:
        IF score < 7.5:
            RETURN "Exception trade score too low: " + score
    ELSE:
        IF score < minScore:
            RETURN "Confluence score too low: " + score
    
    // ===== STEP 10: POSITION SIZING & RISK =====
    indexPrice = getBankNiftySpotPrice()
    slLevel = calculateSLLevel(candles15m, direction)
    
    riskData = calculateRisk(
        indexPrice,
        slLevel,
        direction,
        getCapital(),
        getRiskPercentage()
    )
    
    IF riskData.lots < 1:
        RETURN "Risk calculation results in 0 lots"
    
    // ===== STEP 11: STRIKE SELECTION =====
    strike = selectATMStrike(indexPrice, direction)
    optionSymbol = buildOptionSymbol(strike, direction)
    
    // ===== STEP 12: EXECUTE TRADE =====
    trade = executeTrade({
        "direction": direction,
        "symbol": optionSymbol,
        "strike": strike,
        "lots": riskData.lots,
        "entryPremium": riskData.entryPremium,
        "slPremium": riskData.slPremium,
        "targetPremium": riskData.targetPremium,
        "pattern": pattern,
        "htfBias": htfBias,
        "claudeScore": score,
        "claudeReasoning": reasoning,
        "isException": isException
    })
    
    // ===== STEP 13: SET TRADE LOCK =====
    setTradeLock()  // Redis lock with 24h TTL
    
    // ===== STEP 14: START EXIT MONITORING =====
    startExitMonitor(trade.id)
    
    RETURN "Trade placed: " + trade.id
```

---

## 2. DETAILED ALGORITHMS

### 2.1 Candle Close Detection

```
FUNCTION isCandleClosed(candle, timeframe):
    currentTime = now()
    candleTimestamp = candle.timestamp
    
    IF timeframe == "15m":
        interval = 15 * 60  // 15 minutes in seconds
    ELSE IF timeframe == "1D":
        interval = 24 * 60 * 60
    // ... other timeframes
    
    nextCandleTime = candleTimestamp + interval
    
    // Candle is closed if we're past its end time
    RETURN currentTime >= nextCandleTime
```

### 2.2 EMA Proximity Check

```
FUNCTION isNearEMA(price, emaValue, tolerance):
    """
    Checks if price is within tolerance % of EMA
    tolerance = 0.3 means within 0.3% of EMA value
    """
    toleranceAmount = emaValue * (tolerance / 100)
    upperBound = emaValue + toleranceAmount
    lowerBound = emaValue - toleranceAmount
    
    RETURN price >= lowerBound AND price <= upperBound
```

### 2.3 HTF Trend Analysis

```
FUNCTION analyzeHTFTrend(candlesMonthly, ema20M, ema200M, 
                        candlesWeekly, ema20W, ema200W,
                        candlesDaily, ema20D, ema200D):
    
    // Analyze each timeframe
    monthlyTrend = getTrendDirection(candlesMonthly, ema20M, ema200M)
    weeklyTrend = getTrendDirection(candlesWeekly, ema20W, ema200W)
    dailyTrend = getTrendDirection(candlesDaily, ema20D, ema200D)
    
    // Check alignment
    IF monthlyTrend == "bullish" AND weeklyTrend == "bullish" AND dailyTrend == "bullish":
        RETURN "bullish"
    
    IF monthlyTrend == "bearish" AND weeklyTrend == "bearish" AND dailyTrend == "bearish":
        RETURN "bearish"
    
    RETURN "neutral"  // No alignment

FUNCTION getTrendDirection(candles, ema20, ema200):
    latestCandle = candles[0]
    
    // Criteria for bullish:
    // 1. EMA 20 > EMA 200
    // 2. Latest candle closed above EMA 20
    // 3. EMA 20 slope is positive
    
    emaSlope = (ema20 - calculateEMA(candles[1:], 20)) / ema20
    
    IF ema20 > ema200 AND latestCandle.close > ema20 AND emaSlope > 0:
        RETURN "bullish"
    
    IF ema20 < ema200 AND latestCandle.close < ema20 AND emaSlope < 0:
        RETURN "bearish"
    
    RETURN "neutral"
```

### 2.4 Exception Trade Validation

```
FUNCTION checkExceptionTrade(candles15m, currentPrice, ema20, htfBias):
    """
    Rare exception: Allow counter-EMA trade if multiple significant 
    resistance/support levels exist. Claude is the sole judge.
    """
    
    // Quick checks first
    IF htfBias == "neutral":
        RETURN {isValid: FALSE}
    
    // Check if this is counter to EMA position
    isCounterTrade = FALSE
    direction = NULL
    
    IF currentPrice > ema20 AND htfBias == "bullish":
        // Price above EMA but want to short
        isCounterTrade = TRUE
        direction = "SHORT"
    
    IF currentPrice < ema20 AND htfBias == "bearish":
        // Price below EMA but want to long
        isCounterTrade = TRUE
        direction = "LONG"
    
    IF NOT isCounterTrade:
        RETURN {isValid: FALSE}
    
    // Identify potential resistance/support levels
    levels = identifyKeyLevels(candles15m, lookback=50)
    
    // Claude analyzes if these levels are significant enough
    claudeAnalysis = callClaudeExceptionValidator({
        "direction": direction,
        "currentPrice": currentPrice,
        "ema20": ema20,
        "htfBias": htfBias,
        "keyLevels": levels,
        "recentCandles": candles15m[0:20]
    })
    
    RETURN {
        isValid: claudeAnalysis.exceptionValid,
        direction: direction,
        reasoning: claudeAnalysis.reasoning
    }

FUNCTION identifyKeyLevels(candles, lookback):
    """
    Identifies significant price levels based on:
    - Previous swing highs/lows
    - Round numbers (100-point intervals)
    - High volume nodes
    - Previous day high/low
    """
    levels = []
    
    // Find swing highs and lows
    FOR i = 2 TO lookback:
        // Swing high: high[i] > high[i-1] AND high[i] > high[i+1]
        IF candles[i].high > candles[i-1].high AND candles[i].high > candles[i-2].high:
            IF candles[i].high > candles[i+1].high AND candles[i].high > candles[i+2].high:
                levels.push({
                    type: "swing_high",
                    price: candles[i].high,
                    touches: countTouches(candles, candles[i].high, tolerance=20)
                })
        
        // Similar logic for swing low
        IF candles[i].low < candles[i-1].low AND candles[i].low < candles[i-2].low:
            IF candles[i].low < candles[i+1].low AND candles[i].low < candles[i+2].low:
                levels.push({
                    type: "swing_low",
                    price: candles[i].low,
                    touches: countTouches(candles, candles[i].low, tolerance=20)
                })
    
    // Add round number levels (51000, 51500, 52000, etc.)
    currentPrice = candles[0].close
    FOR price = ROUND(currentPrice, 500) - 1000 TO currentPrice + 1000 STEP 500:
        touches = countTouches(candles, price, tolerance=30)
        IF touches >= 2:
            levels.push({
                type: "round_number",
                price: price,
                touches: touches
            })
    
    // Filter and rank by significance
    significantLevels = levels.filter(level => level.touches >= 3)
    significantLevels.sortBy("touches", descending=TRUE)
    
    RETURN significantLevels.slice(0, 5)  // Top 5 most significant
```

### 2.5 Stop Loss Level Calculation

```
FUNCTION calculateSLLevel(candles15m, direction):
    """
    Determines index SL level based on swing highs/lows
    """
    
    lookbackCandles = 5  // Last 75 minutes (5 x 15m candles)
    
    IF direction == "LONG":
        // SL below recent swing low
        swingLow = MIN(candles15m[0:lookbackCandles].map(c => c.low))
        slLevel = swingLow - 10  // Add buffer of 10 points
    
    ELSE IF direction == "SHORT":
        // SL above recent swing high
        swingHigh = MAX(candles15m[0:lookbackCandles].map(c => c.high))
        slLevel = swingHigh + 10  // Add buffer of 10 points
    
    // Validate SL distance
    currentPrice = candles15m[0].close
    slDistance = ABS(currentPrice - slLevel)
    
    // Enforce minimum and maximum SL distance
    IF slDistance < 50:
        slDistance = 50  // Minimum 50 points
    
    IF slDistance > 250:
        slDistance = 250  // Maximum 250 points (prevents excessive risk)
    
    // Recalculate SL with enforced distance
    IF direction == "LONG":
        slLevel = currentPrice - slDistance
    ELSE:
        slLevel = currentPrice + slDistance
    
    RETURN {
        slLevel: slLevel,
        slDistance: slDistance
    }
```

### 2.6 Risk Calculation & Position Sizing

```
FUNCTION calculateRisk(indexPrice, slLevel, direction, capital, riskPercent):
    """
    Calculates premium-based SL, target, and lot size
    """
    
    // Index SL distance
    slDistance = ABS(indexPrice - slLevel)
    
    // Get or assume ATM delta
    atmDelta = getSettingOrDefault("sl_delta_assumption", 0.5)
    
    // Premium drop at SL = Index SL distance × Delta
    premiumSLDrop = slDistance * atmDelta
    
    // Get current option premium (simulated for paper trade)
    strike = selectATMStrike(indexPrice, direction)
    currentPremium = getOptionPremium(strike, direction)  // Paper: LTP; Live: Bid for sell, Ask for buy
    
    // Calculate SL premium
    slPremium = currentPremium - premiumSLDrop
    
    // Ensure SL premium is positive
    IF slPremium < 10:
        slPremium = 10  // Minimum ₹10 SL to avoid too tight
    
    // Calculate target at 2:1 RR
    premiumGain = premiumSLDrop * 2
    targetPremium = currentPremium + premiumGain
    
    // Position sizing
    maxRiskAmount = capital * (riskPercent / 100)
    lotSize = 15  // Bank Nifty option lot size
    
    riskPerLot = premiumSLDrop * lotSize
    
    // Calculate max lots within risk limit
    maxLots = FLOOR(maxRiskAmount / riskPerLot)
    
    // Ensure at least 1 lot if risk allows
    IF maxLots < 1:
        maxLots = 0  // Can't trade if risk exceeds limit
    
    // Apply expiry-based lot reduction
    daysToExpiry = getDaysToExpiry()
    IF daysToExpiry < 7:
        maxLots = FLOOR(maxLots * 0.7)  // Reduce by 30%
    
    RETURN {
        lots: maxLots,
        entryPremium: currentPremium,
        slPremium: slPremium,
        targetPremium: targetPremium,
        slDistance: slDistance,
        riskPerLot: riskPerLot,
        totalRisk: riskPerLot * maxLots
    }
```

### 2.7 ATM Strike Selection

```
FUNCTION selectATMStrike(indexPrice, direction):
    """
    Selects the At-The-Money strike closest to current index price
    """
    
    strikeInterval = 100  // Bank Nifty strikes are in 100-point intervals
    
    // Round to nearest strike
    lowerStrike = FLOOR(indexPrice / strikeInterval) * strikeInterval
    upperStrike = lowerStrike + strikeInterval
    
    // Calculate distances
    distanceToLower = indexPrice - lowerStrike
    distanceToUpper = upperStrike - indexPrice
    
    // Select closest strike
    IF distanceToLower < distanceToUpper:
        atmStrike = lowerStrike
    ELSE IF distanceToUpper < distanceToLower:
        atmStrike = upperStrike
    ELSE:
        // Exactly in the middle
        // For LONG (CE), prefer higher strike (more conservative)
        // For SHORT (PE), prefer lower strike (more conservative)
        IF direction == "LONG":
            atmStrike = upperStrike
        ELSE:
            atmStrike = lowerStrike
    
    RETURN atmStrike

FUNCTION buildOptionSymbol(strike, direction):
    """
    Builds Fyers option symbol format
    Format: NSE:BANKNIFTY26JUN2651200CE
    """
    
    expiry = getMonthlyExpiry()  // Last Thursday of current month
    expiryFormatted = expiry.format("YYMMMDD").toUpper()  // 26JUN26
    
    optionType = (direction == "LONG") ? "CE" : "PE"
    
    symbol = "NSE:BANKNIFTY" + expiryFormatted + strike + optionType
    
    RETURN symbol
```

---

## 3. EXIT MANAGEMENT

### 3.1 Exit Monitor Service

```
FUNCTION startExitMonitor(tradeId):
    """
    Starts WebSocket monitoring for the active trade
    Monitors: SL hit, Target hit, Partial exit, EOD exit
    """
    
    trade = getTrade(tradeId)
    
    // Subscribe to WebSocket for real-time premium
    websocket = subscribeToWebSocket(trade.symbol)
    
    // State tracking
    partialExited = FALSE
    trailingSlPremium = trade.slPremium
    
    // Monitor loop
    WHILE trade.status == "ACTIVE":
        
        // Check EOD exit first (highest priority)
        IF currentTime >= "15:15:00":
            executeEODExit(trade)
            BREAK
        
        // Get latest premium from WebSocket
        currentPremium = websocket.getLatestLTP()
        
        // Check SL hit (second priority)
        IF currentPremium <= trailingSlPremium:
            executeSLExit(trade, currentPremium)
            BREAK
        
        // Check target hit (2:1 RR)
        IF currentPremium >= trade.targetPremium:
            executeTargetExit(trade, currentPremium)
            BREAK
        
        // Check partial exit (1:1 RR)
        IF NOT partialExited:
            rrRatio = (currentPremium - trade.entryPremium) / (trade.entryPremium - trade.slPremium)
            IF rrRatio >= 1.0:
                executePartialExit(trade, currentPremium)
                partialExited = TRUE
                // Move SL to breakeven
                trailingSlPremium = trade.entryPremium
        
        // Update trailing SL every 15m if in profit and partial exited
        IF partialExited:
            IF shouldUpdateTrailingSL():  // Every 15m candle close
                trailingSlPremium = calculateTrailingSL(trade.entryPremium, currentPremium)
                updateTrailingSL(trade, trailingSlPremium)
        
        SLEEP(1 second)  // Check every second
    
    // Cleanup
    websocket.unsubscribe()

FUNCTION calculateTrailingSL(entryPremium, currentPremium):
    """
    Trailing SL = Entry + (50% of current profit)
    """
    profit = currentPremium - entryPremium
    trailingSL = entryPremium + (profit * 0.5)
    
    RETURN trailingSL
```

### 3.2 Exit Execution Functions

```
FUNCTION executePartialExit(trade, exitPremium):
    """
    Exits 50% of position at 1:1 RR
    """
    
    lotsToExit = FLOOR(trade.lots / 2)
    
    IF lotsToExit < 1:
        RETURN  // Can't exit partial if only 1 lot
    
    // Paper trade: simulate exit
    IF isPaperTrade():
        exitFill = exitPremium * (1 - 0.005)  // 0.5% slippage simulation
    ELSE:
        exitFill = placeMarketOrder(trade.symbol, "SELL", lotsToExit)
    
    // Update trade record
    trade.partialExitPremium = exitFill
    trade.partialExitTime = now()
    trade.lotsRemaining = trade.lots - lotsToExit
    
    pnlPartial = (exitFill - trade.entryPremium) * lotsToExit * 15
    trade.pnlRealized += pnlPartial
    
    saveTrade(trade)
    
    logEvent("Partial exit executed", {
        tradeId: trade.id,
        lotsExited: lotsToExit,
        exitPremium: exitFill,
        pnl: pnlPartial
    })

FUNCTION executeSLExit(trade, exitPremium):
    """
    Exits all remaining lots at SL
    """
    
    lotsToExit = trade.lotsRemaining OR trade.lots
    
    IF isPaperTrade():
        exitFill = exitPremium * (1 - 0.01)  // 1% slippage at SL
    ELSE:
        exitFill = placeMarketOrder(trade.symbol, "SELL", lotsToExit)
    
    trade.exitPremium = exitFill
    trade.exitTime = now()
    trade.exitType = "SL_HIT"
    trade.status = "CLOSED"
    trade.outcome = "LOSS"
    
    pnl = (exitFill - trade.entryPremium) * lotsToExit * 15
    trade.pnlINR = trade.pnlRealized + pnl
    trade.rrAchieved = pnl / (trade.totalRisk)
    
    saveTrade(trade)
    
    // Trigger post-trade analysis
    runPostTradeAnalysis(trade)

FUNCTION executeTargetExit(trade, exitPremium):
    """
    Exits all remaining lots at target (2:1 RR)
    """
    
    lotsToExit = trade.lotsRemaining OR trade.lots
    
    IF isPaperTrade():
        exitFill = exitPremium * (1 - 0.005)  // 0.5% slippage
    ELSE:
        exitFill = placeMarketOrder(trade.symbol, "SELL", lotsToExit)
    
    trade.exitPremium = exitFill
    trade.exitTime = now()
    trade.exitType = "TARGET_HIT"
    trade.status = "CLOSED"
    trade.outcome = "WIN"
    
    pnl = (exitFill - trade.entryPremium) * lotsToExit * 15
    trade.pnlINR = trade.pnlRealized + pnl
    trade.rrAchieved = pnl / (trade.totalRisk)
    
    saveTrade(trade)
    runPostTradeAnalysis(trade)

FUNCTION executeEODExit(trade):
    """
    Hard exit at 3:15 PM regardless of P&L
    """
    
    lotsToExit = trade.lotsRemaining OR trade.lots
    currentPremium = getOptionLTP(trade.symbol)
    
    IF isPaperTrade():
        exitFill = currentPremium * (1 - 0.01)  // Higher slippage at EOD
    ELSE:
        // Market order for guaranteed exit
        exitFill = placeMarketOrder(trade.symbol, "SELL", lotsToExit, type="MARKET")
    
    trade.exitPremium = exitFill
    trade.exitTime = now()
    trade.exitType = "EOD_EXIT"
    trade.status = "CLOSED"
    
    pnl = (exitFill - trade.entryPremium) * lotsToExit * 15
    trade.pnlINR = trade.pnlRealized + pnl
    
    // Determine outcome
    IF trade.pnlINR > 0:
        trade.outcome = "WIN"
    ELSE IF trade.pnlINR < 0:
        trade.outcome = "LOSS"
    ELSE:
        trade.outcome = "BREAKEVEN"
    
    trade.rrAchieved = trade.pnlINR / trade.totalRisk
    
    saveTrade(trade)
    runPostTradeAnalysis(trade)
```

---

## 4. PAPER TRADE SIMULATION

### 4.1 Paper Trade Fill Logic

```
FUNCTION executePaperTrade(orderData):
    """
    Simulates trade execution for paper trading
    """
    
    // Get current market data
    ltp = getOptionLTP(orderData.symbol)
    bidAskSpread = getBidAskSpread(orderData.symbol)
    
    // Simulate entry slippage
    IF orderData.side == "BUY":
        // Pay the spread
        entryFill = ltp + (bidAskSpread / 2) + (ltp * 0.002)  // 0.2% slippage
    ELSE:
        entryFill = ltp - (bidAskSpread / 2) - (ltp * 0.002)
    
    // Create paper trade record
    trade = {
        id: generateUUID(),
        mode: "PAPER",
        direction: orderData.direction,
        symbol: orderData.symbol,
        strike: orderData.strike,
        lots: orderData.lots,
        entryPremium: entryFill,
        slPremium: orderData.slPremium,
        targetPremium: orderData.targetPremium,
        entryTime: now(),
        status: "ACTIVE",
        // ... context fields
    }
    
    saveTrade(trade)
    
    RETURN trade
```

---

## 5. CLAUDE API INTEGRATION

### 5.1 Confluence Scoring Prompt

```
FUNCTION callClaudeAPI(setupData):
    """
    Calls Claude API for confluence scoring
    """
    
    prompt = buildPrompt(setupData)
    
    response = makeAPICall({
        url: "https://api.anthropic.com/v1/messages",
        method: "POST",
        headers: {
            "x-api-key": env("CLAUDE_API_KEY"),
            "anthropic-version": "2023-06-01",
            "content-type": "application/json"
        },
        body: {
            model: "claude-sonnet-4",
            max_tokens: 2000,
            messages: [{
                role: "user",
                content: prompt
            }]
        }
    })
    
    // Parse response
    parsed = parseClaudeResponse(response.content[0].text)
    
    // Log Claude call
    logClaudeCall(prompt, response, parsed)
    
    RETURN parsed

FUNCTION buildPrompt(setupData):
    """
    Builds structured prompt for Claude
    """
    
    historicalStats = setupData.historicalPatternStats
    
    prompt = """
You are an expert Bank Nifty options trader analyzing a potential trade setup.

**Current Market Context:**
- Direction Proposed: {direction}
- 15m Candle Pattern: {pattern}
- Price vs EMA 20: {priceVsEMA20} points
- Higher Timeframe Bias: {htfBias}
- Session Time: {sessionSlot}
- Is Exception Trade: {isException}

**EMA Configuration:**
- EMA 20: {ema20}
- EMA 100: {ema100}
- EMA 200: {ema200}

**Recent 15m Candles (last 10):**
{candleTable}

**Historical Performance:**
- Pattern: {pattern} has {winRate}% win rate in last 20 trades
- Session: {sessionSlot} has {sessionWinRate}% win rate
- Average RR: {avgRR}

**Your Task:**
1. Analyze this setup using the confluence scoring criteria
2. Score this setup from 1.0 to 10.0
3. Provide clear reasoning for your score
4. If this is an exception trade, validate if resistance/support levels justify it

**Scoring Criteria:**
- HTF Alignment (3 points): All timeframes agree on direction
- EMA Position (2 points): Price at ideal position relative to EMAs
- 15m Pattern (2 points): Strong, clean pattern formation
- Market Structure (2 points): Higher highs/lows in uptrend, lower highs/lows in downtrend
- Historical Edge (1 point): This pattern has worked well recently

**Return your analysis as JSON:**
```json
{
  "score": 7.5,
  "reasoning": "Strong bullish alignment across all timeframes. Price rejecting EMA 20 with bullish engulfing pattern. Previous swing low holding as support. Pattern has 75% win rate historically in this session. Only minor concern is proximity to round number resistance at 51500.",
  "exception_valid": false,
  "confidence": "high"
}
```

**Important:**
- Be strict. Only score 7+ for truly high-probability setups
- Exception trades need VERY strong justification (multiple confirmed S/R levels)
- Consider recent pattern performance heavily
- Factor in session time (later sessions often choppier)
    """.format(setupData)
    
    RETURN prompt

FUNCTION parseClaudeResponse(responseText):
    """
    Extracts JSON from Claude response
    """
    
    // Try to find JSON block
    jsonMatch = REGEX_FIND(responseText, /```json\s*([\s\S]*?)\s*```/)
    
    IF jsonMatch:
        jsonText = jsonMatch[1]
    ELSE:
        // Try to parse entire response as JSON
        jsonText = responseText
    
    TRY:
        parsed = JSON_PARSE(jsonText)
        
        // Validate required fields
        IF NOT parsed.score OR NOT parsed.reasoning:
            THROW "Missing required fields"
        
        // Validate score range
        IF parsed.score < 1 OR parsed.score > 10:
            THROW "Score out of range"
        
        RETURN parsed
    
    CATCH error:
        logError("Claude response parsing failed", {
            response: responseText,
            error: error
        })
        
        // Fallback: return low score to skip trade
        RETURN {
            score: 0,
            reasoning: "Claude API response parsing failed",
            exception_valid: FALSE
        }
```

---

## 6. ERROR HANDLING & EDGE CASES

### 6.1 Market Data Failures

```
FUNCTION fetchCandles(symbol, timeframe, limit):
    """
    Fetches candles with retry and validation
    """
    
    maxRetries = 3
    retryDelay = 2  // seconds
    
    FOR attempt = 1 TO maxRetries:
        TRY:
            candles = fyersAPI.getHistory({
                symbol: symbol,
                resolution: timeframe,
                date_format: "1",
                range_from: calculateStartDate(timeframe, limit),
                range_to: now()
            })
            
            // Validate candles
            IF validateCandles(candles):
                RETURN candles
            ELSE:
                THROW "Invalid candle data"
        
        CATCH error:
            logError("Candle fetch failed", {
                attempt: attempt,
                error: error
            })
            
            IF attempt < maxRetries:
                SLEEP(retryDelay * attempt)  // Exponential backoff
            ELSE:
                // All retries failed - return cached data if available
                cached = getCachedCandles(symbol, timeframe)
                IF cached:
                    logWarning("Using cached candles due to API failure")
                    RETURN cached
                ELSE:
                    THROW "Cannot fetch or cache candles"

FUNCTION validateCandles(candles):
    """
    Validates candle data integrity
    """
    
    IF candles.length == 0:
        RETURN FALSE
    
    FOR candle IN candles:
        // Check OHLC relationships
        IF candle.high < candle.low:
            RETURN FALSE
        
        IF candle.high < candle.open OR candle.high < candle.close:
            RETURN FALSE
        
        IF candle.low > candle.open OR candle.low > candle.close:
            RETURN FALSE
        
        // Check for zero/null values
        IF candle.open == 0 OR candle.high == 0 OR candle.low == 0 OR candle.close == 0:
            RETURN FALSE
    
    // Check chronological order
    FOR i = 1 TO candles.length - 1:
        IF candles[i].timestamp >= candles[i-1].timestamp:
            RETURN FALSE  // Should be descending (latest first)
    
    RETURN TRUE
```

### 6.2 Claude API Failures

```
FUNCTION callClaudeAPI(setupData):
    """
    Calls Claude with retry and fallback
    """
    
    maxRetries = 2
    timeout = 30  // seconds
    
    FOR attempt = 1 TO maxRetries:
        TRY:
            response = makeAPICall(claudeConfig, timeout)
            parsed = parseClaudeResponse(response)
            
            IF parsed.score > 0:
                RETURN parsed
        
        CATCH error:
            logError("Claude API call failed", {
                attempt: attempt,
                error: error,
                setupData: setupData
            })
            
            IF attempt < maxRetries:
                SLEEP(5)
    
    // All retries failed - use fallback scoring
    fallbackScore = calculateFallbackScore(setupData)
    
    logWarning("Using fallback scoring due to Claude API failure")
    
    RETURN {
        score: fallbackScore,
        reasoning: "Fallback: Claude API unavailable. Score based on pattern historical win rate.",
        exception_valid: FALSE
    }

FUNCTION calculateFallbackScore(setupData):
    """
    Simple rule-based scoring when Claude is unavailable
    """
    
    score = 0
    
    // HTF alignment (+3)
    IF setupData.htfBias == setupData.direction.toLowerCase():
        score += 3
    
    // Pattern strength (+2)
    patternWinRate = setupData.historicalPatternStats.winRate
    IF patternWinRate > 70:
        score += 2
    ELSE IF patternWinRate > 60:
        score += 1.5
    ELSE IF patternWinRate > 50:
        score += 1
    
    // EMA position (+2)
    distanceFromEMA = ABS(setupData.priceVsEMA20)
    IF distanceFromEMA < 20:
        score += 2
    ELSE IF distanceFromEMA < 50:
        score += 1
    
    // Session performance (+1)
    sessionWinRate = setupData.sessionStats.winRate
    IF sessionWinRate > 60:
        score += 1
    ELSE IF sessionWinRate > 50:
        score += 0.5
    
    RETURN MIN(score, 10)
```

### 6.3 Order Placement Failures

```
FUNCTION executeTrade(tradeData):
    """
    Executes trade with validation and retry
    """
    
    // Pre-flight checks
    IF hasTradeToday():
        THROW "Trade already taken today - lock active"
    
    IF tradeData.lots < 1:
        THROW "Lot size calculation resulted in 0 lots"
    
    // Execute based on mode
    IF isPaperTrade():
        trade = executePaperTrade(tradeData)
    ELSE:
        trade = executeLiveTrade(tradeData)
    
    // Set trade lock
    setTradeLock()
    
    // Log trade
    logTrade(trade)
    
    RETURN trade

FUNCTION executeLiveTrade(tradeData):
    """
    Executes actual live trade with Fyers
    """
    
    maxRetries = 3
    
    FOR attempt = 1 TO maxRetries:
        TRY:
            // Place entry order
            entryOrder = fyersAPI.placeOrder({
                symbol: tradeData.symbol,
                qty: tradeData.lots * 15,
                type: 2,  // LIMIT order
                side: 1,  // BUY
                productType: "INTRADAY",
                limitPrice: tradeData.entryPremium * 1.005,  // 0.5% above LTP
                validity: "DAY"
            })
            
            // Wait for fill (up to 30 seconds)
            filled = waitForOrderFill(entryOrder.id, timeout=30)
            
            IF NOT filled:
                cancelOrder(entryOrder.id)
                THROW "Entry order not filled"
            
            // Get actual fill price
            fillPrice = getOrderFillPrice(entryOrder.id)
            
            // Create trade record
            trade = createTradeRecord(tradeData, fillPrice)
            
            RETURN trade
        
        CATCH error:
            logError("Live order placement failed", {
                attempt: attempt,
                error: error
            })
            
            IF attempt < maxRetries:
                SLEEP(2)
            ELSE:
                // Final failure - mark as failed trade
                trade = createFailedTradeRecord(tradeData, error)
                notifyTrader("Order placement failed after 3 attempts")
                RETURN trade
```

---

## 7. REDIS LOCK MECHANISM

### 7.1 Trade Lock Implementation

```
FUNCTION setTradeLock():
    """
    Sets Redis lock to prevent multiple trades per day
    """
    
    lockKey = "trade:lock:" + today()
    lockValue = {
        locked: TRUE,
        timestamp: now(),
        ttl: 86400  // 24 hours
    }
    
    redis.set(lockKey, JSON_STRINGIFY(lockValue), EX=86400)
    
    logEvent("Trade lock set", {lockKey: lockKey})

FUNCTION hasTradeToday():
    """
    Checks if trade has been taken today
    """
    
    lockKey = "trade:lock:" + today()
    lock = redis.get(lockKey)
    
    RETURN lock != NULL

FUNCTION resetTradeLock():
    """
    Manually reset lock (admin function only)
    """
    
    lockKey = "trade:lock:" + today()
    redis.del(lockKey)
    
    logEvent("Trade lock manually reset", {
        lockKey: lockKey,
        admin: currentUser()
    })
```

---

## 8. SCHEDULER TASKS

### 8.1 Daily Task Schedule

```
// 9:00 AM - Pre-market setup
SCHEDULE("0 9 * * 1-5"):  // Mon-Fri
    refreshFyersToken()
    fetchHTFCandles()
    runClaudeHTFAnalysis()
    checkMarketCalendar()
    resetTradeLock()  // Clear previous day lock

// Every 15 minutes during trading window (9:15 AM - 3:15 PM)
SCHEDULE("*/15 9-15 * * 1-5"):
    fetch15mCandles()
    computeEMAs()
    cacheCandles()
    
    // Only check signals during active window
    IF currentTime >= "11:15" AND currentTime < "14:00":
        checkForEntrySignal()

// 3:15 PM - Hard EOD exit
SCHEDULE("15 15 * * 1-5"):
    executeAllEODExits()

// 3:30 PM - Post-market tasks
SCHEDULE("30 15 * * 1-5"):
    generateDailyReport()
    checkLearningCycleTrigger()
    cleanupWebSockets()
```

---

## 9. IMPLEMENTATION CHECKLIST

### Phase 1: Core Signal Detection
- [ ] Candle fetcher with retry and validation
- [ ] EMA calculator (20, 100, 200)
- [ ] Pattern detector (engulfing, pin bar, inside bar, EMA rejection)
- [ ] HTF trend analyzer with alignment logic
- [ ] Time window validator
- [ ] Market calendar filter

### Phase 2: Entry Logic
- [ ] Primary entry rule engine (long/short)
- [ ] Exception trade validator
- [ ] Swing high/low detector
- [ ] EMA proximity checker
- [ ] Candle close confirmation

### Phase 3: Claude Integration
- [ ] Claude API wrapper with retry
- [ ] Prompt builder for confluence scoring
- [ ] Response parser and validator
- [ ] Exception validation prompts
- [ ] Fallback scoring when API fails

### Phase 4: Risk & Position Sizing
- [ ] SL level calculator
- [ ] Premium-based SL converter
- [ ] Position sizing algorithm
- [ ] ATM strike selector with tie-breaking
- [ ] Expiry-based lot reduction

### Phase 5: Trade Execution
- [ ] Paper trade simulator with slippage
- [ ] Live order placer (Fyers integration)
- [ ] Trade record creator
- [ ] Redis lock setter
- [ ] Order validation and retry

### Phase 6: Exit Management
- [ ] WebSocket real-time monitor
- [ ] SL hit detector
- [ ] Target hit detector
- [ ] Partial exit executor (50% at 1:1 RR)
- [ ] Trailing SL calculator
- [ ] EOD hard exit (3:15 PM)

### Phase 7: Error Handling
- [ ] API failure retry logic
- [ ] Data validation layer
- [ ] Fallback mechanisms
- [ ] Comprehensive logging
- [ ] Notification system

---

**Document Version:** 1.0  
**Last Updated:** 2026-06-23  
**Implementation Ready:** ✅

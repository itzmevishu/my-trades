# Bank Nifty AI Trading Tool
## Product Requirements Document

> **Version:** 1.0 — Initial Release
> **Date:** June 2026
> **Trading Mode:** Paper Trade (Live after validation)
> **Instrument:** Bank Nifty Monthly Options
> **Broker:** Fyers
> **Hosting:** Laravel Cloud

---

## Table of Contents

1. [Product Overview](#1-product-overview)
2. [Trading Strategy Specification](#2-trading-strategy-specification)
3. [Risk Management Engine](#3-risk-management-engine)
4. [Claude AI Integration](#4-claude-ai-integration)
5. [Self-Learning Engine](#5-self-learning-engine)
6. [System Architecture & Flow](#6-system-architecture--flow)
7. [System Modules](#7-system-modules)
8. [Database Schema](#8-database-schema)
9. [Dashboard Specification](#9-dashboard-specification)
10. [Reporting System](#10-reporting-system)
11. [Development Phases](#11-development-phases)
12. [Acceptance Criteria](#12-acceptance-criteria)

---

## 1. Product Overview

### 1.1 Purpose

This document defines the complete product requirements for an AI-powered intraday options trading tool built on Laravel 11. The tool automates Bank Nifty monthly options trading using a pure price action strategy combined with Claude AI analysis, a self-learning feedback engine, and comprehensive trade reporting via a Filament dashboard.

### 1.2 Product Vision

> To build a disciplined, data-driven trading system that combines proven price action principles with AI intelligence — one that learns from every trade, enforces strict risk rules, and continuously improves its decision-making over time.

### 1.3 Core Principles

- **One trade per day** — maximum discipline, no overtrading
- **1% capital risk per trade** — capital preservation above all
- **Price action is truth** — EMAs and patterns are the foundation
- **Claude AI augments — never overrides** — human-defined rules
- **Paper trade first** — 30+ trades before any live capital
- **Every decision is logged** — full auditability always

### 1.4 Key Stakeholders

| Role | Responsibility | Notes |
|---|---|---|
| Trader / Owner | Strategy definition, trade review | Final authority on all decisions |
| Laravel Backend | Rule engine, order execution | Deterministic, fast, reliable |
| Claude AI | Analysis, scoring, learning, reports | Augments decisions, not replaces |
| Fyers API | Market data, order placement | Primary broker integration |

---

## 2. Trading Strategy Specification

### 2.1 Instrument Details

| Parameter | Value |
|---|---|
| Instrument | Bank Nifty Monthly Options (CE / PE) |
| Expiry | Monthly — last Thursday of the month |
| Strike Selection | ATM — closest strike to current index price |
| Direction Long | Buy CE (Call) when long signal triggered |
| Direction Short | Buy PE (Put) when short signal triggered |
| Lot Size | 15 units per lot |
| Trade Frequency | Maximum 1 trade per day |
| Entry Window | 11:15 AM — 2:00 PM IST only |
| Hard Exit | 3:15 PM IST — all positions closed regardless |

### 2.2 EMA Configuration

The strategy uses three Exponential Moving Averages computed from 15-minute OHLC candle data:

| EMA | Role | Usage |
|---|---|---|
| EMA 20 | Primary entry filter | Candle must be at or near EMA 20 to qualify |
| EMA 100 | Mid-term trend confirmation | Confirms intermediate trend direction |
| EMA 200 | Macro trend filter | Price above EMA 200 = longs preferred; below = shorts preferred |

### 2.3 Entry Rules

#### Primary Long Entry
- Candle closes at or above EMA 20 on 15-minute timeframe
- HTF alignment confirms bullish bias (Monthly → Daily → 15m)
- Claude confluence score ≥ 6/10
- Entry window: 11:15 AM – 2:00 PM
- No trade already taken today

#### Primary Short Entry
- Candle closes at or below EMA 20 on 15-minute timeframe
- HTF alignment confirms bearish bias (Monthly → Daily → 15m)
- Claude confluence score ≥ 6/10
- Entry window: 11:15 AM – 2:00 PM
- No trade already taken today

#### Rare Exception — Counter-EMA Trade

> A short trade may be taken when price is above EMA 20 (or long below EMA 20) **ONLY** when Claude AI detects and validates multiple significant resistance/support levels. Claude is the sole judge of whether the exception qualifies. This overrides the HTF filter but requires a higher Claude score **(≥ 7.5/10)**.

### 2.4 Higher Timeframe (HTF) Analysis

Before any entry is considered, the system performs a top-down analysis:

- **Monthly chart** — establish dominant trend direction
- **Weekly chart** — confirm trend continuation or identify key levels
- **Daily chart** — identify structure, swing highs/lows, key support/resistance
- **15-minute chart** — find precise entry within the HTF context

> **HTF Rule:** ALL four timeframes must align for a standard entry. HTF alignment is mandatory and cannot be skipped except in the Rare Exception scenario where Claude explicitly overrides with strong justification.

### 2.5 Stop Loss & Target Rules

| Parameter | Rule |
|---|---|
| SL Method | Premium-based — SL placed at a premium level corresponding to index swing high/low |
| SL Calculation | Index SL points × ATM delta (≈ 0.5) = premium drop; SL = entry premium − premium drop |
| Target | Minimum 1:2 Risk-Reward Ratio |
| Partial Exit | Exit 50% position at 1:1 RR; trail SL on remaining 50% |
| Hard EOD Exit | All positions closed at 3:15 PM regardless of outcome |
| Max Loss/Day | 1% of capital (₹3,000 at ₹3L capital) — configurable |

---

## 3. Risk Management Engine

### 3.1 Position Sizing Formula

```
Max Lots = Floor( Max Risk (₹) ÷ (SL Points × Delta × Lot Size) )

Where:
  Max Risk  = Capital × 1%  (configurable)
  SL Points = Index entry price − Index SL level
  Delta     ≈ 0.5 for ATM options
  Lot Size  = 15 units
  Always floor() — never round up to stay within risk limit
```

### 3.2 Example Calculation

| Input | Value |
|---|---|
| Capital | ₹3,00,000 |
| 1% Risk | ₹3,000 |
| Entry Premium | ₹200 |
| Index SL Points | 150 points |
| ATM Delta | 0.5 |
| Premium Drop at SL | 150 × 0.5 = ₹75 |
| SL Premium Level | ₹200 − ₹75 = ₹125 |
| Loss Per Lot | ₹75 × 15 = ₹1,125 |
| Max Lots | Floor(₹3,000 ÷ ₹1,125) = **2 lots** |
| **Actual Max Loss** | **2 × ₹1,125 = ₹2,250 (within ₹3,000 limit ✓)** |

### 3.3 Capital Configuration

- Capital amount is configurable in the Settings page
- 1% risk auto-recalculates whenever capital is updated
- All lot size calculations use the updated capital immediately
- Historical trades retain their original capital/risk snapshot

### 3.4 Expiry-Based Risk Adjustment

| Days to Expiry | Action | Reason |
|---|---|---|
| > 15 days | Normal trading | Full liquidity, normal theta |
| 8 – 15 days | Flag theta risk in score | Decay accelerating |
| < 7 days | Reduce suggested lots | High theta decay risk |
| **Expiry Thursday** | **SKIP — No trading** | Extreme volatility |

---

## 4. Claude AI Integration

### 4.1 AI Role Overview

Claude AI acts as an intelligent analysis and learning layer built on top of the deterministic Laravel rule engine. Claude augments — never overrides — the core price action rules except in the documented rare exception scenario.

### 4.2 Claude Functions

| Function | Description |
|---|---|
| HTF Trend Read | Interprets Monthly/Weekly/Daily candle structure and establishes directional bias |
| Confluence Scoring | Scores each setup 1–10 before entry. Minimum 6/10 required to trade |
| Exception Judgment | Evaluates resistance/support strength for rare counter-EMA trades. Requires ≥ 7.5/10 |
| Trade Reasoning | Writes plain-English rationale for every trade taken or skipped |
| Post-Trade Analysis | Explains what went right or wrong after each trade exits |
| Learning Engine | Analyses last N trades to identify patterns and update strategy config |
| Report Generation | Generates Daily, Weekly, and Monthly reports in plain English |

### 4.3 Confluence Scoring Criteria

| Scoring Factor | Max Points | What Claude Evaluates |
|---|---|---|
| HTF Alignment | 3.0 | Monthly → Daily → 15m all agree on direction |
| EMA Position | 2.0 | Price relative to EMA 20/100/200 strength |
| 15m Candle Pattern | 2.0 | Pattern quality (engulfing, pin bar, inside bar breakout) |
| Market Structure | 2.0 | HH/HL (bullish) or LH/LL (bearish) intact |
| Historical Pattern Performance | 1.0 | Based on learning engine — how this pattern performed historically |
| **TOTAL** | **10.0** | **Minimum 6.0 to trade; 7.5+ for exception trades** |

### 4.4 API Usage Estimate

- Calls per day: 5–10 (HTF analysis + setup scoring + post-trade + report)
- Tokens per call: ~2,000 average
- Estimated cost: < ₹5 per day at current Claude Sonnet pricing

---

## 5. Self-Learning Engine

### 5.1 Overview

The Self-Learning Engine is the most powerful feature of this system. After every 10 completed trades, Claude analyses the full historical trade context and updates the strategy configuration automatically. This creates a continuously improving trading system that gets smarter with every trade.

### 5.2 Data Captured Per Trade

Every trade stores complete context for learning purposes:

| Field | Description |
|---|---|
| `candle_pattern` | 15m pattern detected (engulfing, pin bar, inside bar breakout, EMA rejection, etc.) |
| `ema_configuration` | Price position relative to EMA 20/100/200 at entry |
| `htf_bias` | Bullish / Bearish / Neutral at trade time |
| `session_slot` | 11:15–12:00 / 12:00–13:00 / 13:00–14:00 |
| `claude_score` | Score given at time of signal (1–10) |
| `is_exception_trade` | Boolean — was this a rare counter-EMA exception? |
| `outcome` | Win / Loss / Breakeven |
| `rr_achieved` | Actual RR at exit (e.g., 1.8, -0.5) |
| `market_condition` | Normal / High-Impact Day / Near Expiry |
| `pnl_points / pnl_inr` | Points and ₹ profit or loss |
| `claude_reasoning` | Full text of Claude's pre-trade rationale |
| `post_trade_analysis` | Claude's post-exit analysis text |

### 5.3 Learning Cycle

> **Learning Trigger:** Every 10 completed trades, Claude receives the full trade history and produces a Strategy Config Update. This update is saved to the database and used for all subsequent trade decisions.

#### What the Learning Engine Identifies

- Which 15m candle patterns have the highest win rate
- Which entry time slots (11:15–12:00, 12:00–13:00, 13:00–14:00) perform best
- Which EMA configurations precede winning trades
- Whether exception trades are profitable or should be avoided
- Which market conditions correlate with losses
- Optimal minimum Claude score threshold (raise or lower 6.0 baseline)

### 5.4 Strategy Config Output

The learning engine produces a JSON configuration stored in the database:

```json
{
  "pattern_weights": {
    "engulfing": 0.85,
    "pin_bar": 0.72,
    "inside_bar_breakout": 0.91,
    "ema_rejection": 0.68
  },
  "best_entry_window": "11:15–12:30",
  "avoid_setups": ["ema_rejection on near-expiry days"],
  "min_score_threshold": 6.5,
  "learning_note": "Inside bar breakouts above EMA 20 with bullish HTF show 78% win rate in last 20 trades. Prioritise this pattern."
}
```

### 5.5 Learning Log

Every learning cycle is recorded in the Learning Log page on the dashboard showing:

- Date of learning cycle
- Number of trades analysed
- What changed from the previous config
- Claude's reasoning for each change
- Win rate before and after config update

---

## 6. System Architecture & Flow

### 6.1 Daily Operating Flow

```
┌─────────────────────────────────────────────────────────┐
│  PRE-MARKET  9:00 AM                                    │
│  ├── Fyers OAuth token auto-refresh                     │
│  ├── Fetch HTF candles: Monthly, Weekly, Daily          │
│  ├── Claude analyses HTF → sets daily bias              │
│  ├── Market condition check (expiry? RBI? gap?)         │
│  └── Dashboard: System Ready | Bias | Caution Flags     │
└─────────────────────────────────────────────────────────┘
           ↓
┌─────────────────────────────────────────────────────────┐
│  OBSERVATION  9:15 AM – 11:15 AM                        │
│  ├── Live 15m candles fetched and displayed             │
│  ├── EMA 20/100/200 computed and shown                  │
│  └── No trades permitted — observe only                 │
└─────────────────────────────────────────────────────────┘
           ↓
┌─────────────────────────────────────────────────────────┐
│  ACTIVE TRADING WINDOW  11:15 AM – 2:00 PM              │
│  ├── Every 15 min: fetch candles, recompute EMAs        │
│  ├── Rule engine: candle position vs EMA 20             │
│  ├── HTF alignment check                                │
│  ├── Claude scores setup (standard or exception)        │
│  ├── Score ≥ threshold + rules pass → signal            │
│  ├── Risk engine: SL, premium SL, lot count             │
│  ├── One-trade-per-day lock checked in Redis            │
│  ├── Paper trade placed and logged                      │
│  └── Exit monitor activated                             │
└─────────────────────────────────────────────────────────┘
           ↓
┌─────────────────────────────────────────────────────────┐
│  MONITOR ONLY  2:00 PM – 3:15 PM                        │
│  └── No new entries; manage open trade only             │
└─────────────────────────────────────────────────────────┘
           ↓
┌─────────────────────────────────────────────────────────┐
│  HARD EXIT  3:15 PM                                     │
│  └── All positions closed at market price               │
└─────────────────────────────────────────────────────────┘
           ↓
┌─────────────────────────────────────────────────────────┐
│  POST-MARKET  3:30 PM                                   │
│  ├── Claude generates daily report                      │
│  ├── Report saved to DB and dashboard                   │
│  ├── Learning cycle check (10 trades? → analyse)        │
│  └── Daily trade lock reset for next day                │
└─────────────────────────────────────────────────────────┘
```

### 6.2 Market Condition Filters

| Condition | Action | Notes |
|---|---|---|
| Bank Nifty Expiry Thursday | **SKIP** | Extreme IV and volatility |
| RBI Policy Announcement | **SKIP** | Unpredictable directional moves |
| Union Budget Day | **SKIP** | Maximum uncertainty |
| Gap Open > 0.5% | **CAUTION** | Wait for structure to establish |
| Near Expiry < 7 days | **CAUTION** | Theta decay risk, lot size reduced |

---

## 7. System Modules

| Module | Responsibility |
|---|---|
| `FyersAuthService` | OAuth2 login, daily token auto-refresh, session management |
| `CandleFetcher` | Pulls HTF + 15m OHLC candles from Fyers API on schedule |
| `EMACalculator` | Computes EMA 20, 100, 200 from candle arrays |
| `HTFAnalyser` | Monthly → Daily → 15m trend bias determination |
| `PatternDetector` | Identifies 15m candle patterns: engulfing, pin bar, inside bar, EMA rejection |
| `ClaudeAnalysisService` | Sends setup data to Claude API, receives score and reasoning |
| `LearningEngine` | Feeds trade history to Claude every 10 trades, stores config updates |
| `StrategyConfig` | DB-driven config record updated by learning engine |
| `RiskEngine` | Calculates SL level, premium SL, delta adjustment, and max lot count |
| `StrikeSelector` | Identifies ATM strike from current index price, selects CE or PE |
| `OrderService` | Paper trade execution now; live Fyers order placement later |
| `TradeLogger` | Stores full trade context to MySQL after every trade event |
| `ExitMonitor` | Watches active trade for SL hit, target hit, partial exit, EOD exit |
| `ReportGenerator` | Triggers Claude to produce Daily, Weekly, Monthly reports |
| `MarketCalendar` | Maintains high-impact event dates, expiry dates, gap filters |

---

## 8. Database Schema

### 8.1 `trades`
Primary table storing every paper and live trade with full context.

```sql
id, date, direction (long/short), instrument, expiry, strike
entry_time, exit_time, entry_premium, exit_premium
sl_premium, target_premium, lots, capital_at_trade
candle_pattern, ema_configuration, htf_bias, session_slot
is_exception_trade, claude_score, claude_reasoning
outcome (win/loss/breakeven), rr_achieved, pnl_points, pnl_inr
market_condition, post_trade_analysis, created_at
```

### 8.2 `strategy_configs`
Stores current and historical strategy configuration from learning engine.

```sql
id, version, pattern_weights (JSON), best_entry_window
min_score_threshold, avoid_setups (JSON), learning_note
trades_analysed, win_rate_at_update, is_active, created_at
```

### 8.3 `learning_logs`
Audit trail of every learning cycle.

```sql
id, trigger_trade_count, trades_analysed
previous_config_id, new_config_id
changes_summary (JSON), claude_full_response, created_at
```

### 8.4 `daily_reports`
Stores generated reports for all periods.

```sql
id, report_date, report_type (daily/weekly/monthly)
market_context, setup_summary, trade_outcome
claude_analysis (text), pnl_summary, created_at
```

### 8.5 `candle_cache`
Caches fetched candles to reduce API calls.

```sql
id, symbol, timeframe, open, high, low, close, volume, timestamp
```

### 8.6 `market_calendar`
High-impact event dates for skip/caution filters.

```sql
id, event_date, event_type, description, action (skip/caution)
```

### 8.7 Settings (Key/Value Store)

| Key | Default | Description |
|---|---|---|
| `capital_amount` | 300000 | Drives 1% risk calculation |
| `min_claude_score` | 6.0 | Updated by learning engine |
| `sl_delta_assumption` | 0.5 | ATM delta for SL calculation |
| `partial_exit_rr` | 1.0 | Triggers 50% position exit |
| `paper_trade_mode` | true | Switch to false for live trading |

---

## 9. Dashboard Specification

### 9.1 Technology

Dashboard built with **Filament v3** — Laravel's premier admin panel framework. Provides rich UI components, real-time stats, and table management out of the box.

### 9.2 Dashboard Pages

| Page | Content |
|---|---|
| **Home / Live** | System status, today's HTF bias, caution flags, active trade card, live 15m candle table with EMAs, one-trade lock status |
| **Trades** | All trades list with filters (date, outcome, pattern, direction). Each row expandable with Claude reasoning + post-trade analysis |
| **Analysis** | Win rate, average RR, total PnL, best/worst trade, equity curve, pattern breakdown, session heatmap |
| **Learning Log** | Timeline of all learning cycles — what changed, why, win rate before/after, full Claude response |
| **Reports** | Searchable list of Daily/Weekly/Monthly reports, fully rendered with Claude analysis |
| **Settings** | Capital, min score, SL delta, partial exit RR, paper trade toggle, market calendar management |

### 9.3 Home Page — Key Widgets

- **System Status** — Ready / Waiting / Trading / Post-Market
- **Daily Bias** — Bullish / Bearish / Neutral with HTF summary
- **Caution Flags** — expiry warning, gap warning, high-impact day
- **Active Trade** — live entry, current premium, unrealised PnL
- **Trade Lock** — "Trade taken today" or "Available"
- **15m Candle Table** — last 20 candles with EMA 20/100/200 columns

### 9.4 Analysis Page — Key Metrics

- Overall win rate (%)
- Average Risk-Reward achieved
- Total trades, winning trades, losing trades
- Total PnL in points and ₹
- Best trade and worst trade
- Pattern breakdown table — win rate per candle pattern
- Session heatmap — 11:15–12:00 vs 12:00–13:00 vs 13:00–14:00
- Equity curve — running PnL plotted over time

---

## 10. Reporting System

### 10.1 Daily Report *(3:30 PM auto-generated)*

- Market context: HTF trend summary for the day
- Pre-market bias and any caution flags
- Setup summary: what signal triggered (or why no trade today)
- Claude's confluence score and full reasoning
- Trade outcome: Entry / SL / Target / Actual exit / RR achieved
- PnL in points and ₹
- Post-trade Claude analysis: what went right or wrong

### 10.2 Weekly Report *(Friday 3:30 PM)*

- Total trades this week, win rate, average RR
- Best and worst trade of the week
- Total weekly PnL in points and ₹
- Pattern and session performance this week
- Claude's weekly observation and pattern insights
- Strategy adherence: did every trade follow the rules?

### 10.3 Monthly Report

- Full equity curve data for the month
- Max drawdown and max consecutive losses
- Win rate, average RR, total PnL
- Best performing pattern and session slot
- Learning engine changes made this month
- Claude's monthly strategic insights and recommendations
- Comparison of paper trade performance vs strategy expectations

> **Live Trading Gate:** After accumulating 30+ paper trades with a win rate > 50% and average RR > 1.5, the Settings page will surface the live trading toggle. This transition is manual and requires explicit user confirmation.

---

## 11. Development Phases

| Phase | Name | Modules | Deliverable |
|---|---|---|---|
| 1 | Foundation | FyersAuth, CandleFetcher, EMACalculator | Live data flowing, EMAs computing |
| 2 | Intelligence | PatternDetector, HTFAnalyser, ClaudeAnalysis, StrategyConfig | Signals scored by Claude |
| 3 | Trade Engine | RiskEngine, StrikeSelector, OrderService, ExitMonitor | Paper trades executing end-to-end |
| 4 | Learning | TradeLogger, LearningEngine, LearningLog | System self-updating after 10 trades |
| 5 | Dashboard & Reports | Filament dashboard, all pages, ReportGenerator | Full product live in paper mode |

### 11.1 Tech Stack

| Layer | Technology |
|---|---|
| Backend Framework | Laravel 11, PHP 8.2 |
| Database | MySQL |
| Cache & Queues | Redis + Laravel Queues + Laravel Scheduler |
| Broker Integration | Fyers API v3 (REST + WebSocket) |
| AI Layer | Anthropic Claude API (Sonnet) |
| Admin Dashboard | Filament v3 |
| Hosting | Laravel Cloud (IST timezone configured) |

### 11.2 Future Enhancements *(Post Live-Trading Validation)*

- Live news sentiment analysis via NewsAPI + Claude interpretation
- Telegram notifications for trade entries and exits
- Backtesting engine using accumulated historical candle data
- Multi-instrument support (Nifty 50 options)
- Mobile-responsive dashboard view

---

## 12. Acceptance Criteria

### 12.1 Paper Trade Phase — Go-Live Gate

> The system must complete a minimum of 30 paper trades before live trading is enabled. The Settings page will surface the live trading toggle only after ALL conditions below are met.

| Criteria | Threshold | Tracked By |
|---|---|---|
| Minimum paper trades completed | 30 trades | Automatic counter |
| Win rate | > 50% | Dashboard metric |
| Average RR achieved | > 1.5 | Dashboard metric |
| Strategy rule adherence | 100% | Audit log |
| Learning engine cycles completed | ≥ 2 cycles | Learning log |

### 12.2 System Reliability Requirements

- Fyers token must auto-refresh without manual intervention daily
- Scheduler must trigger within ±2 minutes of scheduled time
- No duplicate trades on same day under any circumstance
- SL order must be placed simultaneously with entry order
- Hard EOD exit at 3:15 PM must execute even if monitoring fails
- All Claude API failures must be logged and trade skipped gracefully

---

*Bank Nifty AI Trading Tool — PRD v1.0 — June 2026*
*CONFIDENTIAL — FOR INTERNAL USE ONLY*

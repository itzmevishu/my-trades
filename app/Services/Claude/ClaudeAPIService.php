<?php

namespace App\Services\Claude;

use App\Services\BaseService;
use Illuminate\Support\Facades\Http;

/**
 * ClaudeAPIService - AI-powered trade analysis using Claude API
 * 
 * Core Features:
 * - Setup scoring (1-10 confluence score)
 * - Exception trade validation (when score < min threshold)
 * - Post-trade analysis (learning from outcomes)
 * - Pattern analysis and market context
 * - Daily/weekly/monthly report generation
 * 
 * API: Claude Sonnet 4.5 via Anthropic API
 * Model: claude-sonnet-4-20250514
 */
class ClaudeAPIService extends BaseService
{
    /**
     * Claude API endpoint
     */
    private string $apiEndpoint = 'https://api.anthropic.com/v1/messages';

    /**
     * Claude model to use
     */
    private string $model = 'claude-sonnet-4-6';

    /**
     * Score a trade setup for confluence
     * 
     * Analyzes setup quality based on:
     * - Candle pattern strength
     * - EMA confluence (how many EMAs aligned)
     * - Higher timeframe bias
     * - Session slot (morning/afternoon)
     * - Market condition
     * 
     * @param array $setupData Setup parameters
     * @return array ['score' => int, 'reasoning' => string, 'confidence' => string]
     */
    public function scoreSetup(array $setupData): array
    {
        $this->logInfo('Scoring trade setup with Claude AI', [
            'pattern' => $setupData['candle_pattern'] ?? 'unknown',
            'ema_confluence' => $setupData['ema_confluence'] ?? 0
        ]);

        $prompt = $this->buildScoringPrompt($setupData);

        try {
            $response = $this->callClaudeAPI($prompt, 'setup_scoring');

            // Parse response to extract score and reasoning
            $parsed = $this->parseScoreResponse($response);

            $this->logInfo('Setup scored successfully', [
                'score' => $parsed['score'],
                'confidence' => $parsed['confidence']
            ]);

            return $parsed;

        } catch (\Exception $e) {
            $this->logError('Failed to score setup', [
                'error' => $e->getMessage()
            ]);

            // Return fallback score
            return [
                'score' => 5,
                'reasoning' => 'Error scoring setup: ' . $e->getMessage(),
                'confidence' => 'low',
                'error' => true
            ];
        }
    }

    /**
     * Validate exception trade (when manual override requested)
     * 
     * User wants to take trade despite score < min threshold.
     * Claude validates if it's reasonable or reckless.
     * 
     * @param array $setupData Setup parameters
     * @param string $userReasoning User's explanation for override
     * @return array ['valid' => bool, 'reasoning' => string, 'risk_level' => string]
     */
    public function validateException(array $setupData, string $userReasoning): array
    {
        $this->logInfo('Validating exception trade', [
            'score' => $setupData['claude_score'] ?? 0,
            'user_reasoning' => substr($userReasoning, 0, 50) . '...'
        ]);

        $prompt = $this->buildExceptionPrompt($setupData, $userReasoning);

        try {
            $response = $this->callClaudeAPI($prompt, 'exception_validation');
            $parsed = $this->parseExceptionResponse($response);

            $this->logInfo('Exception validated', [
                'valid' => $parsed['valid'],
                'risk_level' => $parsed['risk_level']
            ]);

            return $parsed;

        } catch (\Exception $e) {
            $this->logError('Failed to validate exception', [
                'error' => $e->getMessage()
            ]);

            // Err on side of caution - reject exception
            return [
                'valid' => false,
                'reasoning' => 'Could not validate exception due to API error. Skipping trade for safety.',
                'risk_level' => 'high',
                'error' => true
            ];
        }
    }

    /**
     * Analyze completed trade for learning
     * 
     * Post-trade analysis to understand what worked/didn't work.
     * Used by learning engine every 10 trades.
     * 
     * @param array $tradeData Completed trade data
     * @return array ['analysis' => string, 'lessons' => array, 'pattern_performance' => string]
     */
    public function analyzeCompletedTrade(array $tradeData): array
    {
        $this->logInfo('Analyzing completed trade', [
            'trade_id' => $tradeData['id'] ?? 'unknown',
            'outcome' => $tradeData['outcome'] ?? 'unknown'
        ]);

        $prompt = $this->buildTradeAnalysisPrompt($tradeData);

        try {
            $response = $this->callClaudeAPI($prompt, 'trade_analysis');
            $parsed = $this->parseAnalysisResponse($response);

            return $parsed;

        } catch (\Exception $e) {
            $this->logError('Failed to analyze trade', [
                'error' => $e->getMessage()
            ]);

            return [
                'analysis' => 'Analysis failed: ' . $e->getMessage(),
                'lessons' => [],
                'pattern_performance' => 'unknown',
                'error' => true
            ];
        }
    }

    /**
     * Generate daily/weekly/monthly report
     * 
     * @param array $trades Trades in period
     * @param string $reportType 'daily', 'weekly', or 'monthly'
     * @return string Claude-generated report
     */
    public function generateReport(array $trades, string $reportType): string
    {
        $this->logInfo("Generating {$reportType} report", [
            'trade_count' => count($trades)
        ]);

        $prompt = $this->buildReportPrompt($trades, $reportType);

        try {
            $response = $this->callClaudeAPI($prompt, 'report_generation');
            return $response;

        } catch (\Exception $e) {
            $this->logError('Failed to generate report', [
                'error' => $e->getMessage()
            ]);

            return "Report generation failed: " . $e->getMessage();
        }
    }

    /**
     * Generate custom analysis with provided prompt
     * 
     * Used for learning cycle with manual feedback integration
     * 
     * @param string $customPrompt Pre-built prompt
     * @return string Claude's analysis
     */
    public function generateCustomAnalysis(string $customPrompt): string
    {
        $this->logInfo('Generating custom analysis');

        try {
            $response = $this->callClaudeAPI($customPrompt, 'custom_analysis');
            return $response;

        } catch (\Exception $e) {
            $this->logError('Failed to generate custom analysis', [
                'error' => $e->getMessage()
            ]);

            return "Analysis generation failed: " . $e->getMessage();
        }
    }

    /**
     * Call Claude API
     * 
     * @param string $prompt User prompt
     * @param string $context Context for logging
     * @return string Claude's response
     */
    private function callClaudeAPI(string $prompt, string $context = 'general'): string
    {
        $apiKey = setting('claude_api_key');

        if (!$apiKey) {
            throw new \Exception('Claude API key not configured in settings');
        }

        $maxRetries = setting('api_retry_attempts', 3);
        $retryDelay = setting('api_retry_delay', 2);

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->timeout(30)
                ->post($this->apiEndpoint, [
                    'model' => $this->model,
                    'max_tokens' => 1024,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ]
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $content = $data['content'][0]['text'] ?? '';

                    $this->logInfo('Claude API call successful', [
                        'context' => $context,
                        'response_length' => strlen($content),
                        'attempt' => $attempt
                    ]);

                    return $content;
                }

                throw new \Exception('API request failed: ' . $response->status() . ' - ' . $response->body());

            } catch (\Exception $e) {
                $this->logWarning("Claude API attempt {$attempt} failed", [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries
                ]);

                if ($attempt < $maxRetries) {
                    sleep($retryDelay);
                } else {
                    throw $e;
                }
            }
        }

        throw new \Exception('Claude API call failed after all retries');
    }

    /**
     * Build scoring prompt for Claude
     */
    private function buildScoringPrompt(array $setupData): string
    {
        $pattern = $setupData['candle_pattern'] ?? 'unknown';
        $emaConfluence = $setupData['ema_confluence'] ?? 0;
        $htfBias = $setupData['htf_bias'] ?? 'neutral';
        $sessionSlot = $setupData['session_slot'] ?? 'unknown';
        $marketCondition = $setupData['market_condition'] ?? 'unknown';

        return <<<PROMPT
You are an expert Bank Nifty options trader analyzing a 15-minute timeframe setup.

**Setup Details:**
- Candle Pattern: {$pattern}
- EMA Confluence: {$emaConfluence}/3 EMAs aligned
- Higher Timeframe Bias: {$htfBias}
- Session Slot: {$sessionSlot}
- Market Condition: {$marketCondition}

**Task:**
Score this setup from 1-10 based on confluence quality:
- 8-10: Excellent confluence (all factors aligned, high probability)
- 6-7: Good confluence (most factors aligned, tradeable)
- 4-5: Moderate confluence (some alignment, proceed with caution)
- 1-3: Poor confluence (avoid trade)

**Respond in this EXACT format:**
Score: [number 1-10]
Confidence: [low/medium/high]
Reasoning: [2-3 sentences explaining the score]

Consider:
1. Pattern strength and reliability
2. EMA confluence (more EMAs = better)
3. HTF bias alignment with pattern direction
4. Session timing (morning slots slightly better)
5. Market condition suitability
PROMPT;
    }

    /**
     * Build exception validation prompt
     */
    private function buildExceptionPrompt(array $setupData, string $userReasoning): string
    {
        $score = $setupData['claude_score'] ?? 0;
        $minScore = setting('min_claude_score', 6.0);

        return <<<PROMPT
A trader wants to take this trade despite it scoring {$score}/10 (below minimum {$minScore}).

**Setup:**
Pattern: {$setupData['candle_pattern']}
EMA Confluence: {$setupData['ema_confluence']}/3
HTF Bias: {$setupData['htf_bias']}

**Trader's Reasoning:**
"{$userReasoning}"

**Task:**
Validate if this exception is justified or reckless.

**Respond in this format:**
Valid: [YES/NO]
Risk Level: [low/medium/high]
Reasoning: [Your analysis of whether this override makes sense]

Be critical but fair. Only approve exceptions with genuinely strong reasoning.
PROMPT;
    }

    /**
     * Build trade analysis prompt
     */
    private function buildTradeAnalysisPrompt(array $tradeData): string
    {
        $outcome = $tradeData['outcome'];
        $pattern = $tradeData['candle_pattern'];
        $rrAchieved = $tradeData['rr_achieved'] ?? 0;

        return <<<PROMPT
Analyze this completed Bank Nifty options trade:

**Entry:**
Pattern: {$pattern}
Entry Premium: ₹{$tradeData['entry_premium']}
Claude Score: {$tradeData['claude_score']}/10

**Exit:**
Outcome: {$outcome}
Exit Premium: ₹{$tradeData['exit_premium']}
R:R Achieved: {$rrAchieved}
P&L: ₹{$tradeData['pnl_inr']}

**Task:**
Provide 2-3 sentence analysis:
1. Why did this trade {$outcome}?
2. What can we learn?
3. Should we adjust strategy for similar setups?

Keep it concise and actionable.
PROMPT;
    }

    /**
     * Build report generation prompt
     */
    private function buildReportPrompt(array $trades, string $reportType): string
    {
        $tradeCount = count($trades);
        $wins = collect($trades)->where('outcome', 'win')->count();
        $losses = collect($trades)->where('outcome', 'loss')->count();
        $winRate = $tradeCount > 0 ? round(($wins / $tradeCount) * 100, 1) : 0;

        $tradesText = collect($trades)->map(fn($t) => 
            "{$t['date']}: {$t['candle_pattern']} - {$t['outcome']} (R:R {$t['rr_achieved']})"
        )->implode("\n");

        return <<<PROMPT
Generate a concise {$reportType} trading report for Bank Nifty options.

**Period Summary:**
Total Trades: {$tradeCount}
Wins: {$wins}
Losses: {$losses}
Win Rate: {$winRate}%

**Trades:**
{$tradesText}

**Format:**
1. Performance Summary (2-3 sentences)
2. Pattern Analysis (what worked/didn't work)
3. Key Takeaway (1 actionable insight)

Keep it brief and data-focused.
PROMPT;
    }

    /**
     * Parse Claude's scoring response
     */
    private function parseScoreResponse(string $response): array
    {
        // Extract score (looking for "Score: X" pattern)
        preg_match('/Score:\s*(\d+)/i', $response, $scoreMatch);
        $score = isset($scoreMatch[1]) ? (int)$scoreMatch[1] : 5;

        // Extract confidence
        preg_match('/Confidence:\s*(low|medium|high)/i', $response, $confidenceMatch);
        $confidence = $confidenceMatch[1] ?? 'medium';

        // Extract reasoning (everything after "Reasoning:")
        preg_match('/Reasoning:\s*(.+)/is', $response, $reasoningMatch);
        $reasoning = $reasoningMatch[1] ?? $response;

        return [
            'score' => max(1, min(10, $score)), // Clamp between 1-10
            'confidence' => strtolower($confidence),
            'reasoning' => trim($reasoning),
            'full_response' => $response
        ];
    }

    /**
     * Parse exception validation response
     */
    private function parseExceptionResponse(string $response): array
    {
        preg_match('/Valid:\s*(YES|NO)/i', $response, $validMatch);
        $valid = isset($validMatch[1]) && strtoupper($validMatch[1]) === 'YES';

        preg_match('/Risk Level:\s*(low|medium|high)/i', $response, $riskMatch);
        $riskLevel = $riskMatch[1] ?? 'high';

        preg_match('/Reasoning:\s*(.+)/is', $response, $reasoningMatch);
        $reasoning = $reasoningMatch[1] ?? $response;

        return [
            'valid' => $valid,
            'risk_level' => strtolower($riskLevel),
            'reasoning' => trim($reasoning),
            'full_response' => $response
        ];
    }

    /**
     * Parse trade analysis response
     */
    private function parseAnalysisResponse(string $response): array
    {
        return [
            'analysis' => trim($response),
            'lessons' => $this->extractLessons($response),
            'pattern_performance' => $this->extractPatternPerformance($response)
        ];
    }

    /**
     * Extract lessons from analysis
     */
    private function extractLessons(string $analysis): array
    {
        // Simple extraction - look for numbered points or bullet points
        $lessons = [];
        
        if (preg_match_all('/\d+\.\s*(.+?)(?=\d+\.|$)/s', $analysis, $matches)) {
            $lessons = array_map('trim', $matches[1]);
        }

        return array_slice($lessons, 0, 3); // Max 3 lessons
    }

    /**
     * Extract pattern performance insight
     */
    private function extractPatternPerformance(string $analysis): string
    {
        // Look for mentions of pattern performance
        if (preg_match('/pattern.{0,50}(worked|failed|strong|weak)/i', $analysis, $match)) {
            return trim($match[0]);
        }

        return 'See full analysis';
    }
}

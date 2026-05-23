<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies\CorrectionStrategyInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies\SyntaxErrorStrategy;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies\ColumnErrorStrategy;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies\TableErrorStrategy;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies\GroupByErrorStrategy;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies\JoinErrorStrategy;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies\TypeMismatchStrategy;
use ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent\Strategies\SemanticErrorStrategy;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * CorrectionStrategyManager Class
 * Manages correction strategies and selects appropriate strategy for each error type
 * Provides fallback to LLM reasoning when no specific strategy is available
 */
class CorrectionStrategyManager
{
  private SecurityLogger $logger;
  private bool $debug;
  
  /**
   * @var array<string, CorrectionStrategyInterface> Strategy registry mapping error types to strategies
   */
  private array $strategies = [];

  /**
   * Constructor
   *
   * @param SecurityLogger $logger Security logger instance
   * @param bool $debug Debug mode flag
   */
  public function __construct(SecurityLogger $logger, bool $debug)
  {
    $this->logger = $logger;
    $this->debug = $debug;
    
    // Register all available strategies
    $this->registerStrategy('syntax_error', new SyntaxErrorStrategy());
    $this->registerStrategy('unknown_column', new ColumnErrorStrategy());
    $this->registerStrategy('unknown_table', new TableErrorStrategy());
    $this->registerStrategy('group_by_error', new GroupByErrorStrategy());
    $this->registerStrategy('join_error', new JoinErrorStrategy());
    $this->registerStrategy('type_mismatch', new TypeMismatchStrategy());
    $this->registerStrategy('semantic_error', new SemanticErrorStrategy());
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "CorrectionStrategyManager initialized with " . count($this->strategies) . " strategies",
        'info'
      );
    }
  }

  /**
   * Register a correction strategy
   *
   * @param string $errorType Error type identifier
   * @param CorrectionStrategyInterface $strategy Strategy instance
   * @return void
   */
  public function registerStrategy(string $errorType, CorrectionStrategyInterface $strategy): void
  {
    $this->strategies[$errorType] = $strategy;
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Registered strategy for error type: {$errorType}",
        'info'
      );
    }
  }

  /**
   * Apply appropriate correction strategy
   *
   * @param array $errorContext Error context containing error_message, failed_query, etc.
   * @param array $errorAnalysis Error analysis results
   * @param array $similarCases Similar historical cases
   * @return array Correction result with query, method, confidence, reasoning
   */
  public function applyStrategy(array $errorContext, array $errorAnalysis, array $similarCases): array
  {
    $errorType = $errorAnalysis['type'];
    
    // Select appropriate strategy
    $strategy = $this->selectStrategy($errorType);
    
    if ($strategy !== null) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Applying strategy for error type: {$errorType}",
          'info'
        );
      }
      
      try {
        return $strategy->correct($errorContext, $errorAnalysis, $similarCases);
      } catch (\Exception $e) {
        $this->logger->logSecurityEvent(
          "Strategy execution failed for {$errorType}: " . $e->getMessage(),
          'error'
        );
        
        // Fallback to LLM reasoning
        return $this->correctWithLLMReasoning($errorContext, $errorAnalysis, $similarCases);
      }
    }
    
    // No strategy found, use LLM reasoning
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "No strategy found for error type: {$errorType}, using LLM reasoning",
        'info'
      );
    }
    
    return $this->correctWithLLMReasoning($errorContext, $errorAnalysis, $similarCases);
  }

  /**
   * Select appropriate strategy for error type
   *
   * @param string $errorType Error type identifier
   * @return CorrectionStrategyInterface|null Strategy instance or null if not found
   */
  private function selectStrategy(string $errorType): ?CorrectionStrategyInterface
  {
    return $this->strategies[$errorType] ?? null;
  }

  /**
   * Correction with LLM reasoning (Chain-of-Thought)
   * Fallback method when no specific strategy is available
   *
   * @param array $errorContext Error context
   * @param array $errorAnalysis Error analysis
   * @param array $similarCases Similar cases
   * @return array Proposed correction
   */
  private function correctWithLLMReasoning(array $errorContext, array $errorAnalysis, array $similarCases): array
  {
    try {
      $prompt = $this->buildReasoningPrompt($errorContext, $errorAnalysis, $similarCases);

      $response = Gpt::getGptResponse($prompt, 500);

      $parsed = $this->parseReasoningResponse($response);

      return [
        'query' => $parsed['corrected_query'] ?? $errorContext['failed_query'],
        'method' => 'llm_reasoning',
        'confidence' => $parsed['confidence'] ?? 0.5,
        'reasoning' => $parsed['reasoning'] ?? '',
        'suggestions' => $parsed['suggestions'] ?? [],
      ];

    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "LLM reasoning correction failed: " . $e->getMessage(),
          'error'
        );
      }

      return [
        'query' => $errorContext['failed_query'],
        'method' => 'llm_reasoning_failed',
        'confidence' => 0.0,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Build reasoning prompt for LLM
   * Uses English for internal processing as per domain agnosticism requirement
   * 
   * @param array $errorContext Error context
   * @param array $errorAnalysis Error analysis
   * @param array $similarCases Similar cases
   * @return string Reasoning prompt in English
   */
  private function buildReasoningPrompt(
    array $errorContext,
    array $errorAnalysis,
    array $similarCases
  ): string {
    $parts = [];

    $parts[] = "You are an expert SQL debugging assistant. Analyze and fix this SQL error using step-by-step reasoning.";
    $parts[] = "";
    $parts[] = "## Error Context";
    $parts[] = "Error Type: " . $errorAnalysis['type'];
    $parts[] = "Error Message: " . $errorContext['error_message'];
    $parts[] = "Failed Query:";
    $parts[] = "```sql";
    $parts[] = $errorContext['failed_query'];
    $parts[] = "```";

    if (!empty($errorContext['original_query'])) {
      $parts[] = "";
      $parts[] = "Original User Question: " . $errorContext['original_query'];
    }

    if (!empty($similarCases)) {
      $parts[] = "";
      $parts[] = "## Similar Cases from History";
      foreach (array_slice($similarCases, 0, 2) as $i => $case) {
        $parts[] = "Case " . ($i + 1) . ":";
        $parts[] = "- Original Error: " . $case['original_error'];
        $parts[] = "- Correction Applied: " . $case['correction_method'];
        $parts[] = "- Similarity: " . round($case['similarity_score'] * 100, 1) . "%";
      }
    }

    $parts[] = "";
    $parts[] = "## Your Task";
    $parts[] = "Analyze this error step by step:";
    $parts[] = "1. **Understand**: What is the root cause of this error?";
    $parts[] = "2. **Plan**: What changes are needed to fix it?";
    $parts[] = "3. **Apply**: Generate the corrected SQL query";
    $parts[] = "4. **Validate**: Check if the correction makes sense";
    $parts[] = "";
    $parts[] = "Respond in this format:";
    $parts[] = "REASONING: <your step-by-step analysis>";
    $parts[] = "CORRECTED_QUERY: <the fixed SQL query>";
    $parts[] = "CONFIDENCE: <0.0 to 1.0>";
    $parts[] = "SUGGESTIONS: <optional improvement suggestions>";

    return implode("\n", $parts);
  }

  /**
   * Parse LLM reasoning response
   * 
   * @param string $response LLM response
   * @return array Parsed response data
   */
  private function parseReasoningResponse(string $response): array
  {
    $parsed = [];

    if (preg_match('/REASONING:\s*(.+?)(?=CORRECTED_QUERY:|$)/is', $response, $matches)) {
      $parsed['reasoning'] = trim($matches[1]);
    }

    if (preg_match('/CORRECTED_QUERY:\s*```?sql?\s*(.+?)\s*```?/is', $response, $matches)) {
      $parsed['corrected_query'] = trim($matches[1]);
    } elseif (preg_match('/CORRECTED_QUERY:\s*(.+?)(?=CONFIDENCE:|SUGGESTIONS:|$)/is', $response, $matches)) {
      $parsed['corrected_query'] = trim($matches[1]);
    }

    if (preg_match('/CONFIDENCE:\s*([\d\.]+)/i', $response, $matches)) {
      $parsed['confidence'] = (float) $matches[1];
    }

    if (preg_match('/SUGGESTIONS:\s*(.+?)$/is', $response, $matches)) {
      $suggestions = trim($matches[1]);
      $parsed['suggestions'] = array_filter(explode("\n", $suggestions));
    }

    return $parsed;
  }
}

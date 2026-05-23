<?php
/**
 * LLMFallbackHandler
 * 
 * Handles LLM fallback when no documents are found in vector stores.
 * Queries the LLM directly with the user's question.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Handler\Fallback;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\CLICSHOPPING;

/**
 * LLMFallbackHandler Class
 *
 * Provides LLM fallback functionality for semantic queries when vector stores
 * don't contain relevant information.
 */

class LLMFallbackHandler
{
  private SecurityLogger $logger;
  private bool $debug;

  /**
   * Constructor
   *
   * @param string $userId User ID
   * @param int $languageId Language ID
   * @param bool $debug Enable debug logging
   */
  public function __construct(string $userId = 'system', int $languageId = 1, bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;

    if ($this->debug) {
      $this->logger->logSecurityEvent("LLMFallbackHandler initialized", 'info');
    }
  }

  /**
   * Query LLM with original question
   *
   * @param string $query User query
   * @param array $context Context information
   * @return array LLM response
   */
  public function queryLLM(string $query, array $context = []): array
  {
    $startTime = microtime(true);

    try {
      if ($this->debug) {
        $this->logger->logSecurityEvent("LLMFallbackHandler: Querying LLM with: \"{$query}\"", 'info');
      }

      // Format prompt for LLM
      $prompt = $this->formatPromptForLLM($query, $context);

      // Call LLM API using getGptResponse
      $response = Gpt::getGptResponse($prompt);

      $executionTime = microtime(true) - $startTime;

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "LLM response received in {$executionTime}s (length: " . strlen($response) . ")",
          'info'
        );
      }

      // Validate response is not empty
      if (empty($response) || strlen(trim($response)) < 10) {
        // Log technical details separately
        $this->logger->logSecurityEvent(
          "LLM returned empty or too short response (length: " . strlen($response) . ")",
          'warning'
        );
        
        $errorMessage = "Le système n'a pas pu générer une réponse appropriée";
        
        return [
          'success' => false,
          'error' => 'empty_response',
          'answer' => $errorMessage,
          'response' => $errorMessage,  
          'text_response' => $errorMessage,
          'documents' => [],
          'audit_metadata' => [
            'source' => 'llm',
            'fallback' => true,
            'error' => 'empty_response',
            'response_length' => strlen($response),
            'execution_time' => $executionTime
          ]
        ];
      }

      // Format and return response
      return $this->formatLLMResponse($response);

    } catch (\Exception $e) {
      $executionTime = microtime(true) - $startTime;
      
      // Log technical details separately
      $this->logger->logSecurityEvent(
        "LLM query failed - Technical details: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString(),
        'error'
      );

      // Return user-friendly error message
      $errorMessage = "Je ne peux pas répondre à cette question pour le moment";
      
      return [
        'success' => false,
        'error' => 'llm_failure',
        'answer' => $errorMessage,
        'response' => $errorMessage,  
        'text_response' => $errorMessage,
        'documents' => [],
        'audit_metadata' => [
          'source' => 'llm',
          'fallback' => true,
          'error' => 'llm_failure',
          'error_message' => $e->getMessage(),
          'execution_time' => $executionTime
        ]
      ];
    }
  }

  /**
   * Format prompt for LLM
   *
   * 🔧 FIX 2025-12-28: Use dedicated LLM fallback instruction that allows general knowledge
   * The standard RAG instruction forbids using general knowledge, which breaks LLM fallback
   *
   * @param string $query User query
   * @param array $context Context information
   * @return string Formatted prompt
   */
  private function formatPromptForLLM(string $query, array $context): string
  {
    // Enhanced prompt format with LLM fallback instructions
    $prompt = "Please answer the following question:\n\n";
    $prompt .= "Question: {$query}\n\n";
    
    // Add context if available
    if (!empty($context['conversation_history'])) {
      $prompt .= "Context from previous conversation:\n";
      foreach ($context['conversation_history'] as $item) {
        if (isset($item['role']) && isset($item['content'])) {
          $prompt .= "{$item['role']}: {$item['content']}\n";
        }
      }
      $prompt .= "\n";
    }

    $prompt .= "Answer:";

    // 🔥 CRITICAL FIX: Use LLM fallback instruction (allows general knowledge)
    // NOT the standard RAG instruction (which forbids general knowledge)
    $languageInstruction = CLICSHOPPING::getDef('text_rag_llm_fallback_instruction');
    $prompt .= "\n\n" . $languageInstruction;

    return $prompt;
  }

  /**
   * Format LLM response
   *
   * @param string $response Raw LLM response
   * @return array Formatted response
   */
  private function formatLLMResponse(string $response): array
  {
    // Clean and format the response
    $cleanedResponse = trim($response);

    return [
      'success' => true,
      'answer' => $cleanedResponse,
      'response' => $cleanedResponse,  
      'text_response' => $cleanedResponse,
      'documents' => [], // LLM doesn't return documents
      'audit_metadata' => [
        'source' => 'llm',
        'fallback' => true,
        'response_length' => strlen($cleanedResponse),
        'tables_searched' => 0, // No vector stores searched
        'final_results_count' => 0
      ]
    ];
  }
}

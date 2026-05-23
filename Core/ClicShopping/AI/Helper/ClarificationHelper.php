<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Helper;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;

/**
 * ClarificationHelper Class
 *
 * Helper class for detecting ambiguous queries and generating clarification questions
 * 
 * Responsibilities:
 * - Detect ambiguity in user queries
 * - Generate appropriate clarification questions
 * - Suggest options for disambiguation
 * 
 * PURE LLM MODE: All ambiguity detection is handled by LLM prompts
 * Pattern-based detection has been removed
 * 
 * @package ClicShopping\AI\Helper
 * @since 2025-11-14
 */
class ClarificationHelper
{
  private SecurityLogger $logger;
  private bool $debug;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
  }



  /**
   * Detect if a query is ambiguous
   *
   * @param array $intent Intent analysis result
   * @param string $query Original query
   * @param array $context Conversation context
   * @return array Ambiguity detection result
   */
  public function detectAmbiguity(array $intent, string $query, array $context = []): array
  {
    $isAmbiguous = false;
    $ambiguityType = null;
    $missingInfo = [];
    $suggestions = [];

    // Check 0: Very short or vague queries (NEW - Test 5.5)
    $shortQueryCheck = $this->detectShortOrVagueQuery($query);
    if ($shortQueryCheck['is_ambiguous']) {
      return $shortQueryCheck;
    }

    // Check 1: Very low confidence (< 0.5)
    if (isset($intent['confidence']) && $intent['confidence'] < 0.5) {
      $isAmbiguous = true;
      $ambiguityType = 'low_confidence';
      $missingInfo[] = 'classification_uncertain';

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Ambiguity detected: Low confidence (" . $intent['confidence'] . ")",
          'info'
        );
      }
    }

    // Check 2: Multiple possible entities without clear context
    if (isset($intent['metadata']['entities']) && count($intent['metadata']['entities']) > 1) {
      $hasContext = false;
      foreach ($intent['metadata']['entities'] as $entity) {
        if (isset($entity['from_context']) && $entity['from_context'] === true) {
          $hasContext = true;
          break;
        }
      }

      if (!$hasContext) {
        $isAmbiguous = true;
        $ambiguityType = 'multiple_entities';
        $missingInfo[] = 'entity_selection';
        $suggestions = array_map(
          fn($entity) => $entity['name'] ?? $entity['id'] ?? 'Unknown',
          $intent['metadata']['entities']
        );

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Ambiguity detected: Multiple entities without context",
            'info'
          );
        }
      }
    }

    // Check 3: Missing parameters for analytics query
    if ($intent['type'] === 'analytics') {
      $missingParams = $this->detectMissingParameters($query, $intent);
      if (!empty($missingParams)) {
        $isAmbiguous = true;
        $ambiguityType = 'missing_parameters';
        $missingInfo = $missingParams;

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Ambiguity detected: Missing parameters - " . implode(', ', $missingParams),
            'info'
          );
        }
      }
    }

    // Check 4: Unresolved contextual reference
    if ($this->hasUnresolvedReference($query, $context)) {
      $isAmbiguous = true;
      $ambiguityType = 'unresolved_reference';
      $missingInfo[] = 'contextual_reference';

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Ambiguity detected: Unresolved contextual reference",
          'info'
        );
      }
    }

    return [
      'is_ambiguous' => $isAmbiguous,
      'ambiguity_type' => $ambiguityType,
      'missing_info' => $missingInfo,
      'suggestions' => $suggestions,
      'confidence' => $intent['confidence'] ?? 0,
    ];
  }

  /**
   * Generate a clarification question based on ambiguity type
   *
   * @param array $ambiguityResult Result from detectAmbiguity()
   * @param string $query Original query
   * @param array $intent Intent analysis
   * @return array Clarification question with options
   */
  public function generateClarificationQuestion(array $ambiguityResult, string $query, array $intent): array
  {
    $ambiguityType = $ambiguityResult['ambiguity_type'];
    $missingInfo = $ambiguityResult['missing_info'];
    $suggestions = $ambiguityResult['suggestions'];

    $question = '';
    $options = [];

    switch ($ambiguityType) {
      case 'low_confidence':
        $question = "I'm not sure I understand your question. Could you rephrase it or specify what you're looking for?";
        $options = [
          "Search for general information",
          "Analyze data",
          "Get help"
        ];
        break;

      case 'multiple_entities':
        $question = "Multiple items match your search. Which one are you interested in?";
        $options = $suggestions;
        break;

      case 'missing_parameters':
        $question = $this->generateMissingParameterQuestion($missingInfo);
        $options = $this->generateParameterOptions($missingInfo);
        break;

      case 'unresolved_reference':
        $question = "I'm not sure what you're referring to. Could you clarify?";
        $options = [
          "Specify the item",
          "Restart conversation"
        ];
        break;

      default:
        $question = "Could you please clarify your request?";
        $options = [];
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Generated clarification question: {$question}",
        'info'
      );
    }

    return [
      'question' => $question,
      'options' => $options,
      'ambiguity_type' => $ambiguityType,
      'original_query' => $query,
    ];
  }

  /**
   * Detect missing parameters in a query
   * 
   * PURE LLM MODE: Parameter detection is handled by LLM prompts
   * This method always returns empty array in Pure LLM mode
   *
   * @param string $query Query to analyze (in English)
   * @param array $intent Intent analysis
   * @return array List of missing parameters (always empty in Pure LLM mode)
   */
  private function detectMissingParameters(string $query, array $intent): array
  {
    // PURE LLM MODE: Parameter detection disabled
    // LLM handles ambiguity detection through prompts
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Missing parameter detection bypassed (Pure LLM mode)",
        'info'
      );
    }
    return [];
  }

  /**
   * Check if query has unresolved contextual reference
   * 
   * PURE LLM MODE: No pattern matching
   * Delegates to AmbiguousQueryDetector which uses LLM for detection
   *
   * @param string $query Query to check (in English)
   * @param array $context Conversation context
   * @return bool True if has unresolved reference
   */
  private function hasUnresolvedReference(string $query, array $context): bool
  {
    // PURE LLM MODE: This check is now handled by AmbiguousQueryDetector
    // which uses the LLM prompt to detect unresolved references
    // 
    // The ambiguity prompt already has rules for:
    // - Time expressions ("this month", "this year") are NOT ambiguous
    // - Pronouns without context ARE ambiguous
    //
    // We keep this method for backward compatibility but it always returns false
    // because the real detection happens in the LLM prompt
    
    return false; // LLM handles this in the ambiguity detection prompt
  }

  /**
   * Generate question for missing parameters
   * 
   * PURE LLM MODE: Returns default question
   * LLM handles clarification through prompts
   *
   * @param array $missingInfo Missing parameters
   * @return string Question
   */
  private function generateMissingParameterQuestion(array $missingInfo): string
  {
    // PURE LLM MODE: Return default question
    return "Could you please provide more details about your request?";
  }

  /**
   * Generate options for missing parameters
   * 
   * PURE LLM MODE: Returns empty array
   * LLM handles option generation through prompts
   *
   * @param array $missingInfo Missing parameters
   * @return array Options (always empty in Pure LLM mode)
   */
  private function generateParameterOptions(array $missingInfo): array
  {
    // PURE LLM MODE: Return empty array
    return [];
  }

  /**
   * Detect if a query is too short or vague
   * 
   * PURE LLM MODE: Only checks very basic criteria (length < 2)
   * LLM handles vague query detection through prompts
   *
   * @param string $query Query to check
   * @return array Ambiguity detection result
   */
  private function detectShortOrVagueQuery(string $query): array
  {
    $query = trim($query);
    $queryLength = mb_strlen($query, 'UTF-8');

    // PURE LLM MODE: Only check for extremely short queries (< 2 chars)
    // LLM handles vague query detection through prompts
    
    // Only check for extremely short queries (< 2 chars)
    if ($queryLength < 2) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Query too short: '{$query}' ({$queryLength} chars) - Pure LLM mode",
          'info'
        );
      }

      return [
        'is_ambiguous' => true,
        'ambiguity_type' => 'too_short',
        'missing_info' => ['query_content'],
        'suggestions' => [
          "Rechercher un produit",
          "Voir les commandes",
          "Obtenir de l'aide"
        ],
        'confidence' => 0.0
      ];
    }

    // Query is clear enough for LLM
    return [
      'is_ambiguous' => false,
      'ambiguity_type' => null,
      'missing_info' => [],
      'suggestions' => [],
      'confidence' => 1.0
    ];
  }

  /**
   * Generate helpful suggestions for vague queries
   * 
   * PURE LLM MODE: Returns default suggestions
   * 
   * @return array List of suggested questions
   */
  private function generateVagueSuggestions(): array
  {
    // PURE LLM MODE: Return default suggestions
    return [
      "Rechercher un produit",
      "Voir les commandes",
      "Obtenir de l'aide"
    ];
  }
}

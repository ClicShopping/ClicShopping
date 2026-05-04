<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

/**
 * ContextManager Class
 * Manages conversational context to avoid conflicts between conversation memory and feedback learning
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\CoreAI\Helper\Detection\ContextSwitchDetector;
use ClicShopping\AI\DomainsAI\Semantic\Helper\SemanticDomainDetector;


class ContextManager
{
  private SecurityLogger $securityLogger;
  private ContextSwitchDetector $switchDetector;
  private bool $debug;

  // Context management options
  private array $options = [
    'auto_clear_on_domain_switch' => true,
    'prioritize_feedback_over_context' => true,
    'min_confidence_for_clear' => 0.7,
  ];

  public function __construct(bool $debug = false, array $options = [])
  {
    $this->securityLogger = new SecurityLogger();
    $this->switchDetector = new ContextSwitchDetector($debug);
    $this->debug = $debug;
    $this->options = array_merge($this->options, $options);
  }

  /**
   * Decide how to use context for a new question
   *
   * @param string $query New question
   * @param array $conversationContext Current conversation context
   * @param array $feedbackContext Feedback learning context
   * @return array Decision on context usage
   */
  public function decideContextUsage(string $query, array $conversationContext, array $feedbackContext): array
  {
    // 1. Detect domain change
    $switchDetection = $this->switchDetector->detectContextSwitch($query, $conversationContext);

    // 2. Detect explicit reset markers
    $hasExplicitReset = $this->switchDetector->hasExplicitContextReset($query);

    // 3. Evaluate feedback relevance
    $hasFeedback = !empty($feedbackContext);
    $feedbackRelevance = $this->evaluateFeedbackRelevance($query, $feedbackContext);

    // 4. Make decision
    $decision = $this->makeDecision(
      $switchDetection,
      $hasExplicitReset,
      $hasFeedback,
      $feedbackRelevance
    );

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Context decision: " . json_encode($decision),
        'info'
      );
    }

    return $decision;
  }

  /**
   * Make final decision on context usage
   *
   * @param array $switchDetection Switch detection result
   * @param bool $hasExplicitReset Explicit reset marker
   * @param bool $hasFeedback Feedback presence
   * @param float $feedbackRelevance Feedback relevance score
   * @return array Decision
   */
  private function makeDecision(array $switchDetection, bool $hasExplicitReset, bool $hasFeedback, float $feedbackRelevance): array
  {
    $decision = [
      'use_conversation_context' => true,
      'use_feedback_context' => true,
      'clear_conversation_context' => false,
      'prioritize_feedback' => false,
      'reason' => '',
    ];

    // Case 1: Explicit reset requested
    if ($hasExplicitReset) {
      $decision['clear_conversation_context'] = true;
      $decision['use_conversation_context'] = false;
      $decision['reason'] = 'Explicit context reset requested';
      return $decision;
    }

    // Case 2: Domain switch detected
    if ($switchDetection['has_switch'] && $this->options['auto_clear_on_domain_switch']) {
      if ($switchDetection['confidence'] >= $this->options['min_confidence_for_clear']) {
        $decision['clear_conversation_context'] = true;
        $decision['use_conversation_context'] = false;
        $decision['reason'] = sprintf(
          'Domain switch detected: %s -> %s (confidence: %.2f)',
          $switchDetection['previous_domain'],
          $switchDetection['new_domain'],
          $switchDetection['confidence']
        );
        return $decision;
      }
    }

    // Case 3: Relevant feedback available
    if ($hasFeedback && $feedbackRelevance > 0.6) {
      if ($this->options['prioritize_feedback_over_context']) {
        $decision['prioritize_feedback'] = true;
        $decision['use_conversation_context'] = false;
        $decision['reason'] = sprintf(
          'Prioritizing feedback learning (relevance: %.2f)',
          $feedbackRelevance
        );
        return $decision;
      }
    }

    // Case 4: Use both contexts (default)
    $decision['reason'] = 'Using both contexts with conversation priority';
    return $decision;
  }

  /**
   * Evaluate feedback relevance for current question
   *
   * @param string $query Current question
   * @param array $feedbackContext Feedback context
   * @return float Relevance score (0-1)
   */
  private function evaluateFeedbackRelevance(string $query, array $feedbackContext): float
  {
    if (empty($feedbackContext)) {
      return 0.0;
    }

    $query = mb_strtolower($query);
    $maxRelevance = 0.0;

    foreach ($feedbackContext as $feedback) {
      $originalQuery = mb_strtolower($feedback['original_query'] ?? '');
      
      // Calculate simple similarity (can be improved with Levenshtein or embeddings)
      $similarity = $this->calculateSimpleSimilarity($query, $originalQuery);
      
      if ($similarity > $maxRelevance) {
        $maxRelevance = $similarity;
      }
    }

    return $maxRelevance;
  }

  /**
   * Calculate simple similarity between two texts
   *
   * @param string $text1 First text
   * @param string $text2 Second text
   * @return float Similarity score (0-1)
   */
  private function calculateSimpleSimilarity(string $text1, string $text2): float
  {
    // Simple tokenization
    $words1 = array_unique(preg_split('/\s+/', $text1));
    $words2 = array_unique(preg_split('/\s+/', $text2));

    // Intersection
    $common = array_intersect($words1, $words2);

    // Jaccard similarity
    $union = array_unique(array_merge($words1, $words2));
    
    if (empty($union)) {
      return 0.0;
    }

    return count($common) / count($union);
  }

  /**
   * Filter conversation context based on decision
   *
   * @param array $conversationContext Complete context
   * @param array $decision Management decision
   * @return array Filtered context
   */
  public function filterConversationContext(
    array $conversationContext,
    array $decision
  ): array {
    if ($decision['clear_conversation_context'] || !$decision['use_conversation_context']) {
      // Clear short-term context but KEEP long-term memory
      // Long-term memory is based on semantic similarity, so it's relevant
      $filteredLongTerm = $this->filterLongTermMemory(
        $conversationContext['long_term_context'] ?? [],
        $decision
      );
      
      $feedbackContext = $conversationContext['feedback_context'] ?? [];
      
      return [
        'short_term_context' => [],
        'long_term_context' => $filteredLongTerm,
        'feedback_context' => $feedbackContext,
        'has_context' => !empty($feedbackContext) || !empty($filteredLongTerm),
      ];
    }

    return $conversationContext;
  }

  /**
   * Filter long-term context on domain change
   *
   * @param array $longTermContext Long-term context (memory)
   * @param array $decision Agent decision (contains new domain)
   * @return array Unfiltered context (LLM determines relevance)
   */
  private function filterLongTermMemory(array $longTermContext, array $decision): array
  {
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Long-term memory filtering SKIPPED - Pure LLM mode (LLM determines relevance)",
        'info'
      );
    }
    
    return $longTermContext; // Return unfiltered context in pure LLM mode
  }

  /**
   * Create enriched context with decision metadata
   *
   * @param array $conversationContext Conversation context
   * @param array $decision Decision made
   * @return array Enriched context
   */
  public function enrichContextWithDecision(
    array $conversationContext,
    array $decision
  ): array {
    $conversationContext['context_decision'] = $decision;
    return $conversationContext;
  }

  /**
   * Configure context management options
   *
   * @param array $options New options
   * @return void
   */
  public function setOptions(array $options): void
  {
    $this->options = array_merge($this->options, $options);
  }

  /**
   * Get current options
   *
   * @return array Options
   */
  public function getOptions(): array
  {
    return $this->options;
  }
}

<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;

use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\Infrastructure\Prompt\PromptBuilder;

/**
 * QueryEnricher - prepares/enriches the user question before SQL generation.
 *
 * Extracted from AnalyticsAgent (god-class decomposition): groups the question-enrichment
 * concern (correction examples via PromptBuilder + last_entity context from ConversationMemory).
 * Bodies moved verbatim. ConversationMemory is passed per call because the agent sets it at
 * runtime (setConversationMemory), so it cannot be injected at construction time.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Agent
 * @since 2026-06-11
 */
class QueryEnricher
{
  public function __construct(
    private PromptBuilder $promptBuilder,
    private mixed $language,
    private bool $debug = false
  ) {}

  /**
   * Enrich the question with the last executed SQL query for follow-up context.
   *
   * @param string $question The user's question (already translated to English)
   * @param string $lastSQL The last executed SQL query
   * @return string The enriched question
   */
  public function enrichWithLastSQL(string $question, string $lastSQL): string
  {
    return $this->promptBuilder->enrichWithLastSQL($question, $lastSQL);
  }

  /**
   * Enrich the question with feedback corrections and last_entity context.
   *
   * Adds examples from previous corrections to help the LLM generate better SQL, and injects
   * last_entity context from ConversationMemory so contextual queries (e.g. "donne moi son sku")
   * can be resolved during SQL generation.
   *
   * @param string $question Original question
   * @param array $feedbackContext Feedback items with corrections
   * @param mixed $conversationMemory ConversationMemory instance (or null when not set yet)
   * @return string Enriched question with learning examples and entity context
   */
  public function enrichWithFeedback(string $question, array $feedbackContext, mixed $conversationMemory = null): string
  {
    // Start with feedback enrichment
    $enrichedQuestion = $this->promptBuilder->enrichWithFeedback($question, $feedbackContext);

    // Inject last_entity context if available
    // This provides the LLM with context about what entity was discussed previously
    // so it can resolve pronouns like "son", "sa", "it", "its", etc.
    if ($conversationMemory !== null) {
      try {
        $lastEntity = $conversationMemory->getLastEntity();

        if ($lastEntity !== null) {
          // Check if 'name' key exists before accessing it
          $entityName = $lastEntity['name'] ?? ($lastEntity['id'] ?? 'unknown');
          $entityType = $lastEntity['type'] ?? 'entity';
          $entityId = $lastEntity['id'] ?? 'unknown';

          DomainConfig::loadLanguageFile('rag_analytics_agent');

          $array = [
            'entityType' => $entityType,
            'entityName' => $entityName,
            'entityId' => $entityId
          ];

          $contextString = $this->language->getDef('text_analytic_agent_context', $array);

          // Inject context BEFORE the question so the LLM sees it first
          $enrichedQuestion = $contextString . $enrichedQuestion;

          if ($this->debug) {
            error_log("[AnalyticsAgent] Injected last_entity context into SQL prompt:");
            error_log("  Entity: {$entityType} (ID: {$entityId}, Name: {$entityName})");
          }
        }
      } catch (\Exception $e) {
        // Don't fail on context injection errors - just log and continue
        if ($this->debug) {
          error_log("[AnalyticsAgent] Error injecting last_entity context: " . $e->getMessage());
        }
      }
    }

    return $enrichedQuestion;
  }
}

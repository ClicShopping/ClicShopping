<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Planner;

use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\DomainsAI\WebSearch\Logger\WebSearchLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\Registry;

/**
 * HybridQueryPlanner - Task planner for compound search queries
 *
 * Detects when a user query contains multiple independent search intents
 * (e.g. "What are smartphone trends AND compare iPhone 17 Pro price on Amazon?")
 * and decomposes it into a plan of separate tasks.
 *
 * Each task is then routed and executed independently by WebSearchFacade,
 * and the results are merged into a unified response.
 *
 * Designed to be extensible: adding new modes (e.g. Google Trends) only
 * requires adding the mode to the engine map in WebSearchExecutor and
 * updating the ModeSelector — the planner itself is mode-agnostic.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Planner
 */
class HybridQueryPlanner
{
  private const LLM_MAX_TOKENS = 600;
  private const LLM_TEMPERATURE = 0.2;

  private bool $debug;
  private WebSearchLogger $logger;
  private object $language;

  public function __construct()
  {
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->logger = new WebSearchLogger();

    DomainConfig::loadLanguageFile('rag_intent_router');
    $this->language = Registry::get('Language');
  }

  /**
   * Analyze a query and return a decomposition plan
   *
   * Uses LLM to detect whether the query contains multiple independent intents.
   * Returns WebSearchPlan::single() if the query is not compound or if the LLM
   * analysis fails (safe fallback to standard single-intent routing).
   *
   * @param string $query User query
   * @return WebSearchPlan Plan with tasks or single-intent indicator
   */
  public function analyze(string $query): WebSearchPlan
  {
    try {
      $prompt = $this->language->getDef('text_compound_query_detection_prompt', ['query' => $query]);

      if (empty($prompt)) {
        return WebSearchPlan::single();
      }

      $llmResponse = Gpt::getGptResponse(
        $prompt,
        self::LLM_MAX_TOKENS,
        self::LLM_TEMPERATURE,
        null,
        1
      );

      if (empty($llmResponse)) {
        return WebSearchPlan::single();
      }

      if ($this->debug) {
        error_log('[HybridQueryPlanner] LLM response: ' . substr($llmResponse, 0, 300));
      }

      $data = $this->parseLLMResponse($llmResponse);

      if ($data === null || !($data['is_compound'] ?? false) || empty($data['tasks'])) {
        return WebSearchPlan::single();
      }

      // Validate and sanitize each task
      $tasks = $this->sanitizeTasks($data['tasks']);

      if (empty($tasks)) {
        return WebSearchPlan::single();
      }

      if ($this->debug) {
        error_log(sprintf(
          '[HybridQueryPlanner] Compound query detected: %d tasks for "%s"',
          count($tasks),
          substr($query, 0, 80)
        ));
      }

      $this->logger->logInfo('Compound query decomposed', [
        'query' => substr($query, 0, 100),
        'task_count' => count($tasks),
        'intents' => array_column($tasks, 'intent')
      ]);

      return WebSearchPlan::compound($tasks);

    } catch (\Exception $e) {
      $this->logger->logWarning('HybridQueryPlanner analysis failed: ' . $e->getMessage(), [
        'query' => substr($query, 0, 100)
      ]);
      return WebSearchPlan::single();
    }
  }

  /**
   * Extract and decode JSON from LLM response text
   *
   * @param string $llmResponse Raw LLM text
   * @return array|null Decoded data or null on failure
   */
  private function parseLLMResponse(string $llmResponse): ?array
  {
    if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $llmResponse, $match)) {
      $data = json_decode($match[0], true);
      if (is_array($data) && array_key_exists('is_compound', $data)) {
        return $data;
      }
    }
    return null;
  }

  /**
   * Validate and sanitize task list from LLM response
   *
   * @param array $rawTasks Raw tasks from LLM
   * @return array Validated tasks with required fields
   */
  private function sanitizeTasks(array $rawTasks): array
  {
    $validIntents = ['price_comparison', 'product_discovery', 'market_research'];
    $tasks = [];

    foreach ($rawTasks as $task) {
      if (!is_array($task) || empty($task['query'])) {
        continue;
      }

      $intent = $task['intent'] ?? 'product_discovery';
      if (!in_array($intent, $validIntents, true)) {
        $intent = 'product_discovery';
      }

      $targetSite = $task['target_site'] ?? null;
      if ($targetSite !== null) {
        $targetSite = strtolower(trim($targetSite));
      }

      $tasks[] = [
        'query' => trim($task['query']),
        'intent' => $intent,
        'product' => $task['product'] ?? null,
        'target_site' => $targetSite,
      ];
    }

    return $tasks;
  }
}

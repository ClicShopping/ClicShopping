<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents;

use ClicShopping\AI\InterfacesAI\ActorAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCapability;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\AI\Infrastructure\Cache\Cache;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services\TranslationServiceWrapper;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services\LLMServiceWrapper;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Prompts\SerpAnalysisPrompts;
use ClicShopping\AI\DomainsAI\WebSearch\WebSearchFacade;

/**
 * SerpAgent
 *
 * SERP Analysis Agent using Pure LLM Mode.
 * Analyzes search engine results pages using LLM reasoning instead of pattern matching.
 *
 * Key Features:
 * - Pure LLM-based intent classification
 * - LLM-based feature detection
 * - LLM-based topic extraction
 * - LLM-based competitor analysis
 * - Translation to English before analysis
 * - No hardcoded keywords or pattern matching
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\CoreAI
 * @since 2026-03-02
 */
class SerpAgent implements ActorAgentInterface
{
  /** Cache TTL for LLM analysis sub-results (intent, topics, competitors): 6 hours */
  private const SERP_CACHE_TTL    = 21600;
  private const SERP_CACHE_PREFIX = 'serp_llm_';

  private string $actorId;
  private bool $debug;
  private TranslationServiceWrapper $translator;
  private LLMServiceWrapper $llm;
  private Cache $cache;
  /** @var int Running count of LLM calls made during one executeAction() invocation */
  private int $llmCallCount = 0;

  public function __construct(bool $debug = false)
  {
    $this->actorId = 'seo_serp_actor_' . uniqid();
    $this->debug = $debug || (defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True');
    
    $this->translator = new TranslationServiceWrapper($this->debug);
    $this->llm = new LLMServiceWrapper($this->debug);
    $this->cache = new Cache(true);
  }

  public function executeAction(Action $action): ActionResult
  {
    $start = microtime(true);
    $this->llmCallCount = 0;
    $params = $action->getParameters();

    $query = trim((string)($params['query'] ?? ''));
    if ($query === '' && !empty($params['entity_name'])) {
      $query = (string)$params['entity_name'];
    }

    $baseUrl = (string)($params['base_url'] ?? '');
    $entityType = (string)($params['entity_type'] ?? 'unknown');
    $language = (string)($params['language'] ?? 'en');

    $output = [
      'success' => false,
      'query' => $query,
      'language' => $language,
      'source' => 'serpapi',
      'error' => null,
      'intent_dominant' => 'unknown',
      'intent_confidence' => 0.0,
      'intent_reasoning' => '',
      'features_visible' => [],
      'ai_overview' => null,
      'ai_overview_insights' => null,
      'topics' => [],
      'keywords' => [],
      'types_of_pages' => [],
      'serp_stability' => [
        'label' => 'unknown',
        'score' => 0.0,
        'reason' => 'insufficient data',
      ],
      'cannibalization' => [
        'risk' => 'unknown',
        'details' => [],
      ],
      'competitor_insights' => [],
      'top_results' => [],
    ];

    try {
      if ($query === '') {
        throw new \InvalidArgumentException('SERP query cannot be empty');
      }

      // Translate query to English for SERP search
      $queryEn = $this->translateToEnglish($query, $language);

      if ($this->debug) {
        error_log("[INFO SerpAgent] Original query: {$query}");
        error_log("[INFO SerpAgent] Translated query: {$queryEn}");
      }

      // Fetch SERP data
      $serpData = $this->fetchSerpData($queryEn);

      if (!$serpData['success']) {
        $output['error'] = $serpData['error'] ?? 'SERP search failed';
      } else {
        $items = $serpData['items'] ?? [];

        // Extract AI Overview
        $aiOverview = $this->extractAiOverview($serpData);
        $output['ai_overview'] = $aiOverview;

        // Analyze AI Overview if present
        if ($aiOverview !== null) {
          $output['ai_overview_insights'] = $this->analyzeAiOverview($aiOverview, $queryEn, $entityType);
        }

        // Analyze using LLM
        $output['success'] = true;
        
        // Intent analysis
        $intentResult = $this->analyzeIntent($queryEn, $entityType, $query, $items, $language);
        $output['intent_dominant'] = $intentResult['intent'] ?? 'unknown';
        $output['intent_confidence'] = $intentResult['confidence'] ?? 0.0;
        $output['intent_reasoning'] = $intentResult['reasoning'] ?? '';

        // Feature detection
        $output['features_visible'] = $this->detectFeatures($items, $serpData);

        // Topic extraction
        $topicResult = $this->extractTopics($items, $queryEn, $entityType, $language);
        $output['topics'] = $topicResult['topics'] ?? [];
        $output['keywords'] = $topicResult['keywords'] ?? [];

        // Page type classification
        $output['types_of_pages'] = $this->detectPageTypes($items);

        // Stability analysis
        $output['serp_stability'] = $this->estimateStability($items);

        // Cannibalization detection
        $output['cannibalization'] = $this->detectCannibalization($items, $baseUrl);

        // Competitor analysis
        $output['competitor_insights'] = $this->analyzeCompetitors($items, $queryEn, $entityType, $language);

        // Format top results
        $output['top_results'] = $this->formatTopResults($items);
      }
    } catch (\Throwable $e) {
      $output['error'] = $e->getMessage();
      
      if ($this->debug) {
        error_log("[ERROR SerpAgent] Error: " . $e->getMessage());
        error_log("[INFO SerpAgent] Trace: " . $e->getTraceAsString());
      }
    }

    $metrics = [
      'execution_time_ms' => (int)((microtime(true) - $start) * 1000),
      'serpapi_available' => Gpt::isSerpApiAvailable(),
      'llm_calls'         => $this->llmCallCount,
    ];

    return new ActionResult(
      $action->getActionId(),
      $this->actorId,
      $output,
      'seo_serp_report',
      $metrics,
      $action->getContext(),
      ($output['success'] ?? false) ? 'success' : 'failed'
    );
  }

  /**
   * Translate query to English
   *
   * @param string $query Query text
   * @param string $fromLang Source language code
   * @return string Translated query in English
   */
  private function translateToEnglish(string $query, string $fromLang): string
  {
    if ($fromLang === 'en') {
      return $query;
    }

    try {
      return $this->translator->translate($query, $fromLang, 'en');
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[ERROR SerpAgent] Translation failed: " . $e->getMessage());
      }
      return $query; // Fallback to original
    }
  }








  /**
   * Fetch SERP data using WebSearchTool
   *
   * @param string $query Search query
   * @return array SERP data
   */
  private function fetchSerpData(string $query): array
  {
    try {
      $webSearch = new WebSearchFacade();

      $searchOptions = [
        'max_results' => 10,
      ];

      if ($this->debug) {
        error_log('[INFO SerpAgent] Fetching SERP data for: ' . $query);
      }

      return $webSearch->search($query, $searchOptions);

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[ERROR SerpAgent] SERP fetch failed: " . $e->getMessage());
      }

      return [
        'success' => false,
        'error' => $e->getMessage(),
        'items' => [],
      ];
    }
  }

  /**
   * Extract AI Overview from SERP data
   *
   * @param array $serpData SERP data from WebSearchTool
   * @return array|null AI Overview data or null if not present
   */
  private function extractAiOverview(array $serpData): ?array
  {
    // Check if AI Overview is present
    if (!isset($serpData['ai_overview']) || empty($serpData['ai_overview'])) {
      if ($this->debug) {
        error_log("[INFO SerpAgent] No AI Overview found in SERP data");
      }
      return null;
    }

    $aiOverview = $serpData['ai_overview'];

    // Validate structure
    if (!is_array($aiOverview)) {
      if ($this->debug) {
        error_log("[ERROR SerpAgent] AI Overview is not an array");
      }
      return null;
    }

    // Extract and structure AI Overview data
    $extracted = [
      'full_summary' => $aiOverview['full_summary'] ?? '',
      'text_blocks' => $aiOverview['text'] ?? [],
      'sources' => $aiOverview['sources'] ?? [],
      'is_generative' => $aiOverview['is_generative'] ?? true,
    ];

    // Validate that we have content
    if (empty($extracted['full_summary']) && empty($extracted['text_blocks'])) {
      if ($this->debug) {
        error_log("[INFO SerpAgent] AI Overview has no content");
      }
      return null;
    }

    if ($this->debug) {
      error_log("[INFO SerpAgent] AI Overview extracted successfully");
    }

    return $extracted;
  }

  /**
   * Analyze AI Overview using LLM to extract key insights
   *
   * @param array $aiOverview AI Overview data
   * @param string $queryEn Query in English
   * @param string $entityType Entity type
   * @return array AI Overview insights
   */
  private function analyzeAiOverview(
    array $aiOverview,
    string $queryEn,
    string $entityType
  ): array
  {
    try {
      // Get full summary text
      $summary = $aiOverview['full_summary'] ?? '';

      // If no full summary, concatenate text blocks
      if (empty($summary) && !empty($aiOverview['text_blocks'])) {
        if (is_array($aiOverview['text_blocks'])) {
          $summary = implode("\n", $aiOverview['text_blocks']);
        }
      }

      if (empty($summary)) {
        return [
          'key_points' => [],
          'relevance_score' => 0.0,
          'content_gaps' => [],
          'opportunities' => [],
        ];
      }

      // Get prompt for AI Overview analysis
      $prompt = SerpAnalysisPrompts::getAiOverviewAnalysisPrompt(
        $summary,
        $queryEn,
        $entityType
      );

      // Call LLM
      $response = $this->llmStructuredResponse($prompt, 500, 0.4);

      return [
        'key_points' => $response['key_points'] ?? [],
        'relevance_score' => (float)($response['relevance_score'] ?? 0.0),
        'content_gaps' => $response['content_gaps'] ?? [],
        'opportunities' => $response['opportunities'] ?? [],
        'user_intent_match' => $response['user_intent_match'] ?? '',
      ];

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[SerpAgent] AI Overview analysis failed: " . $e->getMessage());
      }

      return [
        'key_points' => [],
        'relevance_score' => 0.0,
        'content_gaps' => [],
        'opportunities' => [],
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Wraps LLM structured call with a per-query cache (TTL 6h) using the same
   * Cache infrastructure as WebSearchTool (getCachedResponse / cacheResponse).
   * Increments llmCallCount only on actual LLM calls.
   *
   * @param string $prompt
   * @param int    $maxTokens
   * @param float  $temperature
   * @param string $cacheKey  Non-empty → attempt cache read/write; empty → skip cache
   * @return array
   */
  private function llmStructuredResponse(
    string $prompt,
    int    $maxTokens,
    float  $temperature,
    string $cacheKey = ''
  ): array {
    // cache read
    if ($cacheKey !== '') {
      try {
        $cached = $this->cache->getCachedResponse($cacheKey);
        if ($cached !== null) {
          $decoded = json_decode($cached, true);
          if (is_array($decoded)) {
            if ($this->debug) {
              error_log("[INFO SerpAgent] LLM cache HIT: {$cacheKey}");
            }
            return $decoded;
          }
        }
      } catch (\Throwable $e) {
        if ($this->debug) {
          error_log("[ERROR SerpAgent] Cache read error ({$cacheKey}): " . $e->getMessage());
        }
      }
    }

    // ── LLM call ──────────────────────────────────────────────────────────────
    $this->llmCallCount++;
    $result = $this->llm->generateStructuredResponse($prompt, [
      'maxTokens'   => $maxTokens,
      'temperature' => $temperature,
    ]);

    // ── Cache write ───────────────────────────────────────────────────────────
    if ($cacheKey !== '' && !empty($result)) {
      try {
        $this->cache->cacheResponse(
          $cacheKey,
          json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          self::SERP_CACHE_TTL
        );
        if ($this->debug) {
          error_log("[INFO SerpAgent] LLM cache WRITE: {$cacheKey}");
        }
      } catch (\Throwable $e) {
        if ($this->debug) {
          error_log("[ERROR SerpAgent] Cache write error ({$cacheKey}): " . $e->getMessage());
        }
      }
    }

    return $result;
  }

  /**
   * Builds a deterministic cache key for a given LLM sub-analysis.
   * Key = prefix + md5(query + language + entityType + bucket).
   */
  private function serpCacheKey(
    string $query,
    string $language,
    string $entityType,
    string $bucket
  ): string {
    return self::SERP_CACHE_PREFIX . md5($query . $language . $entityType . $bucket);
  }

  /**
   * Analyze search intent using LLM
   *
   * @param string $queryEn Query in English
   * @param string $entityType Entity type
   * @param string $entityName Entity name
   * @param array $items SERP items
   * @return array Intent analysis result
   */
  private function analyzeIntent(
    string $queryEn,
    string $entityType,
    string $entityName,
    array  $items,
    string $language = 'en'
  ): array
  {
    try {
      $cacheKey    = $this->serpCacheKey($queryEn, $language, $entityType, 'intent');
      $serpResults = $this->formatSerpResultsForLLM($items);
      $prompt      = SerpAnalysisPrompts::getIntentAnalysisPrompt(
        $queryEn,
        $entityType,
        $entityName,
        $serpResults
      );
      $response = $this->llmStructuredResponse($prompt, 300, 0.3, $cacheKey);

      return [
        'intent'     => $response['intent']     ?? 'unknown',
        'confidence' => (float)($response['confidence'] ?? 0.0),
        'reasoning'  => $response['reasoning']  ?? '',
      ];

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[SerpAgent] Intent analysis failed: " . $e->getMessage());
      }

      return [
        'intent' => 'unknown',
        'confidence' => 0.0,
        'reasoning' => 'Analysis failed: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Format SERP results for LLM consumption
   *
   * @param array $items SERP items
   * @return string Formatted text
   */
  private function formatSerpResultsForLLM(array $items): string
  {
    $buffer = '';

    foreach ($items as $i => $item) {
      $position = $i + 1;
      $title = $item['title'] ?? '';
      $snippet = $item['snippet'] ?? '';
      $link = $item['link'] ?? '';

      $buffer .= "Result {$position}:\n";
      $buffer .= "Title: {$title}\n";
      $buffer .= "URL: {$link}\n";
      $buffer .= "Snippet: {$snippet}\n\n";
    }

    return $buffer;
  }

  /**
   * Detect SERP features using LLM
   *
   * @param array $items SERP items
   * @param array $serpData Full SERP data
   * @return array List of detected features
   */
  private function detectFeatures(array $items, array $serpData): array
  {
    try {
      $serpDataText = json_encode($serpData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

      if (mb_strlen($serpDataText, 'UTF-8') > 12000) {
        $serpDataText = mb_substr($serpDataText, 0, 12000, 'UTF-8') . "\n...(truncated)";
      }

      $prompt = SerpAnalysisPrompts::getFeatureDetectionPrompt($serpDataText);

      // Call LLM
      $response = $this->llmStructuredResponse($prompt, 200, 0.2);

      // Extract features that are present
      $features = [];
      foreach ($response as $feature => $present) {
        if ($present === true) {
          $features[] = $feature;
        }
      }

      return $features;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[SerpAgent] Feature detection failed: " . $e->getMessage());
      }

      return [];
    }
  }

  /**
   * Extract topics using LLM
   *
   * @param array $items SERP items
   * @return array Topics and keywords
   */
  private function extractTopics(
    array  $items,
    string $queryEn    = '',
    string $entityType = '',
    string $language   = 'en'
  ): array
  {
    // Guard: when WebSearch returned zero results the LLM has nothing to
    // analyze.  Asking it to "extract topics" from an empty SERP blob
    // makes the model invent generic SEO meta-topics like
    // "Search Engine Optimization (SEO) techniques", which then leak into
    // primary_keyword and contaminate the whole optimization downstream.
    // Returning empty arrays makes SeoOptimizationAgent fall back to the
    // product name as the primary keyword, which is the correct behaviour
    // when no SERP signal is available.
    if (empty($items)) {
      if ($this->debug) {
        error_log('[SerpAgent] Topic extraction skipped: empty SERP results.');
      }
      return [
        'topics'           => [],
        'keywords'         => [],
        'content_patterns' => [],
        'user_questions'   => [],
      ];
    }

    try {
      $cacheKey    = $this->serpCacheKey($queryEn, $language, $entityType, 'topics');
      $serpResults = $this->formatSerpResultsForLLM($items);
      $prompt      = SerpAnalysisPrompts::getTopicExtractionPrompt($serpResults);
      $response    = $this->llmStructuredResponse($prompt, 400, 0.4, $cacheKey);

      return [
        'topics'           => $response['topics']           ?? [],
        'keywords'         => $response['keywords']         ?? [],
        'content_patterns' => $response['content_patterns'] ?? [],
        'user_questions'   => $response['user_questions']   ?? [],
      ];

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[SerpAgent] Topic extraction failed: " . $e->getMessage());
      }

      return [
        'topics' => [],
        'keywords' => [],
        'content_patterns' => [],
        'user_questions' => [],
      ];
    }
  }

  /**
   * Detect page types for all SERP results using a single batched LLM call.
   *
   * Previously this method called the LLM once per SERP result (up to 10 calls).
   * It now sends all items in a single prompt and parses the indexed JSON response,
   * reducing LLM cost and latency by ~90 % for a typical 10-result SERP.
   *
   * @param array $items SERP items (each with 'link', 'title', 'snippet')
   * @return array Page type distribution sorted by count descending
   */
  private function detectPageTypes(array $items): array
  {
    if (empty($items)) {
      return [];
    }

    $types = [];

    try {
      // Single LLM call for all items
      $prompt   = SerpAnalysisPrompts::getBatchPageTypePrompt($items);
      $response = $this->llmStructuredResponse($prompt, 400, 0.2);

      foreach ($items as $i => $item) {
        $position = (string)($i + 1);
        $entry    = $response[$position] ?? null;
        $types[]  = (is_array($entry) && !empty($entry['page_type']))
          ? (string)$entry['page_type']
          : 'other';
      }

      if ($this->debug) {
        error_log('[SerpAgent] detectPageTypes batch: ' . count($items) . ' items → 1 LLM call');
      }
    } catch (\Exception $e) {
      // Graceful fallback: mark every item as 'other' rather than crashing
      if ($this->debug) {
        error_log('[SerpAgent] detectPageTypes batch failed: ' . $e->getMessage());
      }
      $types = array_fill(0, count($items), 'other');
    }

    // Aggregate counts
    $counts = array_count_values($types);
    arsort($counts);

    $out = [];
    foreach ($counts as $type => $count) {
      $out[] = [
        'type'  => $type,
        'count' => $count,
      ];
    }

    return $out;
  }

  /**
   * Estimate SERP stability using LLM
   *
   * @param array $items SERP items
   * @return array Stability analysis
   */
  private function estimateStability(array $items): array
  {
    try {
      // Extract domains
      $domains = [];
      foreach ($items as $item) {
        $host = parse_url((string)($item['link'] ?? ''), PHP_URL_HOST);
        if (!empty($host)) {
          $domains[] = $host;
        }
      }

      if (empty($domains)) {
        return [
          'label' => 'unknown',
          'score' => 0.0,
          'reason' => 'no domains detected',
        ];
      }

      // Get prompt
      $prompt = SerpAnalysisPrompts::getStabilityAnalysisPrompt($domains);

      // Call LLM
      $response = $this->llmStructuredResponse($prompt, 300, 0.3);

      return [
        'label' => $response['stability'] ?? 'unknown',
        'score' => (float)($response['score'] ?? 0.0),
        'reason' => $response['reasoning'] ?? '',
        'major_players' => $response['major_players'] ?? [],
        'diversity_score' => (float)($response['diversity_score'] ?? 0.0),
      ];

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[SerpAgent] Stability analysis failed: " . $e->getMessage());
      }

      return [
        'label' => 'unknown',
        'score' => 0.0,
        'reason' => 'Analysis failed',
      ];
    }
  }

  /**
   * Detect keyword cannibalization using LLM
   *
   * @param array $items SERP items
   * @param string $baseUrl Base URL to check
   * @return array Cannibalization analysis
   */
  private function detectCannibalization(array $items, string $baseUrl): array
  {
    $baseHost = parse_url($baseUrl, PHP_URL_HOST) ?: '';
    if ($baseHost === '') {
      return [
        'risk' => 'unknown',
        'details' => ['base_url_missing'],
      ];
    }

    try {
      // Get prompt
      $prompt = SerpAnalysisPrompts::getCannibalizationPrompt($items, $baseHost);

      // Call LLM
      $response = $this->llmStructuredResponse($prompt, 400, 0.3);

      return [
        'risk' => $response['risk'] ?? 'unknown',
        'matching_results' => (int)($response['matching_results'] ?? 0),
        'pages' => $response['pages'] ?? [],
        'recommendation' => $response['recommendation'] ?? '',
      ];

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[SerpAgent] Cannibalization detection failed: " . $e->getMessage());
      }

      return [
        'risk' => 'unknown',
        'details' => ['analysis_failed'],
      ];
    }
  }

  /**
   * Analyze competitors using LLM
   *
   * @param array $items SERP items
   * @param string $queryEn Query in English
   * @return array Competitor insights
   */
  private function analyzeCompetitors(
    array  $items,
    string $queryEn,
    string $entityType = '',
    string $language   = 'en'
  ): array
  {
    // Same guard as extractTopics(): without real SERP results the LLM
    // would invent fictitious competitor patterns.
    if (empty($items)) {
      if ($this->debug) {
        error_log('[SerpAgent] Competitor analysis skipped: empty SERP results.');
      }
      return [];
    }

    try {
      $cacheKey    = $this->serpCacheKey($queryEn, $language, $entityType, 'competitors');
      $serpResults = $this->formatSerpResultsForLLM($items);
      $prompt      = SerpAnalysisPrompts::getCompetitorAnalysisPrompt($serpResults, $queryEn);
      return $this->llmStructuredResponse($prompt, 500, 0.4, $cacheKey);

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[SerpAgent] Competitor analysis failed: " . $e->getMessage());
      }

      return [];
    }
  }

  /**
   * Format top results
   *
   * @param array $items SERP items
   * @return array Formatted results
   */
  private function formatTopResults(array $items): array
  {
    $out = [];
    foreach ($items as $item) {
      $out[] = [
        'position' => $item['position'] ?? null,
        'title' => $item['title'] ?? '',
        'link' => $item['link'] ?? '',
        'snippet' => $item['snippet'] ?? '',
        'source' => $item['source'] ?? '',
      ];
    }

    return $out;
  }

  public function proposeAction(Context $context): Action
  {
    return new Action('serp_analysis', [], $context, 'medium', 60);
  }

  public function getCapabilities(): array
  {
    return [
      'serp_analysis' => new ActorCapability('serp_analysis', 0.75, 'seo', 'competent', ['query', 'base_url', 'language']),
    ];
  }

  public function evaluateConfidence(Action $action): float
  {
    return Gpt::isSerpApiAvailable() ? 0.8 : 0.4;
  }

  public function receiveFeedback(Feedback $feedback): void
  {
    // No-op for now
  }

  public function getActorId(): string
  {
    return $this->actorId;
  }
}

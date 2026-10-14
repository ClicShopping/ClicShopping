<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

/**
 * UnifiedMetadataExtractor
 *
 * Runs the single unified LLM call that extracts translation/entity metadata from a
 * query: builds the prompt, calls Gpt, cleans and parses the JSON, and falls back to a
 * minimal analysis on failure. Extracted verbatim from UnifiedQueryAnalyzer::analyzeQuery
 * (Phase C); intent classification is handled separately by SequentialQueryClassifier.
 * Behaviour is unchanged.
 */
class UnifiedMetadataExtractor
{
  private SecurityLogger $logger;
  private mixed $language;
  private bool $debug;

  /**
   * @param SecurityLogger $logger Structured logger (shared with UnifiedQueryAnalyzer)
   * @param mixed $language Language registry object (defs loaded by the caller)
   * @param bool $debug Enable debug logging
   */
  public function __construct(SecurityLogger $logger, mixed $language, bool $debug = false)
  {
    $this->logger = $logger;
    $this->language = $language;
    $this->debug = $debug;
  }

  /**
   * Extract translation/entity metadata for a (pre-translated) query via the unified
   * LLM prompt. Returns a raw analysis array (language, translated_query, entity_type,
   * time_constraint, status_keywords, sub_queries); never the intent classification.
   *
   * @param string $query Pre-translated (English) query
   * @return array Raw analysis metadata
   */
  public function extract(string $query): array
  {
    // Build unified prompt with (potentially pre-translated) query
    // This is now ONLY used for translation and entity extraction, NOT classification
    $prompt = $this->buildUnifiedPrompt($query);
    
    // 🔍 DEBUG: Log prompt loading verification
    if ($this->debug) {
      error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Prompt Loading Verification:");
      error_log("  Prompt length: " . strlen($prompt) . " characters");
      error_log("  Prompt preview (first 200 chars): " . substr($prompt, 0, 200) . "...");
      error_log("  Query in prompt: " . (str_contains($prompt, $query) ? 'YES' : 'NO'));
    }

    // Single GPT call for everything
    // Use Gpt::getGptResponse() instead of non-existent complete() method

    $maxTokens = $this->metadataResponseMaxTokens($query);

    // 🔍 DEBUG: Log before GPT call
    if ($this->debug) {
      error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] Calling Gpt::getGptResponse():");
      error_log("  Max tokens: {$maxTokens}");
      error_log("  Temperature: 0.0");
      error_log("  Timestamp: " . date('Y-m-d H:i:s'));
    }

    $response = Gpt::getGptResponse(
      $prompt,
      $maxTokens,
      0.0  // temperature (deterministic for consistency)
    );

    // 🔍 DEBUG: Log GPT response
    if ($this->debug) {
      error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] GPT Response Received:");
      error_log("  Response length: " . strlen($response) . " characters");
      error_log("  Response type: " . gettype($response));
      error_log("  Response preview (first 500 chars): " . substr($response, 0, 500));
      error_log("  Full response: " . $response);
      error_log(sprintf($this->language->getDef('debug_gpt_response'), $response));
    }

    // Clean response (remove markdown code blocks if present)
    $cleanedResponse = $this->cleanJsonResponse($response);
    
    // 🔍 DEBUG: Log JSON cleaning
    if ($this->debug) {
      error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] JSON Response Cleaning:");
      error_log("  Original response length: " . strlen($response));
      error_log("  Cleaned response length: " . strlen($cleanedResponse));
      error_log("  Was cleaned: " . ($response !== $cleanedResponse ? 'YES' : 'NO'));
      error_log("  Cleaned response: " . $cleanedResponse);
    }

    // Parse JSON response
    $analysis = json_decode($cleanedResponse, true);
    $jsonError = json_last_error_msg();

    // 🔍 DEBUG: Log JSON parsing
    if ($this->debug) {
      error_log("[INFO : ANALYSE] [UnifiedQueryAnalyzer] JSON Parsing:");
      error_log("  JSON decode success: " . (json_last_error() === JSON_ERROR_NONE ? 'YES' : 'NO'));
      if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("  JSON error: " . json_last_error_msg());
        error_log("  JSON error code: " . json_last_error());
      } else {
        error_log("  Parsed array keys: " . implode(', ', array_keys($analysis ?? [])));
      }
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
      $this->logger->logStructured(
        'error',
        'UnifiedQueryAnalyzer',
        'json_parse_error',
        [
          'query' => $query,
          'response' => $response,
          'cleaned_response' => $cleanedResponse,
          'error' => json_last_error_msg()
        ]
      );

      // Fallback to default
      $analysis = null;
    }

    // ClassificationEngine provides: type, confidence, reasoning, sub_types
    // Unified analyzer provides: language, translated_query, entity_type, time_constraint, status_keywords, sub_queries
    if ($analysis === null) {
      $this->logger->logSecurityEvent(
        sprintf(
          'UnifiedMetadataExtractor: metadata analysis LOST, falling back to a minimal one '
          . '(json: %s, response %d c., budget %d tokens) — plan degrades to a single catch-all step',
          $jsonError,
          strlen($response),
          $maxTokens
        ),
        'warning'
      );

      // If unified analyzer failed, create minimal analysis with classification result
      // Use the pre-translated query as the translated_query
      $analysis = [
        'language' => 'en',
        'translated_query' => $query, // Use pre-translated query (already in English)
        'entity_type' => ['general'],
        'time_constraint' => 'none',
        'status_keywords' => [],
        'sub_queries' => []
      ];
      
      if ($this->debug) {
        error_log("[UnifiedQueryAnalyzer] Unified analyzer failed, using minimal analysis:");
        error_log("  Using pre-translated query: {$query}");
      }
    }
    
    // Ensure translated_query is not empty and is the correct query
    // If unified analyzer returned an empty or invalid translated_query, use the pre-translated query
    if (empty($analysis['translated_query']) || trim($analysis['translated_query']) === '') {
      $analysis['translated_query'] = $query;
      
      if ($this->debug) {
        error_log("[UnifiedQueryAnalyzer] Empty translated_query, using pre-translated query:");
        error_log("  Using: {$query}");
      }
    }

    return $analysis;
  }

  /**
   * Size the output-token budget for the metadata JSON to the question being analysed.
   *
   * A fixed 300 was reached exactly on a long question: the model had produced three correct
   * sub_queries and the JSON was cut mid-value, so the whole rich analysis was thrown away and the
   * plan fell back to one catch-all step. The response scales with the question — it echoes it in
   * `translated_query` and again, split, across `sub_queries` — so the budget must scale too.
   * Budget generously, as LLMWeightingEngine::weightingResponseMaxTokens() already does: surplus
   * tokens cost nothing (generation stops at the closing brace), a short cap costs the analysis.
   *
   * @param string $query Query being analysed
   * @return int Output-token budget
   */
  private function metadataResponseMaxTokens(string $query): int
  {
    $queryTokens = (int)ceil(mb_strlen($query) / 4);

    return min(4000, max(600, 300 + 4 * $queryTokens));
  }

  /**
   * Build unified prompt for language + intent detection
   *
   * This prompt asks GPT to analyze the query, translate it, and break down
   * complex analytic questions. Returns structured JSON with entity types,
   * time constraints, status keywords, and sub-queries.
   *
   * @param string $query User query
   * @return string GPT prompt
   */
  private function buildUnifiedPrompt(string $query): string
  {
    // Build prompt by concatenating sections. The reasoning skeleton is agnostic
    // (Agents/rag_unified_analyzer.txt) and carries an {{examples}} placeholder per
    // section; the concrete examples come from the active domain layer
    // (ecommerce/rag_unified_analyzer.txt). 

    $prompt = '';
    $prompt .= $this->language->getDef('unified_analyzer_prompt_header') . "\n\n";
    $prompt .= $this->resolveSection('unified_analyzer_prompt_anti_hallucination', 'unified_analyzer_examples_anti_hallucination') . "\n\n";
    $prompt .= $this->resolveSection('unified_analyzer_prompt_multi_temporal', 'unified_analyzer_examples_multi_temporal') . "\n\n";
    $prompt .= $this->resolveSection('unified_analyzer_prompt_compound', 'unified_analyzer_examples_compound') . "\n\n";
    $prompt .= $this->resolveSection('unified_analyzer_prompt_basic_analytics', 'unified_analyzer_examples_basic_analytics') . "\n\n";
    $prompt .= $this->language->getDef('unified_analyzer_prompt_classification') . "\n\n";
    $prompt .= $this->language->getDef('unified_analyzer_prompt_output_format') . "\n\n";
    $prompt .= $this->language->getDef('unified_analyzer_prompt_query_section') . "\n";
    $prompt .= $query . "\n\n";  // Insert the actual query here
    $prompt .= $this->resolveSection('unified_analyzer_prompt_final_instructions', 'unified_analyzer_examples_final_instructions') . "\n";

    if ($this->debug) {
      error_log("UnifiedQueryAnalyzer: Built prompt from language file sections");
      error_log("UnifiedQueryAnalyzer: Query to analyze: {$query}");
      error_log("UnifiedQueryAnalyzer: Total prompt length: " . strlen($prompt) . " characters");
      error_log("UnifiedQueryAnalyzer: Prompt contains query: " . (str_contains($prompt, $query) ? 'YES' : 'NO'));
    }

    return $prompt;
  }

  /**
   * Resolve one prompt section by injecting its domain examples into the agnostic
   * skeleton. The skeleton key holds an {{examples}} placeholder; the examples key
   * holds the domain-specific block. If the domain layer provides no examples, the
   * placeholder collapses to an empty string so no raw key name leaks into the prompt.
   *
   * @param string $skeletonKey Agnostic section key (contains {{examples}})
   * @param string $examplesKey Domain example key
   * @return string Resolved section text
   */
  private function resolveSection(string $skeletonKey, string $examplesKey): string
  {
    $examples = $this->language->getDef($examplesKey);

    // getDef() returns the key name verbatim when a definition is missing.
    if ($examples === $examplesKey) {
      $examples = '';
    }

    return $this->language->getDef($skeletonKey, ['examples' => $examples]);
  }

  /**
   * Clean JSON response by removing markdown code blocks
   *
   * GPT sometimes wraps JSON in markdown code blocks like:
   * ```json
   * { ... }
   * ```
   *
   * This method extracts the JSON content from such blocks.
   *
   * @param string $response Raw GPT response
   * @return string Cleaned JSON string
   */
  private function cleanJsonResponse(string $response): string
  {
    return HTMLOverrideCommon::extractJsonFromMarkdown($response);
  }
}

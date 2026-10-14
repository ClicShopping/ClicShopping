<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Response;

use ClicShopping\AI\Security\SecurityLogger;

/**
 * ResponseNormalizer
 *
 * Normalizes responses from different LLM models into a consistent format.
 * Handles model-specific response variations, JSON parsing, SQL extraction, and validation.
 *
 * SIMPLIFIED APPROACH (following Task 1.1 and 1.2 patterns):
 * - Handles all response types: analytics, semantic, web_search, hybrid
 * - Model-agnostic: works with any model's response format
 * - Robust JSON parsing with fallback mechanisms
 * - SQL extraction with security validation
 * - Response validation with detailed error reporting
 *
 * Response Types:
 * - analytics_response: Database queries with SQL and results
 * - semantic_results: Embedding-based searches with documents
 * - web_search_result: External web searches with URLs
 * - hybrid/mixed: Combination of multiple types
 *
 * Model-Specific Handling (classified from the model id alone — provider-agnostic):
 * - OpenAI GPT cloud series: dispatched by parsed major version (gpt-4 vs
 *   gpt-5-and-newer, so future gpt-6/gpt-7… need no code change)
 * - Other LLPhant cloud providers (OpenAI o-series, Anthropic, Gemini, Mistral):
 *   modern-model truncation handling
 * - LLPhant local backends (Ollama / LM Studio) and bare open-weights ids
 *   (qwen, phi-4, gpt-oss…): response field-name normalization
 */
class ResponseNormalizer
{
  /**
   * Security logger for tracking issues
   */
  private SecurityLogger $securityLogger;

  /**
   * Debug mode flag
   */
  private bool $debug;

  /**
   * Required fields for all responses
   */
  private const REQUIRED_FIELDS = [
    'response_type',
    'interpretation',
    'entity_type'
  ];

  /**
   * Valid response types
   */
  private const VALID_RESPONSE_TYPES = [
    'analytics_response',
    'analytics_results',
    'analytics',
    'semantic_results',
    'semantic',
    'web_search_result',
    'web_search_results',
    'web_search',
    'hybrid',
    'mixed'
  ];

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->securityLogger = new SecurityLogger();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';
  }

  /**
   * Normalize response from any model
   *
   * Takes a raw response from any LLM model and normalizes it into a consistent format.
   * Handles JSON parsing, field validation, SQL extraction, and model-specific variations.
   *
   * @param mixed $response Raw response from model (string, array, or object)
   * @param string $model Model name (e.g., 'gpt-4o', 'gpt-4', 'phi-4')
   * @return array Normalized response in standard format
   */
  public function normalize($response, string $model): array
  {
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "ResponseNormalizer: Normalizing response from model '$model'",
        'info'
      );
    }

    // Step 1: Parse JSON safely
    $parsed = $this->parseJsonSafe($response);

    // Step 2: Apply model-specific adjustments FIRST (before validation)
    // This ensures field mappings happen before defaults are added
    $adjusted = $this->applyModelSpecificAdjustments($parsed, $model);

    // Step 3: Handle ambiguous analytics responses (Task 3.3 fix)
    // Flatten interpretation_results to top level for consistent access
    $adjusted = $this->flattenAmbiguousResponses($adjusted);

    // Step 4: Validate and normalize structure
    $normalized = $this->validateResponse($adjusted);

    // Step 5: Extract SQL if needed (analytics responses)
    if ($this->isAnalyticsResponse($normalized)) {
      $normalized = $this->ensureSqlExtracted($normalized);
    }

    // Step 6: Normalize response type names
    $normalized = $this->normalizeResponseType($normalized);

    if ($this->debug) {
      $responseType = $normalized['response_type'] ?? 'unknown';
      $this->securityLogger->logSecurityEvent(
        "ResponseNormalizer: Successfully normalized response (Type: $responseType)",
        'info'
      );
    }

    return $normalized;
  }

  /**
   * Parse JSON response with fallback
   *
   * Attempts to parse JSON from various formats:
   * - Plain JSON string
   * - JSON wrapped in markdown code blocks (```json ... ```)
   * - JSON with extra whitespace or formatting
   * - Already parsed arrays
   *
   * @param mixed $response Response to parse
   * @return array Parsed data or error structure
   */
  public function parseJsonSafe($response): array
  {
    // If already an array, return it
    if (is_array($response)) {
      return $response;
    }

    // Convert to string
    $jsonString = is_string($response) ? trim($response) : (string)$response;

    // Remove markdown code blocks
    $jsonString = $this->removeMarkdownCodeBlocks($jsonString);

    // Try to parse JSON
    $decoded = json_decode($jsonString, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
      return $decoded;
    }

    // JSON parsing failed - log error and return fallback
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "JSON parsing failed: " . json_last_error_msg() . " | Response: " . substr($jsonString, 0, 200),
        'warning'
      );
    }

    // Return fallback structure with raw response
    return [
      'response_type' => 'semantic_results',
      'interpretation' => $this->extractTextFromFailedJson($jsonString),
      'entity_type' => 'general',
      'entity_id' => 0,
      'data_results' => [],
      'source_attribution' => [],
      'raw_response' => substr($jsonString, 0, 500),
      'parse_error' => json_last_error_msg()
    ];
  }

  /**
   * Extract SQL from response
   *
   * Extracts SQL queries from various locations in the response:
   * - Dedicated 'sql_query' field
   * - Embedded in 'interpretation' text
   * - In 'data_results' or 'results' fields
   *
   * @param string|array $response Response text or array
   * @return string|null SQL query or null if not found
   */
  public function extractSql($response): ?string
  {
    // If response is an array, check for sql_query field
    if (is_array($response)) {
      if (isset($response['sql_query']) && !empty($response['sql_query'])) {
        return trim($response['sql_query']);
      }

      // Check in interpretation field
      if (isset($response['interpretation'])) {
        $sql = $this->extractSqlFromText($response['interpretation']);
        if ($sql !== null) {
          return $sql;
        }
      }

      // Check in data_results
      if (isset($response['data_results']['sql_query'])) {
        return trim($response['data_results']['sql_query']);
      }

      return null;
    }

    // If response is a string, extract SQL from text
    return $this->extractSqlFromText((string)$response);
  }

  /**
   * Validate response format
   *
   * Validates that the response has all required fields and correct structure.
   * Adds default values for missing fields.
   *
   * @param array $response Response to validate
   * @return bool True if valid, false otherwise
   */
  public function validateResponse(array $response): array
  {
    $validated = $response;

    // Ensure required fields are present
    foreach (self::REQUIRED_FIELDS as $field) {
      if (!isset($validated[$field]) || empty($validated[$field])) {
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Missing required field: $field. Adding default value.",
            'warning'
          );
        }

        $validated[$field] = $this->getDefaultFieldValue($field);
      }
    }

    // Ensure additional standard fields
    $validated['entity_id'] = $validated['entity_id'] ?? 0;
    $validated['data_results'] = $validated['data_results'] ?? [];
    $validated['source_attribution'] = $validated['source_attribution'] ?? [];

    // Keep both 'results' and 'data_results' for backward compatibility
    // Many test scripts check for $response['results']
    if (isset($validated['results'])) {
      // Copy to data_results if not already set
      if (!isset($validated['data_results']) || empty($validated['data_results'])) {
        $validated['data_results'] = $validated['results'];
      }
      // Keep results field for backward compatibility
    } elseif (isset($validated['data_results']) && !empty($validated['data_results'])) {
      // If data_results exists but results doesn't, create results for backward compatibility
      $validated['results'] = $validated['data_results'];
    }

    return $validated;
  }

  /**
   * Remove markdown code blocks from JSON string
   *
   * @param string $jsonString JSON string potentially wrapped in markdown
   * @return string Cleaned JSON string
   */
  private function removeMarkdownCodeBlocks(string $jsonString): string
  {
    // Remove ```json ... ```
    if (str_starts_with($jsonString, '```json') && str_ends_with($jsonString, '```')) {
      $jsonString = substr($jsonString, 7, -3);
      return trim($jsonString);
    }

    // Remove ``` ... ```
    if (str_starts_with($jsonString, '```') && str_ends_with($jsonString, '```')) {
      $jsonString = substr($jsonString, 3, -3);
      return trim($jsonString);
    }

    // Remove single backticks
    $jsonString = str_replace('`', '', $jsonString);

    return trim($jsonString);
  }

  /**
   * Extract text from failed JSON parsing
   *
   * When JSON parsing fails, try to extract meaningful text from the response.
   *
   * @param string $response Failed JSON response
   * @return string Extracted text
   */
  private function extractTextFromFailedJson(string $response): string
  {
    // If response looks like plain text, return it
    if (!str_contains($response, '{') && !str_contains($response, '[')) {
      return substr($response, 0, 500);
    }

    // Try to extract interpretation field manually
    if (preg_match('/"interpretation"\s*:\s*"([^"]+)"/', $response, $matches)) {
      return $matches[1];
    }

    // Return truncated response
    return 'Response parsing error. Raw: ' . substr($response, 0, 200) . '...';
  }

  /**
   * Extract SQL from text
   *
   * Extracts SQL queries from text using pattern matching.
   * Supports SELECT, INSERT, UPDATE, DELETE statements.
   *
   * @param string $text Text containing SQL
   * @return string|null Extracted SQL or null
   */
  private function extractSqlFromText(string $text): ?string
  {
    // Pattern for SELECT queries (most common in analytics)
    if (preg_match('/SELECT\s+.*?FROM\s+.*?(?:WHERE.*?)?(?:ORDER BY.*?)?(?:LIMIT.*?)?(?:;|\n|$)/is', $text, $matches)) {
      return trim($matches[0]);
    }

    // Pattern for INSERT queries
    if (preg_match('/INSERT\s+INTO\s+.*?VALUES\s+.*?(?:;|\n|$)/is', $text, $matches)) {
      return trim($matches[0]);
    }

    // Pattern for UPDATE queries
    if (preg_match('/UPDATE\s+.*?SET\s+.*?(?:WHERE.*?)?(?:;|\n|$)/is', $text, $matches)) {
      return trim($matches[0]);
    }

    // Pattern for DELETE queries
    if (preg_match('/DELETE\s+FROM\s+.*?(?:WHERE.*?)?(?:;|\n|$)/is', $text, $matches)) {
      return trim($matches[0]);
    }

    return null;
  }

  /**
   * Get default value for missing field
   *
   * @param string $field Field name
   * @return mixed Default value
   */
  private function getDefaultFieldValue(string $field)
  {
    return match ($field) {
      'response_type' => 'semantic_results',
      'interpretation' => 'No interpretation provided',
      'entity_type' => 'general',
      'entity_id' => 0,
      'data_results' => [],
      'source_attribution' => [],
      default => ''
    };
  }

  /**
   * Check if response is analytics type
   *
   * @param array $response Response array
   * @return bool True if analytics response
   */
  private function isAnalyticsResponse(array $response): bool
  {
    $type = $response['response_type'] ?? '';
    return in_array($type, ['analytics_response', 'analytics_results', 'analytics'], true);
  }

  /**
   * Ensure SQL is extracted for analytics responses
   *
   * @param array $response Response array
   * @return array Response with SQL extracted
   */
  private function ensureSqlExtracted(array $response): array
  {
    // If SQL already present, return as-is
    if (isset($response['sql_query']) && !empty($response['sql_query'])) {
      return $response;
    }

    // Try to extract SQL from interpretation
    $sql = $this->extractSql($response);
    if ($sql !== null) {
      $response['sql_query'] = $sql;
    }

    return $response;
  }

  /**
   * Normalize response type names
   *
   * Converts various response type names to standard format:
   * - analytics_results, analytics → analytics_response
   * - semantic → semantic_results
   * - web_search, web_search_results → web_search_result
   *
   * @param array $response Response array
   * @return array Response with normalized type
   */
  private function normalizeResponseType(array $response): array
  {
    $type = $response['response_type'] ?? 'semantic_results';

    $normalized = match ($type) {
      'analytics', 'analytics_results' => 'analytics_response',
      'semantic' => 'semantic_results',
      'web_search', 'web_search_results' => 'web_search_result',
      default => $type
    };

    $response['response_type'] = $normalized;
    return $response;
  }

  /**
   * Flatten ambiguous analytics responses (Task 3.3 fix)
   *
   * When queries are ambiguous (e.g., "How many products?" could mean count OR sum),
   * the system returns multiple interpretations in interpretation_results[].
   * This method flattens the structure so results are accessible at the top level.
   *
   * Before:
   * {
   *   "type": "analytics_results_ambiguous",
   *   "interpretation_results": [
   *     {"sql_query": "...", "results": [...], "type": "count"},
   *     {"sql_query": "...", "results": [...], "type": "sum"}
   *   ]
   * }
   *
   * After:
   * {
   *   "type": "analytics_results_ambiguous",
   *   "sql_query": "...",  // Primary interpretation
   *   "results": [...],     // Primary results
   *   "all_interpretations": [...],  // All interpretations preserved
   *   "ambiguous": true
   * }
   *
   * @param array $response Response array
   * @return array Flattened response
   */
  private function flattenAmbiguousResponses(array $response): array
  {
    // Check if this is an ambiguous analytics response
    $type = $response['type'] ?? '';
    $hasInterpretations = isset($response['interpretation_results']) && 
                          is_array($response['interpretation_results']) && 
                          !empty($response['interpretation_results']);

    if ($type === 'analytics_results_ambiguous' && $hasInterpretations) {
      if ($this->debug) {
        $count = count($response['interpretation_results']);
        $this->securityLogger->logSecurityEvent(
          "ResponseNormalizer: Flattening ambiguous response with $count interpretations",
          'info'
        );
      }

      // Use first interpretation as primary result
      $primary = $response['interpretation_results'][0];

      // Flatten to top level
      $response['sql_query'] = $primary['sql_query'] ?? null;
      $response['results'] = $primary['results'] ?? [];
      $response['primary_interpretation_type'] = $primary['type'] ?? 'unknown';
      $response['primary_interpretation_label'] = $primary['label'] ?? '';

      // Keep all interpretations for reference
      $response['all_interpretations'] = $response['interpretation_results'];

      // Mark as ambiguous
      $response['ambiguous'] = true;
      $response['ambiguity_type'] = $response['ambiguity_type'] ?? 'multiple_interpretations';

      if ($this->debug) {
        $primaryType = $response['primary_interpretation_type'];
        $resultCount = count($response['results']);
        $this->securityLogger->logSecurityEvent(
          "ResponseNormalizer: Flattened to primary type '$primaryType' with $resultCount results",
          'info'
        );
      }
    }

    return $response;
  }

  /**
   * Apply model-specific adjustments
   *
   * Classifies the model from its identifier alone (this class is provider-agnostic
   * and must not depend on the ChatGpt App catalog) and applies the matching
   * post-processing:
   *  - OpenAI GPT cloud models, dispatched by the parsed major version (gpt-4 vs
   *    gpt-5-and-newer, so future gpt-6/gpt-7… are covered without a code change);
   *  - other LLPhant cloud providers (OpenAI o-series, Anthropic, Gemini, Mistral)
   *    share the modern-model truncation handling;
   *  - LLPhant local backends (Ollama / LM Studio) and bare open-weights ids
   *    (qwen, phi-4, gpt-oss…) get response field-name normalization.
   *
   * @param array $response Response array
   * @param string $model Model name
   * @return array Adjusted response
   */
  private function applyModelSpecificAdjustments(array $response, string $model): array
  {
    $modelId = strtolower(trim($model));

    // LLPhant local backends first: Ollama-tagged ids ("qwen:7b", "llama3:latest")
    // and LM Studio ("openai/…"). A ':' tag never appears in a cloud model id.
    if ($this->isLocalModel($modelId)) {
      return $this->handleLocalModelVariations($response);
    }

    // OpenAI GPT cloud series — dispatch by the numeric major version parsed from the id.
    $gptMajorVersion = $this->gptMajorVersion($modelId);

    if ($gptMajorVersion === 4) {
      return $this->handleGpt4Truncation($response);
    }

    if ($gptMajorVersion !== null && $gptMajorVersion >= 5) {
      return $this->handleModernModelTruncation($response);
    }

    // Other modern cloud providers wired through LLPhant (o-series, Anthropic, Gemini, Mistral).
    if ($this->isModernCloudModel($modelId)) {
      return $this->handleModernModelTruncation($response);
    }

    // Unknown / bare open-weights ids (qwen, phi-4, gpt-oss…) run locally: normalize fields.
    return $this->handleLocalModelVariations($response);
  }

  /**
   * Whether the model runs on a LLPhant local backend (Ollama or LM Studio).
   *
   * Ollama model ids carry a "name:tag" suffix (e.g. "qwen:7b", "llama3:latest") or an
   * explicit "ollama" prefix; LM Studio exposes OpenAI-compatible ids prefixed "openai/".
   * Cloud model ids never contain a ':' tag.
   *
   * @param string $modelId Lower-cased model identifier
   * @return bool
   */
  private function isLocalModel(string $modelId): bool
  {
    return str_contains($modelId, ':')
        || str_starts_with($modelId, 'ollama')
        || str_starts_with($modelId, 'openai/');
  }

  /**
   * Parses the numeric major version of an OpenAI GPT identifier.
   *
   * "gpt-4" → 4, "gpt-4.1-mini" → 4, "gpt-5-mini" → 5, "gpt-6" → 6. Returns null for
   * non-numeric GPT names such as the local "gpt-oss".
   *
   * @param string $modelId Lower-cased model identifier
   * @return int|null Major version, or null when the id is not a numeric GPT model
   */
  private function gptMajorVersion(string $modelId): ?int
  {
    if (preg_match('/^gpt-(\d+)/', $modelId, $matches) === 1) {
      return (int)$matches[1];
    }

    return null;
  }

  /**
   * Whether the model is a modern cloud model wired through LLPhant, other than the
   * GPT series handled via {@see gptMajorVersion()} (OpenAI o-series, Anthropic, Gemini,
   * Mistral). Local Mistral ids ("mistral:7b") are excluded upstream by {@see isLocalModel()}.
   *
   * @param string $modelId Lower-cased model identifier
   * @return bool
   */
  private function isModernCloudModel(string $modelId): bool
  {
    return str_starts_with($modelId, 'o1')
        || str_starts_with($modelId, 'o3')
        || str_starts_with($modelId, 'claude')
        || str_starts_with($modelId, 'anth')
        || str_starts_with($modelId, 'gemini')
        || str_starts_with($modelId, 'google')
        || str_starts_with($modelId, 'mistral');
  }

  /**
   * Handle GPT-4 truncation issues
   *
   * GPT-4 has 8K context limit, so responses may be truncated.
   * Add indicators when truncation is detected.
   *
   * @param array $response Response array
   * @return array Adjusted response
   */
  private function handleGpt4Truncation(array $response): array
  {
    // Check if interpretation seems truncated (ends abruptly)
    if (isset($response['interpretation'])) {
      $interpretation = $response['interpretation'];
      $lastChar = substr($interpretation, -1);

      // If doesn't end with punctuation, may be truncated
      if (!in_array($lastChar, ['.', '!', '?', ')', ']', '}'], true)) {
        $response['interpretation'] .= ' [Response may be truncated due to context limit]';
        $response['truncated'] = true;
      }
    }

    return $response;
  }

  /**
   * Handle truncation for modern cloud models (GPT-5 and newer, OpenAI o-series,
   * Anthropic, Gemini, Mistral).
   *
   * These models have large but finite context windows; add an indicator when the
   * response looks truncated.
   *
   * @param array $response Response array
   * @return array Adjusted response
   */
  private function handleModernModelTruncation(array $response): array
  {
    if (isset($response['interpretation'])) {
      $interpretation = $response['interpretation'];
      $lastChar = substr($interpretation, -1);

      if (!in_array($lastChar, ['.', '!', '?', ')', ']', '}'], true)) {
        $response['interpretation'] .= ' [Response may be truncated due to context limit]';
        $response['truncated'] = true;
      }
    }

    return $response;
  }

  /**
   * Handle local model variations
   *
   * Local models (GPT-OSS, Phi-4) may have different formatting.
   * Normalize their responses to match standard format.
   *
   * @param array $response Response array
   * @return array Adjusted response
   */
  private function handleLocalModelVariations(array $response): array
  {
    // Local models may use different field names
    // Map common variations to standard names
    $fieldMappings = [
      'answer' => 'interpretation',
      'response' => 'interpretation',
      'query' => 'question',
      'type' => 'response_type',
      'entity' => 'entity_type'
    ];

    foreach ($fieldMappings as $oldField => $newField) {
      if (isset($response[$oldField]) && !isset($response[$newField])) {
        $response[$newField] = $response[$oldField];
        unset($response[$oldField]);
      }
    }

    return $response;
  }
}

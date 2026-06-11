<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Processor;


use ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\ResultFormatter;
use ClicShopping\AI\DomainsAI\Shared\Helper\AgentResponseHelper;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * ResultSynthesizer - Synthesizes results from multiple sub-queries
 *
 * Responsibilities:
 * - Determine synthesis type based on query classification
 * - Extract from embedding results for semantic queries (no LLM generation)
 * - Format SQL results in tables for analytics queries
 * - Present external sources with citations for web_search queries
 * - Combine results with source attribution for hybrid queries using LLM
 * - Aggregate entity information from sub-queries
 *
 * Requirements:
 * - REQ-4.1: Determine synthesis type based on query classification
 * - REQ-4.2: Semantic synthesis (extract from embeddings only)
 * - REQ-4.3: Analytics synthesis (format SQL results)
 * - REQ-4.4: Web_search synthesis (present sources with citations)
 * - REQ-4.5: Hybrid synthesis (combine with LLM)
 * - REQ-4.6: Aggregate entity information from sub-queries
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubHybridQueryProcessor
 * @since 2025-12-14
 */

class ResultSynthesizer extends BaseQueryProcessor
{
  /**
   * @var PromptValidator Validator for LLM prompts
   */
  private PromptValidator $promptValidator;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    parent::__construct($debug, 'ResultSynthesizer');
    $this->promptValidator = new PromptValidator($debug);
  }

  /**
   * Process: Synthesize results from multiple sub-queries
   */
  public function process($input, array $context = []): array
  {
    if (!$this->validate($input)) {
      return $this->handleError("Invalid input for synthesis", null, ['type' => 'error', 'message' => 'Invalid input for result synthesis']);
    }

    $subQueryResults = $input;
    $originalQuery = $context['original_query'] ?? '';

    try {
      if ($this->debug) $this->logInfo("Synthesizing results for query: {$originalQuery}", ['count' => count($subQueryResults)]);

      // Determine synthesis type
      $synthesisType = $this->determineSynthesisType($subQueryResults);
      if ($this->debug) $this->logInfo("Synthesis type determined: {$synthesisType}");

      // Extract data from results
      list($combinedText, $combinedData, $sources) = $this->extractResultData($subQueryResults);

      // Synthesize based on type
      $textResponse = $this->synthesizeByType($synthesisType, $subQueryResults, $combinedText, $combinedData, $sources, $originalQuery);

      // Aggregate entity from sub-queries
      $aggregatedEntity = $this->aggregateEntityFromSubQueries($subQueryResults);

      // For single result, return it directly with proper format
      if (count($subQueryResults) === 1) {
        return $this->formatSingleResult($subQueryResults[0], $synthesisType, $aggregatedEntity);
      }

      // For multiple results, use hybrid response helper
      return $this->formatHybridResult($originalQuery, $subQueryResults, $textResponse, $synthesisType, $aggregatedEntity, $context);

    } catch (\Exception $e) {
      return $this->handleError("Error synthesizing results", $e, ['type' => 'error', 'message' => 'Failed to synthesize results: ' . $e->getMessage()]);
    }
  }

  /**
   * Extract data from sub-query results
   *
   */
  private function extractResultData(array $subQueryResults): array
  {
    $combinedText = [];
    $combinedData = [];
    $sources = [];
    $addedContentHashes = []; // Track added content to prevent duplicates

    foreach ($subQueryResults as $index => $result) {
      // Extract text_response if available (preferred for synthesis)
      if (isset($result['text_response']) && !empty($result['text_response'])) {
        $hash = md5($result['text_response']);
        if (!isset($addedContentHashes[$hash])) {
          $combinedText[] = $result['text_response'];
          $addedContentHashes[$hash] = true;
          
          if ($this->debug) {
            $this->logInfo("Added text_response to combined text", ['hash' => substr($hash, 0, 8), 'length' => strlen($result['text_response'])]);
          }
        } else {
          if ($this->debug) {
            $this->logInfo("Skipped duplicate text_response", ['hash' => substr($hash, 0, 8)]);
          }
        }
      }
      
      // Extract result data
      if (isset($result['result'])) {
        if (is_string($result['result'])) {
          // Check if this content is already added (by hash)
          $hash = md5($result['result']);
          if (!isset($addedContentHashes[$hash])) {
            $combinedText[] = $result['result'];
            $addedContentHashes[$hash] = true;
            
            if ($this->debug) {
              $this->logInfo("Added result string to combined text", ['hash' => substr($hash, 0, 8), 'length' => strlen($result['result'])]);
            }
          } else {
            if ($this->debug) {
              $this->logInfo("Skipped duplicate result string", ['hash' => substr($hash, 0, 8)]);
            }
          }
        } elseif (is_array($result['result'])) {
          $combinedData[] = $result['result'];
        }
      }
      
      // Extract sources
      if (isset($result['sources'])) {
        $sources = array_merge($sources, $result['sources']);
      }
    }

    return [$combinedText, $combinedData, $sources];
  }

  /**
   * Format single result with entity metadata
   */
  private function formatSingleResult(array $result, string $synthesisType, ?array $aggregatedEntity): array
  {
    // Ensure standardized format
    if (!isset($result['source_attribution'])) {
      $result['source_attribution'] = [
        'primary_source' => ucfirst($synthesisType),
        'icon' => $this->getIconForType($synthesisType),
        'details' => [],
        'confidence' => 0.7,
      ];
    }

    if (!isset($result['metadata'])) {
      $result['metadata'] = ['timestamp' => date('c'), 'execution_time' => 0];
    }

    // Add aggregated entity
    if ($aggregatedEntity !== null) {
      $result['_step_entity_metadata'] = $aggregatedEntity;
      $result['entity_id'] = $aggregatedEntity['entity_id'];
      $result['entity_type'] = $aggregatedEntity['entity_type'];
      if ($this->debug) $this->logInfo("Added entity to single result", ['type' => $aggregatedEntity['entity_type'], 'id' => $aggregatedEntity['entity_id']]);
    }

    return $result;
  }

  /**
   * Format hybrid result with entity metadata
   */
  private function formatHybridResult(string $originalQuery, array $subQueryResults, string $textResponse, string $synthesisType, ?array $aggregatedEntity, array $context): array
  {
    $hybridResponse = AgentResponseHelper::createHybridResponse(
      $originalQuery,
      $subQueryResults,
      $textResponse,
      [
        'execution_time' => microtime(true) - ($context['start_time'] ?? microtime(true)),
        'synthesis_type' => $synthesisType,
      ]
    );

    // Add aggregated entity
    if ($aggregatedEntity !== null) {
      $hybridResponse['_step_entity_metadata'] = $aggregatedEntity;
      $hybridResponse['entity_id'] = $aggregatedEntity['entity_id'];
      $hybridResponse['entity_type'] = $aggregatedEntity['entity_type'];
      if ($this->debug) $this->logInfo("Added entity to hybrid result", ['type' => $aggregatedEntity['entity_type'], 'id' => $aggregatedEntity['entity_id']]);
    }

    return $hybridResponse;
  }

  /**
   * Validate input
   */
  public function validate($input): bool
  {
    return is_array($input) && !empty($input);
  }

  /**
   * Determine synthesis type based on sub-query results
   */
  private function determineSynthesisType(array $subQueryResults): string
  {
    if (count($subQueryResults) === 1) return $subQueryResults[0]['type'] ?? 'semantic';
    $types = array_unique(array_column($subQueryResults, 'type'));
    return count($types) === 1 ? $types[0] : 'hybrid';
  }

  /**
   * Synthesize results based on type
   * 
   * ✅ FIX (2026-01-08): For multi-temporal queries with multiple analytics sub-queries,
   * we should use the already-formatted text_response from each sub-query (in combinedText)
   * instead of re-formatting the raw data. This ensures the formatted HTML content is preserved.
   */
  private function synthesizeByType(string $synthesisType, array $subQueryResults, array $combinedText, array $combinedData, array $sources, string $originalQuery): string
  {
    // ✅ FIX (2026-01-08): If we have multiple sub-queries with formatted text_response,
    // always use hybrid synthesis to combine them (even if all are analytics)
    if (count($subQueryResults) > 1 && !empty($combinedText)) {
      if ($this->debug) {
        $this->logInfo("Multiple sub-queries with combinedText, using hybrid synthesis");
      }
      return $this->synthesizeHybrid($combinedText, $originalQuery);
    }
    
    return match($synthesisType) {
      'semantic' => $this->synthesizeSemantic($subQueryResults, $combinedText),
      'analytics' => $this->synthesizeAnalytics($combinedData),
      'web_search' => $this->synthesizeWebSearch($subQueryResults, $combinedText, $sources),
      'hybrid' => $this->synthesizeHybrid($combinedText, $originalQuery),
      default => implode("\n\n", $combinedText)
    };
  }

  /**
   * Synthesize semantic results (extract from embeddings only, no LLM generation)
   * REQ-4.2: Extract from embedding results only
   */
  private function synthesizeSemantic(array $subQueryResults, array $combinedText): string
  {
    // Extract content from embedding results
    $semanticTexts = [];
    foreach ($subQueryResults as $result) {
      if (isset($result['result']) && is_array($result['result'])) {
        foreach ($result['result'] as $embeddingResult) {
          if (isset($embeddingResult['content'])) {
            $score = isset($embeddingResult['score']) ? round($embeddingResult['score'] * 100, 2) : 0;
            $semanticTexts[] = $embeddingResult['content'] . "\n(Relevance: {$score}%)";
          }
        }
      }
    }

    if (!empty($semanticTexts)) {
      if ($this->debug) {
        $this->logInfo("Semantic synthesis: Extracted text from embeddings without LLM generation");
      }
      return implode("\n\n", $semanticTexts);
    }

    return !empty($combinedText) ? implode("\n\n", $combinedText) : "No semantic results found in the knowledge base.";
  }

  /**
   * Synthesize analytics results (format SQL results in tables)
   * REQ-4.3: Format SQL results in tables with actual data
   */
  private function synthesizeAnalytics(array $combinedData): string
  {
    if (!empty($combinedData)) {
      if ($this->debug) $this->logInfo("Analytics synthesis: Formatted SQL results in tables");
      return ResultFormatter::formatAnalyticsAsText($combinedData);
    }
    return "No analytics data available.";
  }

  /**
   * Synthesize web search results (present sources with citations)
   * REQ-4.4: Present external sources with citations
   */
  private function synthesizeWebSearch(array $subQueryResults, array $combinedText, array $sources): string
  {
    // Extract sources from result data
    $webSources = [];
    foreach ($subQueryResults as $result) {
      if (isset($result['result']) && is_array($result['result'])) {
        foreach ($result['result'] as $item) {
          if (isset($item['url']) && !empty($item['url'])) {
            $webSources[] = [
              'title' => $item['title'] ?? 'Source',
              'url' => $item['url'],
              'snippet' => $item['snippet'] ?? '',
            ];
          }
        }
      }
    }

    if (!empty($combinedText) || !empty($webSources)) {
      if ($this->debug) $this->logInfo("Web search synthesis: Formatted sources", ['count' => count($webSources)]);
      return ResultFormatter::formatWebSearchAsText($combinedText, $webSources);
    }
    return "No web search results found.";
  }

  /**
   * Synthesize hybrid results (combine without LLM)
   * Combine results with source attribution
   *
   * The sub-queries already have formatted text_response fields, so we just
   * need to combine them with clear section headers. No LLM call needed.
   */
  private function synthesizeHybrid(array $combinedText, string $originalQuery): string
  {
    if (empty($combinedText)) return "Results processed successfully.";

    // ✅ Simply concatenate the formatted text responses with separators
    // Each text_response is already formatted by ResultFormatter
    if ($this->debug) {
      $this->logInfo("Hybrid synthesis: Combining " . count($combinedText) . " formatted responses");
    }

    return implode("\n\n---\n\n", $combinedText);
  }

  /**
   * Aggregate entity information from sub-queries
   * REQ-4.6: Priority order: analytics > web_search > semantic
   */
  private function aggregateEntityFromSubQueries(array $subQueryResults): ?array
  {
    $priorityOrder = ['analytics', 'web_search', 'semantic'];

    foreach ($priorityOrder as $priorityType) {
      foreach ($subQueryResults as $result) {
        if (($result['type'] ?? '') !== $priorityType) continue;

        // Check for _step_entity_metadata
        if (isset($result['_step_entity_metadata'])) {
          $entityMeta = $result['_step_entity_metadata'];
          if (isset($entityMeta['entity_id']) && $entityMeta['entity_id'] > 0 && isset($entityMeta['entity_type'])) {
            if ($this->debug) $this->logInfo("Found entity in {$priorityType}", ['type' => $entityMeta['entity_type'], 'id' => $entityMeta['entity_id']]);
            return [
              'entity_id' => $entityMeta['entity_id'],
              'entity_type' => $entityMeta['entity_type'],
              'source' => 'hybrid_aggregated_from_' . $priorityType,
              'original_source' => $entityMeta['source'] ?? 'unknown'
            ];
          }
        }

        // Check for entity at top level
        if (isset($result['entity_id']) && $result['entity_id'] > 0 && isset($result['entity_type'])) {
          if ($this->debug) $this->logInfo("Found entity at top level in {$priorityType}", ['type' => $result['entity_type'], 'id' => $result['entity_id']]);
          return [
            'entity_id' => $result['entity_id'],
            'entity_type' => $result['entity_type'],
            'source' => 'hybrid_aggregated_from_' . $priorityType,
            'original_source' => 'top_level'
          ];
        }
      }
    }

    if ($this->debug) $this->logInfo("No entity found in any sub-query");
    return null;
  }

  /**
   * Get icon for query type
   *
   * @param string $type Query type
   * @return string Icon emoji
   */
  private function getIconForType(string $type): string
  {
    $icons = [
      'analytics' => '📊',
      'semantic' => '📚',
      'web_search' => '🌐',
      'hybrid' => '🔀',
    ];

    return $icons[$type] ?? '🤖';
  }

  // ================================================================================
  // TEMPORAL LABELING METHODS (Task 6 - Multi-Temporal Query Detection)
  // Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6
  // ================================================================================

  /**
   * Format temporal label based on period type and value
   * 
   * Converts raw temporal values (1, 2, 3...) into human-readable labels
   * in the user's language (January, Q1, Semester 1, etc.)
   * 
   * Requirements: 7.2, 7.3, 7.4, 7.5
   * 
   * @param string $temporalPeriod The temporal period type (month, quarter, semester, year, week, day)
   * @param mixed $value The raw value (1-12 for months, 1-4 for quarters, etc.)
   * @param string $languageCode The user's language code (en, fr, es, de)
   * @return string The formatted temporal label
   */
  public function formatTemporalLabel(string $temporalPeriod, $value, string $languageCode = 'en'): string
  {
    if ($this->debug) {
      $this->logInfo("Formatting temporal label", [
        'period' => $temporalPeriod,
        'value' => $value,
        'language' => $languageCode
      ]);
    }

    $label = match (strtolower($temporalPeriod)) {
      'month' => $this->formatMonthLabel($value, $languageCode),
      'quarter' => $this->formatQuarterLabel($value, $languageCode),
      'semester' => $this->formatSemesterLabel($value, $languageCode),
      'year' => $this->formatYearLabel($value),
      'week' => $this->formatWeekLabel($value, $languageCode),
      'day' => $this->formatDayLabel($value, $languageCode),
      default => $this->formatCustomPeriodLabel($temporalPeriod, $value, $languageCode)
    };

    if ($this->debug) {
      $this->logInfo("Temporal label formatted", ['label' => $label]);
    }

    return $label;
  }

  /**
   * Format month label (January, February, etc.)
   * 
   * @param mixed $value Month number (1-12) or month name
   * @param string $languageCode Language code
   * @return string Formatted month name
   */
  private function formatMonthLabel($value, string $languageCode): string
  {
    // If already a string (month name), return as-is or translate
    if (is_string($value) && !is_numeric($value)) {
      return $this->translateMonthName($value, $languageCode);
    }

    $monthNumber = (int)$value;
    if ($monthNumber < 1 || $monthNumber > 12) {
      return (string)$value;
    }

    $monthNames = $this->getMonthNames($languageCode);
    return $monthNames[$monthNumber - 1] ?? (string)$value;
  }

  /**
   * Get month names for a language
   * 
   * @param string $languageCode Language code
   * @return array Array of month names (0-indexed)
   */
  private function getMonthNames(string $languageCode): array
  {
    $months = [
      'en' => ['January', 'February', 'March', 'April', 'May', 'June', 
               'July', 'August', 'September', 'October', 'November', 'December'],
      'fr' => ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
               'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
      'es' => ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
      'de' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
               'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
    ];

    return $months[strtolower($languageCode)] ?? $months['en'];
  }

  /**
   * Translate month name from English to target language
   * 
   * @param string $monthName English month name
   * @param string $languageCode Target language code
   * @return string Translated month name
   */
  private function translateMonthName(string $monthName, string $languageCode): string
  {
    $englishMonths = ['January', 'February', 'March', 'April', 'May', 'June',
                      'July', 'August', 'September', 'October', 'November', 'December'];
    
    $monthIndex = array_search(ucfirst(strtolower($monthName)), $englishMonths);
    if ($monthIndex === false) {
      return $monthName; // Return as-is if not found
    }

    $targetMonths = $this->getMonthNames($languageCode);
    return $targetMonths[$monthIndex] ?? $monthName;
  }

  /**
   * Format quarter label (Q1, Q2, Q3, Q4)
   * 
   * @param mixed $value Quarter number (1-4)
   * @param string $languageCode Language code
   * @return string Formatted quarter label
   */
  private function formatQuarterLabel($value, string $languageCode): string
  {
    $quarterNumber = (int)$value;
    if ($quarterNumber < 1 || $quarterNumber > 4) {
      return (string)$value;
    }

    $quarterPrefixes = [
      'en' => 'Q',
      'fr' => 'T',  // Trimestre
      'es' => 'T',  // Trimestre
      'de' => 'Q',  // Quartal
    ];

    $prefix = $quarterPrefixes[strtolower($languageCode)] ?? 'Q';
    return $prefix . $quarterNumber;
  }

  /**
   * Format semester label (Semester 1, Semester 2)
   * 
   * @param mixed $value Semester number (1-2)
   * @param string $languageCode Language code
   * @return string Formatted semester label
   */
  private function formatSemesterLabel($value, string $languageCode): string
  {
    $semesterNumber = (int)$value;
    if ($semesterNumber < 1 || $semesterNumber > 2) {
      return (string)$value;
    }

    $semesterLabels = [
      'en' => ['Semester 1', 'Semester 2'],
      'fr' => ['Semestre 1', 'Semestre 2'],
      'es' => ['Semestre 1', 'Semestre 2'],
      'de' => ['Semester 1', 'Semester 2'],
    ];

    $labels = $semesterLabels[strtolower($languageCode)] ?? $semesterLabels['en'];
    return $labels[$semesterNumber - 1] ?? (string)$value;
  }

  /**
   * Format year label (2025, 2026, etc.)
   * 
   * @param mixed $value Year value
   * @return string Formatted year label
   */
  private function formatYearLabel($value): string
  {
    return (string)$value;
  }

  /**
   * Format week label (Week 1, Week 2, etc.)
   * 
   * @param mixed $value Week number
   * @param string $languageCode Language code
   * @return string Formatted week label
   */
  private function formatWeekLabel($value, string $languageCode): string
  {
    $weekNumber = (int)$value;

    $weekLabels = [
      'en' => 'Week',
      'fr' => 'Semaine',
      'es' => 'Semana',
      'de' => 'Woche',
    ];

    $label = $weekLabels[strtolower($languageCode)] ?? 'Week';
    return "{$label} {$weekNumber}";
  }

  /**
   * Format day label (2025-01-15, etc.)
   * 
   * @param mixed $value Date value (string or timestamp)
   * @param string $languageCode Language code
   * @return string Formatted day label
   */
  private function formatDayLabel($value, string $languageCode): string
  {
    // If already a formatted date string, return as-is
    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
      return $value;
    }

    // If timestamp, format it
    if (is_numeric($value)) {
      $dateFormats = [
        'en' => 'Y-m-d',
        'fr' => 'd/m/Y',
        'es' => 'd/m/Y',
        'de' => 'd.m.Y',
      ];
      $format = $dateFormats[strtolower($languageCode)] ?? 'Y-m-d';
      return date($format, (int)$value);
    }

    return (string)$value;
  }

  /**
   * Format custom period label (every 4 months, etc.)
   * 
   * @param string $temporalPeriod Custom period type
   * @param mixed $value Period value
   * @param string $languageCode Language code
   * @return string Formatted custom period label
   */
  private function formatCustomPeriodLabel(string $temporalPeriod, $value, string $languageCode): string
  {
    // For custom periods, return a descriptive label
    $periodLabels = [
      'en' => "Period {$value}",
      'fr' => "Période {$value}",
      'es' => "Período {$value}",
      'de' => "Zeitraum {$value}",
    ];

    return $periodLabels[strtolower($languageCode)] ?? "Period {$value}";
  }

  /**
   * Get section header for temporal period
   * 
   * Returns a localized section header for grouping results by temporal period
   * 
   * Requirements: 7.2, 7.3
   * 
   * @param string $temporalPeriod The temporal period type
   * @param string $languageCode The user's language code
   * @return string The section header
   */
  public function getTemporalSectionHeader(string $temporalPeriod, string $languageCode = 'en'): string
  {
    $headers = [
      'month' => [
        'en' => 'Monthly Results',
        'fr' => 'Résultats mensuels',
        'es' => 'Resultados mensuales',
        'de' => 'Monatliche Ergebnisse',
      ],
      'quarter' => [
        'en' => 'Quarterly Results',
        'fr' => 'Résultats trimestriels',
        'es' => 'Resultados trimestrales',
        'de' => 'Quartalsergebnisse',
      ],
      'semester' => [
        'en' => 'Semester Results',
        'fr' => 'Résultats semestriels',
        'es' => 'Resultados semestrales',
        'de' => 'Semesterergebnisse',
      ],
      'year' => [
        'en' => 'Yearly Results',
        'fr' => 'Résultats annuels',
        'es' => 'Resultados anuales',
        'de' => 'Jährliche Ergebnisse',
      ],
      'week' => [
        'en' => 'Weekly Results',
        'fr' => 'Résultats hebdomadaires',
        'es' => 'Resultados semanales',
        'de' => 'Wöchentliche Ergebnisse',
      ],
      'day' => [
        'en' => 'Daily Results',
        'fr' => 'Résultats quotidiens',
        'es' => 'Resultados diarios',
        'de' => 'Tägliche Ergebnisse',
      ],
    ];

    $periodHeaders = $headers[strtolower($temporalPeriod)] ?? null;
    if ($periodHeaders === null) {
      // Custom period
      $customHeaders = [
        'en' => 'Custom Period Results',
        'fr' => 'Résultats par période personnalisée',
        'es' => 'Resultados por período personalizado',
        'de' => 'Ergebnisse nach benutzerdefiniertem Zeitraum',
      ];
      return $customHeaders[strtolower($languageCode)] ?? $customHeaders['en'];
    }

    return $periodHeaders[strtolower($languageCode)] ?? $periodHeaders['en'];
  }

  /**
   * Synthesize multi-temporal results with proper labeling
   * 
   * Combines results from multiple temporal sub-queries into a single response
   * with clear section headers and temporal labels in the user's language.
   * 
   * Requirements: 7.1, 7.2, 7.3, 7.6
   * 
   * @param array $subQueryResults Array of sub-query results with temporal_period metadata
   * @param string $languageCode The user's language code
   * @return string Combined text response with temporal sections
   */
  public function synthesizeMultiTemporalResults(array $subQueryResults, string $languageCode = 'en'): string
  {
    if (empty($subQueryResults)) {
      return "No results available.";
    }

    if ($this->debug) {
      $this->logInfo("Synthesizing multi-temporal results", [
        'sub_query_count' => count($subQueryResults),
        'language' => $languageCode
      ]);
    }

    $sections = [];

    // Process each sub-query result in order (preserving user's requested order)
    // Requirements: 7.6 - Preserve result order as requested by user
    foreach ($subQueryResults as $index => $result) {
      $temporalPeriod = $result['temporal_period'] ?? null;
      
      if ($temporalPeriod === null) {
        // No temporal period, just add the text response
        if (isset($result['text_response']) && !empty($result['text_response'])) {
          $sections[] = $result['text_response'];
        }
        continue;
      }

      // Get section header
      $sectionHeader = $this->getTemporalSectionHeader($temporalPeriod, $languageCode);
      
      // Get the content
      $content = $result['text_response'] ?? '';
      if (empty($content) && isset($result['result'])) {
        if (is_string($result['result'])) {
          $content = $result['result'];
        } elseif (is_array($result['result']) && isset($result['result']['rows'])) {
          // Format analytics data with temporal labels
          $content = $this->formatTemporalAnalyticsData($result['result']['rows'], $temporalPeriod, $languageCode);
        }
      }

      if (!empty($content)) {
        // Add section with header
        $sections[] = "📊 **{$sectionHeader}**\n\n{$content}";
      }
    }

    if (empty($sections)) {
      return "No temporal results available.";
    }

    $synthesizedResult = implode("\n\n---\n\n", $sections);

    if ($this->debug) {
      $this->logInfo("Multi-temporal synthesis complete", [
        'section_count' => count($sections),
        'result_length' => strlen($synthesizedResult)
      ]);
    }

    return $synthesizedResult;
  }

  /**
   * Format analytics data with temporal labels
   * 
   * @param array $rows Data rows from analytics query
   * @param string $temporalPeriod The temporal period type
   * @param string $languageCode The user's language code
   * @return string Formatted data with temporal labels
   */
  private function formatTemporalAnalyticsData(array $rows, string $temporalPeriod, string $languageCode): string
  {
    if (empty($rows)) {
      return "No data available.";
    }

    $formattedRows = [];
    foreach ($rows as $row) {
      // Find the period column (could be 'period', 'month', 'quarter', etc.)
      $periodValue = $row['period'] ?? $row[$temporalPeriod] ?? $row['MONTH'] ?? $row['QUARTER'] ?? null;
      
      if ($periodValue !== null) {
        // Format the period label
        $formattedPeriod = $this->formatTemporalLabel($temporalPeriod, $periodValue, $languageCode);
        
        // Get the value column (could be 'value', 'total', 'revenue', etc.)
        $value = $row['value'] ?? $row['total'] ?? $row['revenue'] ?? $row['amount'] ?? null;
        
        if ($value !== null) {
          $formattedValue = is_numeric($value) ? number_format((float)$value, 2) : $value;
          $formattedRows[] = "  {$formattedPeriod}: {$formattedValue}";
        } else {
          // Just show the row data
          $rowData = array_map(function($k, $v) {
            return "{$k}: {$v}";
          }, array_keys($row), array_values($row));
          $formattedRows[] = "  " . implode(", ", $rowData);
        }
      } else {
        // No period column found, format as generic row
        $rowData = array_map(function($k, $v) {
          return "{$k}: {$v}";
        }, array_keys($row), array_values($row));
        $formattedRows[] = "  " . implode(", ", $rowData);
      }
    }

    return implode("\n", $formattedRows);
  }

  /**
   * Detect if sub-query results contain multi-temporal data
   * 
   * @param array $subQueryResults Array of sub-query results
   * @return bool True if multi-temporal data detected
   */
  public function isMultiTemporalResult(array $subQueryResults): bool
  {
    $temporalPeriods = [];
    foreach ($subQueryResults as $result) {
      if (isset($result['temporal_period']) && !empty($result['temporal_period'])) {
        $temporalPeriods[] = $result['temporal_period'];
      }
    }

    // Multi-temporal if we have 2+ different temporal periods
    return count(array_unique($temporalPeriods)) >= 2;
  }

  /**
   * Get detected temporal periods from sub-query results
   * 
   * @param array $subQueryResults Array of sub-query results
   * @return array List of temporal periods in order
   */
  public function getTemporalPeriodsFromResults(array $subQueryResults): array
  {
    $periods = [];
    foreach ($subQueryResults as $result) {
      if (isset($result['temporal_period']) && !empty($result['temporal_period'])) {
        $periods[] = $result['temporal_period'];
      }
    }
    return $periods;
  }
}

<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Query;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Helper\LanguageHelper;
use ClicShopping\AI\DomainsAI\CoreAI\Patterns\Common\EntityDetectionPattern;
use ClicShopping\AI\DomainsAI\CoreAI\Patterns\Common\ContinuationPattern;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\OperatorPattern;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\QueryCriteriaPattern;

/**
 * QueryAnalyzer Class
 *
 * Responsible for query analysis, enrichment, and criteria extraction.
 * Separated from OrchestratorAgent to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Analyze query relation to conversation context
 * - Extract entities from messages
 * - Extract structured query criteria (filters, ranges)
 * - Enrich queries with conversation context
 * - Format structured filters to human-readable text
 *
 * REORGANIZATION: Moved from SubOrchestrator to CoreAI/Query (2025-12-10)
 */

class QueryAnalyzer
{
  private SecurityLogger $securityLogger;
  private bool $debug;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
    $this->securityLogger = new SecurityLogger();

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("QueryAnalyzer initialized", 'info');
    }
  }

  /**
   * Analyze query's relation to conversation context
   *
   * Determines if the query is a new query, continuation, modification, or clarification
   * based on keyword similarity, entity overlap, and continuation patterns.
   *
   * @param string $query User query
   * @param array $context Conversation context (short_term_context, long_term_context)
   * @return array Analysis result with relation type, confidence, entities, keywords
   */
  public function analyzeQueryContextRelation(string $query, array $context): array
  {
    $analysis = [
      'is_related_to_context' => false,
      'relation_type' => 'new_query', // new_query, continuation, modification, clarification
      'confidence' => 0.0,
      'related_entities' => [],
      'context_keywords' => [],
      'query_keywords' => $this->extractKeywords($query)
    ];

    // If no context, it's definitely a new query
    if (empty($context['short_term_context']) && empty($context['long_term_context'])) {
      return $analysis;
    }

    // Extract keywords from recent context
    $contextKeywords = [];
    $contextEntities = [];

    // Analyze short-term context (recent conversation)
    if (!empty($context['short_term_context'])) {
      foreach ($context['short_term_context'] as $message) {
        $messageKeywords = $this->extractKeywords($message['content'] ?? '');
        $contextKeywords = array_merge($contextKeywords, $messageKeywords);

        // Extract mentioned entities (products, categories, etc.)
        $entities = $this->extractEntitiesFromMessage($message['content'] ?? '');
        $contextEntities = array_merge($contextEntities, $entities);
      }
    }

    // Analyze long-term context (similar interactions)
    if (!empty($context['long_term_context'])) {
      foreach ($context['long_term_context'] as $document) {
        $docContent = $document->content ?? '';
        $docKeywords = $this->extractKeywords($docContent);
        $contextKeywords = array_merge($contextKeywords, $docKeywords);

        $entities = $this->extractEntitiesFromMessage($docContent);
        $contextEntities = array_merge($contextEntities, $entities);
      }
    }

    $contextKeywords = array_unique($contextKeywords);
    $contextEntities = array_unique($contextEntities);
    $analysis['context_keywords'] = $contextKeywords;
    $analysis['related_entities'] = $contextEntities;

    // Calculate similarity between query and context keywords
    $commonKeywords = array_intersect($analysis['query_keywords'], $contextKeywords);
    $keywordSimilarity = count($contextKeywords) > 0 ? count($commonKeywords) / count($contextKeywords) : 0;

    // Detect continuation/modification patterns using ContinuationPattern
    $isContinuation = ContinuationPattern::matches($query);

    // Detect common entities (products, categories, etc.)
    $queryEntities = $this->extractEntitiesFromMessage($query);
    $commonEntities = array_intersect($queryEntities, $contextEntities);
    $entitySimilarity = count($contextEntities) > 0 ? count($commonEntities) / count($contextEntities) : 0;

    // Calculate overall confidence
    $confidence = ($keywordSimilarity * 0.4) + ($entitySimilarity * 0.4) + ($isContinuation ? 0.2 : 0);

    // Determine relation type
    if ($confidence > 0.7 || $isContinuation) {
      $analysis['is_related_to_context'] = true;

      if ($isContinuation) {
        $analysis['relation_type'] = 'continuation';
      } elseif ($keywordSimilarity > 0.8) {
        $analysis['relation_type'] = 'clarification';
      } else {
        $analysis['relation_type'] = 'modification';
      }
    } elseif ($confidence > 0.3) {
      $analysis['is_related_to_context'] = true;
      $analysis['relation_type'] = 'related_new_query';
    }

    $analysis['confidence'] = $confidence;

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Query context analysis: relation_type={$analysis['relation_type']}, confidence={$confidence}, common_keywords=" . count($commonKeywords),
        'info'
      );
    }

    return $analysis;
  }

  /**
   * Extract entities from message
   *
   * Identifies e-commerce entities (products, categories, customers, orders, etc.)
   * using EntityDetectionPattern class.
   *
   * @param string $message Message text
   * @return array Extracted entities (unique list)
   */
  public function extractEntitiesFromMessage(string $message): array
  {
    return EntityDetectionPattern::detect($message);
  }

  /**
   * Extract structured query criteria
   *
   * Extracts entities, filters, and ranges from query using pattern matching.
   * Handles boolean filters (with/without), numeric comparisons (>, <, =),
   * and range filters (between X and Y).
   *
   * @param string $query User query
   * @return array Extracted criteria with entities, filters, ranges
   */
  public function extractQueryCriteria(string $query): array
  {
    $query = mb_strtolower($query);

    // Standardized return structure
    $result = [
      'entities' => $this->extractEntitiesFromMessage($query),
      'filters'  => [],
      'ranges'   => []
    ];

    // 1. Get allowed fields from QueryCriteriaPattern
    $fieldPattern = QueryCriteriaPattern::getFieldPattern();

    // 2. Handle boolean filters (with/without)
    // Captures: "with stock", "without price", "no sku"
    if (preg_match_all('/(with|without|no)\s+(' . $fieldPattern . ')/', $query, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $operator = ($match[1] === 'with') ? 'exists' : 'not_exists';
        $result['filters'][] = [
          'field'    => $match[2],
          'operator' => $operator,
          'value'    => null
        ];
      }
    }

    // 3. Handle advanced numeric comparisons using OperatorPattern
    $opsPattern = OperatorPattern::getOperatorRegexPattern();
    $regex = "/($fieldPattern)\s+(?:is\s+)?($opsPattern)\s+(\d+(?:\.\d+)?)/";

    if (preg_match_all($regex, $query, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $field = $match[1];
        $rawOp = $match[2];
        $value = $match[3];

        $result['filters'][] = [
          'field'    => $field,
          'operator' => OperatorPattern::translate($rawOp), // Translate to standard operator
          'value'    => (float)$value
        ];
      }
    }

    // 4. Handle ranges (Between)
    // Ex: "price between 10 and 50"
    if (preg_match_all("/($fieldPattern)\s+between\s+(\d+(?:\.\d+)?)\s+and\s+(\d+(?:\.\d+)?)/", $query, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $result['ranges'][] = [
          'field' => $match[1],
          'min'   => (float)$match[2],
          'max'   => (float)$match[3]
        ];
      }
    }

    return $result;
  }

  /**
   * Enrich query with conversation context
   *
   * Adds relevant context information to the query for continuation or modification queries.
   * Includes entities, filters, ranges, and previous SQL queries from conversation history.
   *
   * @param string $query User query
   * @param array $context Conversation context
   * @param array $contextAnalysis Context analysis result
   * @return string Enriched query with context information
   */
  public function enrichQueryWithContext(string $query, array $context, array $contextAnalysis): string
  {
    $enrichedQuery = $query;
    $contextInfo = [];

    // Only enrich for continuation or modification queries
    if ($contextAnalysis['relation_type'] === 'continuation' || $contextAnalysis['relation_type'] === 'modification') {

      // 1. Extract relevant information from short-term context (Memory)
      if (!empty($context['short_term_context'])) {
        // Limit search to the N most recent user messages to save tokens
        $recentUserMessages = array_filter($context['short_term_context'], fn($m) => $m['role'] === 'user');
        $lastMessages = array_slice($recentUserMessages, -3); // Limit to 3 latest user messages

        foreach ($lastMessages as $message) {
          // Use the structured V2 version of extractQueryCriteria
          $previousCriteria = $this->extractQueryCriteria($message['content']);

          // Merge found criteria into the global context array
          $contextInfo['entities'] = array_merge($contextInfo['entities'] ?? [], $previousCriteria['entities'] ?? []);
          $contextInfo['filters']  = array_merge($contextInfo['filters'] ?? [], $previousCriteria['filters'] ?? []);
          $contextInfo['ranges']   = array_merge($contextInfo['ranges'] ?? [], $previousCriteria['ranges'] ?? []);
        }
      }

      // 2. Extract information from long-term context (e.g., Previous SQL Query)
      if (!empty($context['long_term_context'])) {
        foreach ($context['long_term_context'] as $document) {
          $docContent = $document->content ?? '';
          // Use 's' modifier to capture multi-line SQL queries
          if (preg_match('/SQL Query:\s*(.+?)(?:\n|$)/is', $docContent, $matches)) {
            // Truncate SQL query if it's excessively long
            $sql = trim($matches[1]);
            $contextInfo['previous_sql'] = (strlen($sql) > 500) ? substr($sql, 0, 500) . '...' : $sql;
            break; // Only take the latest SQL query found
          }
        }
      }

      // 3. Enrich the prompt (using Markdown for clarity)
      if (
        !empty($contextInfo['entities']) ||
        !empty($contextInfo['filters']) ||
        !empty($contextInfo['ranges']) ||
        !empty($contextInfo['previous_sql'])
      ) {
        $enrichedQuery .= "\n\n### Conversation Context to Consider:\n";

        // A. Entities
        if (!empty($contextInfo['entities'])) {
          // Remove duplicate entities after merging
          $entitiesUnique = array_unique($contextInfo['entities']);
          $enrichedQuery .= "* **Primary Entities**: " . implode(', ', $entitiesUnique) . "\n";
        }

        // B. Structured Filters (V2)
        $filtersText = $this->formatStructuredFiltersToText($contextInfo['filters'] ?? [], $contextInfo['ranges'] ?? []);
        if (!empty($filtersText)) {
          $enrichedQuery .= "* **Implicit Filters**: The previous request included: *{$filtersText}*\n";
        }

        // C. SQL Query
        if (!empty($contextInfo['previous_sql'])) {
          $enrichedQuery .= "* **Last SQL Analysis**: The previous operation was:\n```sql\n" . $contextInfo['previous_sql'] . "\n```\n";
        }

        $enrichedQuery .= "\n**Instruction**: Please interpret and adapt the new query based on this implicit context, without repeating it.";
      }
    }

    // Security Logging
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Query enriched with context. Original length: " . strlen($query) . ", Enriched length: " . strlen($enrichedQuery),
        'info'
      );
    }

    return $enrichedQuery;
  }

  /**
   * Format structured filters to text
   *
   * Converts structured filters and ranges into human-readable text
   * suitable for LLM prompts.
   *
   * @param array $filters Simple filters (field, operator, value)
   * @param array $ranges Range filters (field, min, max)
   * @return string Formatted text string
   */
  public function formatStructuredFiltersToText(array $filters, array $ranges): string
  {
    $parts = [];

    // Format simple filters (>, <, =)
    foreach ($filters as $filter) {
      $field = $filter['field'] ?? 'a field';
      $operator = $filter['operator'] ?? '=';
      $value = $filter['value'] ?? 'unknown value';

      if ($operator === 'exists') {
        $parts[] = "presence of field `{$field}`";
      } elseif ($operator === 'not_exists') {
        $parts[] = "absence of field `{$field}`";
      } else {
        // Replace symbols with words for the LLM using OperatorPattern
        $opText = OperatorPattern::getReverseMap()[$operator] ?? $operator;

        $parts[] = "`{$field}` is {$opText} {$value}";
      }
    }

    // Format ranges (between)
    foreach ($ranges as $range) {
      $field = $range['field'] ?? 'a field';
      $min = $range['min'] ?? 0;
      $max = $range['max'] ?? 0;
      $parts[] = "`{$field}` is between {$min} and {$max}";
    }

    // Join parts with a comma, and "and" for the last element
    if (count($parts) > 1) {
      $last = array_pop($parts);
      return implode(', ', $parts) . " and " . $last;
    }

    return implode('', $parts);
  }

  /**
   * Extract keywords from query
   *
   * Extracts meaningful keywords by removing stop words and short words.
   * Used for context analysis and similarity calculations.
   *
   * @param string $query User query
   * @return array Extracted keywords (unique list)
   */
  public function extractKeywords(string $query): array
  {
    // Stop words to ignore
    $stopWords = LanguageHelper::stopWord();

    // Clean and split query
    $words = preg_split('/\s+/', strtolower(trim($query)));
    $keywords = [];

    foreach ($words as $word) {
      // Clean word (remove punctuation)
      $cleanWord = preg_replace('/[^\w\-]/', '', $word);

      // Ignore short words or stop words
      if (strlen($cleanWord) > 2 && !in_array($cleanWord, $stopWords, true)) {
        $keywords[] = $cleanWord;
      }
    }

    return array_unique($keywords);
  }
}

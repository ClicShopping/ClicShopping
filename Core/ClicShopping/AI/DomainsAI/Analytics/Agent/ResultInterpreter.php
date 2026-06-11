<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Hash;
use ClicShopping\AI\Security\InputValidator;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Cache\Cache;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\DomainsAI\Analytics\Agent\EmptyResultFormatter;

/**
 * Class ResultInterpreter
 * Handles interpretation of SQL query results into natural language
 * Implements caching and sanitization for AI prompts
 * 
 * @updated 2026-01-11: Added EmptyResultFormatter integration for empty result handling
 */

class ResultInterpreter
{
  private mixed $chat;
  private Cache $cache;
  private SecurityLogger $securityLogger;
  private mixed $app;
  private mixed $language;
  private int $maxRowsForInterpretation;
  private bool $enablePromptCache;
  private bool $debug;
  private array $promptCache = [];
  private ?EmptyResultFormatter $emptyResultFormatter = null;

  /**
   * Constructor
   *
   * @param mixed $chat Chat interface for AI generation
   * @param Cache $cache Cache instance
   * @param SecurityLogger $securityLogger Security logger instance
   * @param mixed $app Application instance for definitions
   * @param int $maxRowsForInterpretation Maximum rows to send to AI
   * @param bool $enablePromptCache Enable prompt caching
   * @param bool $debug Enable debug mode
   */
  public function __construct(mixed $chat, Cache $cache, SecurityLogger $securityLogger, mixed $app, int $maxRowsForInterpretation, bool $enablePromptCache = true, bool $debug = false) {
    $this->chat = $chat;
    $this->cache = $cache;
    $this->securityLogger = $securityLogger;
    $this->app = $app;
    $this->language = Registry::get('Language');
    $this->maxRowsForInterpretation = $maxRowsForInterpretation;
    $this->enablePromptCache = $enablePromptCache;
    $this->debug = $debug;
    
    // Initialize EmptyResultFormatter for handling empty results
    $this->emptyResultFormatter = new EmptyResultFormatter($chat, $securityLogger, $debug);
  }

  /**
   * Interprets the results of a SQL query into natural language
   * Generates a natural language interpretation of the results
   * Uses caching to improve performance
   * 
   * @updated 2026-01-11: Added empty result detection and delegation to EmptyResultFormatter
   *
   * @param string $question The business question in natural language
   * @param array $results The results of the SQL query
   * @param string $sqlQuery Optional SQL query for empty result formatting
   * @param string $queryType Optional query type (analytics, semantic, hybrid)
   * @return string|array Natural language interpretation or empty result array
   */
  public function interpretResults(string $question, array $results, string $sqlQuery = '', string $queryType = 'analytics'): string|array
  {
    // Check for empty results and delegate to EmptyResultFormatter
    if ($this->isEmptyResult($results)) {
      if ($this->debug) {
        error_log("ResultInterpreter: Empty results detected, delegating to EmptyResultFormatter");
      }
      
      return $this->emptyResultFormatter->formatEmptyResult($question, $sqlQuery, $queryType);
    }
    
    // Check if the number of rows exceeds the configured limit
    if (\count($results) > $this->maxRowsForInterpretation) {
      // Generate a message indicating the result set is too large
      $array = [
        'maxRows' => $this->maxRowsForInterpretation,
        'count' => \count($results)
      ];

      return CLICSHOPPING::getDef('text_error_context_sql_number_request', $array);
    }

    $cleanResults = $this->sanitizeResultsForPrompt($results);
    $safeQuestion = InputValidator::validateParameter($question, 'string');

    if ($safeQuestion !== $question) {
      $this->securityLogger->logSecurityEvent(
        "Question sanitized in interpretResults",
        'warning'
      );
      $question = $safeQuestion;
    }

    $interpretCacheKey = "interpret_" . $this->cache->generateCacheKey($question . json_encode($cleanResults));

    // Check the cache with expiration logic
    if ($this->enablePromptCache && isset($this->promptCache[$interpretCacheKey])) {
      $cacheItem = $this->promptCache[$interpretCacheKey];

      if (time() < $cacheItem['ttl']) {
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Using cached interpretation for question: " . substr($question, 0, 50) . "...",
            'info'
          );
        }

        $this->promptCache[$interpretCacheKey]['last_used'] = time();

        return $cacheItem['response'];
      } else {
        // Remove expired cache
        unset($this->promptCache[$interpretCacheKey]);
      }
    }

    // Load the prompt in English for consistency with AI training
    DomainConfig::loadAgnosticLanguageFile('rag_result_interpreter');
    
    $array = [
      'question' => $question,
      'results' => json_encode($cleanResults, JSON_PRETTY_PRINT),
      'currentDate' => date('Y-m-d')
    ];

    $prompt = $this->language->getDef('text_interpret_results', $array);

    if ($this->debug) {
      error_log("ResultInterpreter: Using English prompt for interpretation");
      error_log("Prompt length: " . \strlen($prompt) . " chars");
    }

    $interpretation = $this->chat->generateText($prompt);

    // Create the cache with expiration
    if ($this->enablePromptCache) {
      $this->promptCache[$interpretCacheKey] = [
        'prompt' => $prompt,
        'response' => $interpretation,
        'created' => time(),
        'last_used' => time(),
        'ttl' => time() + 3600 // Cache expires in 1 hour
      ];

      $this->cache->savePromptCache();
    }

    return $interpretation;
  }
  
  /**
   * Check if results are empty
   * 
   * Detects empty results including:
   * - Empty array
   * - Array with single row where all values are null/zero/empty
   * 
   * @param array $results Query results to check
   * @return bool True if results are empty
   */
  public function isEmptyResult(array $results): bool
  {
    if (empty($results)) {
      return true;
    }
    
    // Check if all values are null or zero (single row with no meaningful data)
    if (\count($results) === 1) {
      $firstRow = reset($results);
      if (\is_array($firstRow)) {
        foreach ($firstRow as $value) {
          if ($value !== null && $value !== 0 && $value !== '0' && $value !== '') {
            return false;
          }
        }
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Get the EmptyResultFormatter instance
   * 
   * @return EmptyResultFormatter
   */
  public function getEmptyResultFormatter(): EmptyResultFormatter
  {
    return $this->emptyResultFormatter;
  }

  /**
   * Sanitizes results for inclusion in a prompt
   * Handles nested arrays, objects, and various data types
   * Implements error handling and logging
   * 
   * 🔧 FIX 2026-01-11: Added decryption for GDPR-encrypted fields (customers_name, etc.)
   *
   * @param array $results Results to sanitize
   * @return array Sanitized results
   */
  public function sanitizeResultsForPrompt(array $results): array
  {
    $cleanedResults = [];

    foreach ($results as $rowKey => $row) {
      if (!\is_array($row)) {
        // Simple encoding for scalar values - decrypt first
        $decrypted = Hash::displayDecryptedDataText((string) $row);
        $cleanedResults[$rowKey] = htmlspecialchars($decrypted, ENT_QUOTES, 'UTF-8');
        continue;
      }

      $cleanedRow = [];
      foreach ($row as $key => $value) {
        // Clean each cell value
        if (\is_array($value)) {
          $cleanedRow[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (\is_object($value)) {
          $cleanedRow[$key] = '[object]';
        } else {
          // 🔧 FIX: Decrypt GDPR-encrypted fields before sending to LLM
          $decrypted = Hash::displayDecryptedDataText((string) $value);
          $cleanedRow[$key] = htmlspecialchars($decrypted, ENT_QUOTES, 'UTF-8');
        }
      }
      $cleanedResults[$rowKey] = $cleanedRow;
    }

    return $cleanedResults;
  }

  /**
   * Extracts clean translation from verbose GPT response
   * Removes descriptive text and extracts only the translation
   *
   * @param string $response Response from GPT
   * @return string Clean translation
   */
  public function extractCleanTranslation(string $response): string
  {
    if ($this->debug) {
      error_log("Extracting clean translation...");
    }

    // Pattern 1: Extract text between quotes after "is:"
    if (preg_match('/is:\s*"([^"]+)"|is:\s*\'([^\']+)\'/', $response, $matches)) {
      $clean = $matches[1] ?? $matches[2];
      if ($this->debug) {
        error_log("Extracted via pattern 1: '{$clean}'");
      }
      return $clean;
    }

    // Pattern 2: Extract quoted text
    if (preg_match('/["\']([^"\']+)["\']/', $response, $matches)) {
      $clean = $matches[1];
      if ($this->debug) {
        error_log("Extracted via pattern 2: '{$clean}'");
      }
      return $clean;
    }

    // Fallback
    if ($this->debug) {
      error_log("No pattern matched, returning as-is: '{$response}'");
    }
    return $response;
  }
}

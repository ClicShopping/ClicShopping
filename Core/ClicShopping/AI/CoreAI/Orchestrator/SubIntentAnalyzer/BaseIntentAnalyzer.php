<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\InterfacesAI\IntentAnalyzerInterface;

/**
 * BaseIntentAnalyzer
 *
 * Abstract base class providing common functionality for all intent analyzers.
 * Implements shared logic for logging, normalization, and basic pattern matching.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer
 * @since 2025-12-14
 */
abstract class BaseIntentAnalyzer implements IntentAnalyzerInterface
{
  protected SecurityLogger $logger;
  protected bool $debug;
  protected string $type;

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
   * {@inheritdoc}
   */
  public function getType(): string
  {
    return $this->type;
  }

  /**
   * Normalize query for pattern matching
   *
   * @param string $query Query to normalize
   * @return string Normalized query (lowercase, no special chars, single spaces)
   */
  protected function normalizeQuery(string $query): string
  {
    $normalized = strtolower($query);
    $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    return trim($normalized);
  }

  /**
   * Log detection result
   *
   * @param string $query Query analyzed
   * @param bool $matches Whether analyzer matched
   * @param float $confidence Confidence score
   * @param array $reasoning Detection reasoning
   */
  protected function logDetection(string $query, bool $matches, float $confidence, array $reasoning): void
  {
    if ($this->debug) {
      $status = $matches ? '✅' : '❌';
      $this->logger->logStructured(
        'info',
        get_class($this),
        'detection_result',
        [
          'query' => $query,
          'type' => $this->type,
          'matches' => $matches,
          'confidence' => round($confidence, 3),
          'reasoning' => $reasoning,
        ]
      );

      error_log("{$status} {$this->type} analyzer: " . ($matches ? "MATCH" : "NO MATCH") . " (confidence: " . round($confidence, 3) . ")");
    }
  }

  /**
   * Count pattern matches in query
   *
   * @param string $query Normalized query
   * @param array $patterns Array of regex patterns
   * @return array Match results with count and matched patterns
   */
  protected function countPatternMatches(string $query, array $patterns): array
  {
    $matchCount = 0;
    $matchedPatterns = [];

    foreach ($patterns as $pattern => $weight) {
      if (preg_match($pattern, $query)) {
        $matchCount++;
        $matchedPatterns[] = $pattern;
      }
    }

    return [
      'count' => $matchCount,
      'patterns' => $matchedPatterns,
      'total_patterns' => count($patterns),
    ];
  }
}

<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Cache\Cache;

/**
 * LearningStatistics Class
 * Manages correction statistics tracking, persistence, and reporting
 */
class LearningStatistics
{
  private Cache $cache;
  private SecurityLogger $logger;
  private string $userId;
  private bool $debug;

  private array $learningStats = [
    'total_errors' => 0,
    'successful_corrections' => 0,
    'failed_corrections' => 0,
    'learned_patterns' => 0,
    'correction_accuracy' => 0.0,
  ];

  /**
   * Constructor
   *
   * @param Cache $cache Cache instance for persistence
   * @param SecurityLogger $logger Security logger instance
   * @param string $userId User identifier
   * @param bool $debug Debug mode flag
   */
  public function __construct(Cache $cache, SecurityLogger $logger, string $userId, bool $debug)
  {
    $this->cache = $cache;
    $this->logger = $logger;
    $this->userId = $userId;
    $this->debug = $debug;

    $this->loadLearningStats();

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "LearningStatistics initialized for user: {$this->userId}",
        'info'
      );
    }
  }

  /**
   * Increment total errors counter
   */
  public function incrementTotalErrors(): void
  {
    $this->learningStats['total_errors']++;
  }

  /**
   * Increment successful corrections counter
   */
  public function incrementSuccessfulCorrections(): void
  {
    $this->learningStats['successful_corrections']++;
  }

  /**
   * Increment failed corrections counter
   */
  public function incrementFailedCorrections(): void
  {
    $this->learningStats['failed_corrections']++;
  }

  /**
   * Increment learned patterns counter
   */
  public function incrementLearnedPatterns(): void
  {
    $this->learningStats['learned_patterns']++;
  }

  /**
   * Load learning statistics from cache
   */
  private function loadLearningStats(): void
  {
    $cacheKey = "correction_agent_stats_{$this->userId}";
    $cached = $this->cache->getCachedResponse($cacheKey);

    if ($cached !== null) {
      $decoded = json_decode($cached, true);
      if (is_array($decoded)) {
        $this->learningStats = array_merge($this->learningStats, $decoded);
      }
    }
  }

  /**
   * Save learning statistics to cache
   */
  private function saveLearningStats(): void
  {
    $cacheKey = "correction_agent_stats_{$this->userId}";
    $encoded = json_encode($this->learningStats);
    $this->cache->cacheResponse($cacheKey, $encoded, 86400);

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Learning stats saved: " . json_encode($this->learningStats),
        'info'
      );
    }
  }

  /**
   * Get learning statistics
   *
   * @return array Statistics
   */
  public function getLearningStats(): array
  {
    return $this->learningStats;
  }

  /**
   * Reset learning statistics
   */
  public function resetLearningStats(): void
  {
    $this->learningStats = [
      'total_errors' => 0,
      'successful_corrections' => 0,
      'failed_corrections' => 0,
      'learned_patterns' => 0,
      'correction_accuracy' => 0.0,
    ];

    $this->saveLearningStats();
  }

  /**
   * Update correction accuracy
   */
  public function updateAccuracy(): void
  {
    $total = $this->learningStats['successful_corrections'] + $this->learningStats['failed_corrections'];

    if ($total > 0) {
      $this->learningStats['correction_accuracy'] =
        $this->learningStats['successful_corrections'] / $total;
    }

    if ($total % 10 === 0) {
      $this->saveLearningStats();
    }
  }

  /**
   * Get detailed learning report
   *
   * @return array Report
   */
  public function getLearningReport(): array
  {
    $totalErrors = $this->learningStats['total_errors'];
    $successfulCorrections = $this->learningStats['successful_corrections'];
    $failedCorrections = $this->learningStats['failed_corrections'];

    return [
      'overview' => [
        'total_errors_processed' => $totalErrors,
        'successful_corrections' => $successfulCorrections,
        'failed_corrections' => $failedCorrections,
        'correction_accuracy' => round($this->learningStats['correction_accuracy'] * 100, 2) . '%',
        'learned_patterns' => $this->learningStats['learned_patterns'],
      ],
      'performance' => [
        'success_rate' => $totalErrors > 0
          ? round(($successfulCorrections / $totalErrors) * 100, 2) . '%'
          : 'N/A',
        'learning_efficiency' => $successfulCorrections > 0
          ? round($this->learningStats['learned_patterns'] / $successfulCorrections, 2)
          : 0,
      ],
      'recommendations' => $this->generateRecommendations(),
    ];
  }

  /**
   * Generate recommendations based on stats
   * 
   * @return array Array of recommendations
   */
  private function generateRecommendations(): array
  {
    $recommendations = [];
    $accuracy = $this->learningStats['correction_accuracy'];

    if ($accuracy < 0.5) {
      $recommendations[] = "Low correction accuracy. Consider reviewing correction strategies.";
    } elseif ($accuracy < 0.7) {
      $recommendations[] = "Moderate correction accuracy. System is learning but could improve.";
    } else {
      $recommendations[] = "Good correction accuracy. System is learning effectively.";
    }

    $learnedPatterns = $this->learningStats['learned_patterns'];
    if ($learnedPatterns < 10) {
      $recommendations[] = "Limited learned patterns. More corrections needed for better performance.";
    } elseif ($learnedPatterns < 50) {
      $recommendations[] = "Building knowledge base. Continue processing errors to improve.";
    } else {
      $recommendations[] = "Substantial knowledge base acquired. System can handle many error types.";
    }

    return $recommendations;
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Metrics;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

/**
 * ReasoningAgentStats Class
 * 
 * Manages persistent statistics for ReasoningAgent using database
 * Retrieves and aggregates reasoning mode statistics from rag_statistics table
 */
class ReasoningAgentStats
{
  private $db;

  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Get statistics for all modes from the database
   * 
   * @param int $days Number of days to look back (default: 30)
   * @return array Statistics aggregated by mode
   */
  public function getStats(int $days = 30): array
  {
    $dateLimit = date('Y-m-d H:i:s', strtotime("-{$days} days"));

    // Get overall stats
    $Qoverall = $this->db->prepare('
      SELECT 
        COUNT(*) as total_reasonings,
        SUM(CASE WHEN error_occurred = 0 THEN 1 ELSE 0 END) as successful_reasonings,
        SUM(CASE WHEN error_occurred = 1 THEN 1 ELSE 0 END) as failed_reasonings
      FROM :table_rag_statistics
      WHERE agent_type = :agent_type
        AND date_added >= :date_limit
    ');
    $Qoverall->bindValue(':agent_type', 'ReasoningAgent');
    $Qoverall->bindValue(':date_limit', $dateLimit);
    $Qoverall->execute();
    $overall = $Qoverall->fetch();

    // Get detailed stats by mode with metadata
    $Qmodes = $this->db->prepare('
      SELECT 
        classification_type as mode,
        COUNT(*) as count,
        SUM(CASE WHEN error_occurred = 0 THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN error_occurred = 1 THEN 1 ELSE 0 END) as failed,
        AVG(CASE WHEN error_occurred = 0 THEN confidence_score ELSE NULL END) as avg_confidence,
        metadata
      FROM :table_rag_statistics
      WHERE agent_type = :agent_type
        AND date_added >= :date_limit
      GROUP BY classification_type
    ');
    $Qmodes->bindValue(':agent_type', 'ReasoningAgent');
    $Qmodes->bindValue(':date_limit', $dateLimit);
    $Qmodes->execute();

    $byMode = [
      'chain_of_thought' => [
        'count' => 0,
        'successful' => 0,
        'failed' => 0,
        'avg_steps' => 0,
        'avg_confidence' => 0,
      ],
      'tree_of_thought' => [
        'count' => 0,
        'successful' => 0,
        'failed' => 0,
        'avg_paths' => 0,
        'avg_confidence' => 0,
      ],
      'self_consistency' => [
        'count' => 0,
        'successful' => 0,
        'failed' => 0,
        'avg_attempts' => 0,
        'avg_confidence' => 0,
        'avg_agreement' => 0,
      ],
    ];

    // Now get mode-specific metrics from metadata
    foreach (['chain_of_thought', 'tree_of_thought', 'self_consistency'] as $modeName) {
      $Qdetails = $this->db->prepare('
        SELECT metadata
        FROM :table_rag_statistics
        WHERE agent_type = :agent_type
          AND classification_type = :mode
          AND error_occurred = 0
          AND date_added >= :date_limit
      ');
      $Qdetails->bindValue(':agent_type', 'ReasoningAgent');
      $Qdetails->bindValue(':mode', $modeName);
      $Qdetails->bindValue(':date_limit', $dateLimit);
      $Qdetails->execute();

      $totalSteps = 0;
      $totalPaths = 0;
      $totalAttempts = 0;
      $totalAgreement = 0;
      $countWithMetrics = 0;

      while ($row = $Qdetails->fetch()) {
        if (!empty($row['metadata'])) {
          $metadata = json_decode($row['metadata'], true);
          if ($metadata) {
            $countWithMetrics++;
            
            if ($modeName === 'chain_of_thought' && isset($metadata['steps_count'])) {
              $totalSteps += $metadata['steps_count'];
            } elseif ($modeName === 'tree_of_thought' && isset($metadata['explored_paths'])) {
              $totalPaths += $metadata['explored_paths'];
            } elseif ($modeName === 'self_consistency') {
              if (isset($metadata['attempts'])) {
                $totalAttempts += $metadata['attempts'];
              }
              if (isset($metadata['agreement_rate'])) {
                $totalAgreement += $metadata['agreement_rate'];
              }
            }
          }
        }
      }

      // Calculate averages
      if ($countWithMetrics > 0) {
        if ($modeName === 'chain_of_thought') {
          $byMode[$modeName]['avg_steps'] = round($totalSteps / $countWithMetrics, 1);
        } elseif ($modeName === 'tree_of_thought') {
          $byMode[$modeName]['avg_paths'] = round($totalPaths / $countWithMetrics, 1);
        } elseif ($modeName === 'self_consistency') {
          $byMode[$modeName]['avg_attempts'] = round($totalAttempts / $countWithMetrics, 1);
          $byMode[$modeName]['avg_agreement'] = round($totalAgreement / $countWithMetrics, 2);
        }
      }
    }

    // Fill in basic stats from grouped query
    while ($mode = $Qmodes->fetch()) {
      $modeName = $mode['mode'];
      if (isset($byMode[$modeName])) {
        $byMode[$modeName]['count'] = (int)$mode['count'];
        $byMode[$modeName]['successful'] = (int)$mode['successful'];
        $byMode[$modeName]['failed'] = (int)$mode['failed'];
        $byMode[$modeName]['avg_confidence'] = round((float)$mode['avg_confidence'] / 100, 2);
      }
    }

    // Calculate success rate and average steps
    $total = (int)$overall['total_reasonings'];
    $successful = (int)$overall['successful_reasonings'];
    $successRate = $total > 0 ? round(($successful / $total) * 100, 2) . '%' : '0%';
    
    // Calculate overall average steps from all modes
    $totalStepsAllModes = 0;
    $countWithSteps = 0;
    foreach ($byMode as $modeData) {
      if ($modeData['count'] > 0) {
        if (isset($modeData['avg_steps']) && $modeData['avg_steps'] > 0) {
          $totalStepsAllModes += $modeData['avg_steps'] * $modeData['successful'];
          $countWithSteps += $modeData['successful'];
        } elseif (isset($modeData['avg_paths']) && $modeData['avg_paths'] > 0) {
          $totalStepsAllModes += $modeData['avg_paths'] * $modeData['successful'];
          $countWithSteps += $modeData['successful'];
        } elseif (isset($modeData['avg_attempts']) && $modeData['avg_attempts'] > 0) {
          $totalStepsAllModes += $modeData['avg_attempts'] * $modeData['successful'];
          $countWithSteps += $modeData['successful'];
        }
      }
    }
    $avgSteps = $countWithSteps > 0 ? round($totalStepsAllModes / $countWithSteps, 1) : 0;

    return [
      'total_reasonings' => $total,
      'successful_reasonings' => $successful,
      'failed_reasonings' => (int)$overall['failed_reasonings'],
      'success_rate' => $successRate,
      'avg_steps' => $avgSteps,
      'by_mode' => $byMode,
      'period_days' => $days,
    ];
  }

  /**
   * Get statistics for a specific mode
   * 
   * @param string $mode Reasoning mode (chain_of_thought, tree_of_thought, self_consistency)
   * @param int $days Number of days to look back
   * @return array|null Mode statistics or null if not found
   */
  public function getModeStats(string $mode, int $days = 30): ?array
  {
    $stats = $this->getStats($days);
    return $stats['by_mode'][$mode] ?? null;
  }

  /**
   * Save reasoning statistics to database
   * 
   * @param string $mode The reasoning mode used (chain_of_thought, tree_of_thought, self_consistency)
   * @param array $result The result from reasoning operation
   * @param int $responseTime Response time in milliseconds
   * @param bool $success Whether the reasoning was successful
   * @return bool True if saved successfully, false otherwise
   */
  public function saveStatistics(string $mode, array $result, int $responseTime, bool $success): bool
  {
    try {
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      // Prepare metadata with mode-specific metrics
      $metadata = [
        'reasoning_mode' => $mode,
        'success' => $success,
      ];
      
      // Add mode-specific metrics to metadata
      switch ($mode) {
        case 'chain_of_thought':
          if (isset($result['steps_count'])) {
            $metadata['steps_count'] = $result['steps_count'];
          }
          break;
          
        case 'tree_of_thought':
          if (isset($result['explored_paths'])) {
            $metadata['explored_paths'] = $result['explored_paths'];
          }
          break;
          
        case 'self_consistency':
          if (isset($result['attempts'])) {
            $metadata['attempts'] = $result['attempts'];
          }
          if (isset($result['agreement_rate'])) {
            $metadata['agreement_rate'] = $result['agreement_rate'];
          }
          break;
      }
      
      // Add error information if failed
      if (!$success && isset($result['error'])) {
        $metadata['error'] = $result['error'];
      }
      
      // Get confidence score
      $confidence = isset($result['confidence']) ? round($result['confidence'] * 100, 2) : null;
      
      // Insert into rag_statistics table
      $sql = "INSERT INTO {$prefix}rag_statistics 
              (agent_type, classification_type, confidence_score, response_time_ms, 
               error_occurred, error_message, metadata, user_id, session_id, 
               language_id, date_added)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
      
      $stmt = $this->db->prepare($sql);
      $stmt->execute([
        'ReasoningAgent',
        $mode,
        $confidence,
        $responseTime,
        $success ? 0 : 1,
        $success ? null : ($result['error'] ?? 'Unknown error'),
        json_encode($metadata),
        1, // user_id (default)
        session_id(),
        Registry::get('Language')->getId()
      ]);
      
      return true;
      
    } catch (\Exception $e) {
      error_log('ReasoningAgentStats: Failed to save statistics - ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Reset all statistics (for testing purposes)
   */
  public function reset(): void
  {
    $this->db->query('
      DELETE FROM :table_rag_statistics
      WHERE agent_type = "ReasoningAgent"
    ');
  }
}

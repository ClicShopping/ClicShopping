<?php
/**
 * ClicShopping AI - Agent Local Objectives and Evaluation System
 *
 * @copyright 2025 ClicShopping(tm). All rights reserved.
 * @license   MIT License
 * @version   1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

/**
 * ExpertiseWeightingSystem Class
 *
 * Manages expertise-based weighting for agent evaluations.
 * Tracks evaluator expertise levels and applies appropriate weights
 * to consensus calculations, giving more influence to domain experts.
 *
 * Requirements: 16.1, 16.2, 16.3, 16.4, 16.5
 */
class ExpertiseWeightingSystem
{
  private $db;
  private bool $debug;

  // Expertise level weight multipliers
  private const EXPERTISE_WEIGHTS = [
    'novice' => 0.5,
    'competent' => 1.0,
    'expert' => 1.5
  ];

  // Valid expertise levels
  private const VALID_LEVELS = ['novice', 'competent', 'expert'];

  /**
   * Constructor
   *
   * Initializes the expertise weighting system with database connection.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                   CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
  }

  /**
   * Get expertise weight for an agent and output type
   *
   * Retrieves the weight multiplier for an agent's expertise level
   * for a specific output type. Returns 1.0 (competent) if no
   * expertise record exists.
   *
   * @param string $agentId The agent identifier
   * @param string $outputType The output type being evaluated
   * @return float The expertise weight multiplier (0.5, 1.0, or 1.5)
   */
  public function getExpertiseWeight(string $agentId, string $outputType): float
  {
    try {
      $expertiseLevel = $this->getExpertiseLevel($agentId, $outputType);
      
      if ($expertiseLevel === null) {
        // Default to competent weight if no expertise record exists
        return self::EXPERTISE_WEIGHTS['competent'];
      }

      return self::EXPERTISE_WEIGHTS[$expertiseLevel] ?? self::EXPERTISE_WEIGHTS['competent'];

    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ExpertiseWeightingSystem: Failed to get expertise weight - " . $e->getMessage());
      }
      // Default to competent weight on error
      return self::EXPERTISE_WEIGHTS['competent'];
    }
  }

  /**
   * Set expertise level for an agent and output type
   *
   * Sets or updates the expertise level for an agent evaluating
   * a specific output type. Creates a new record if one doesn't exist.
   *
   * @param string $agentId The agent identifier
   * @param string $outputType The output type
   * @param string $expertiseLevel The expertise level: 'novice', 'competent', or 'expert'
   * @throws InvalidArgumentException If expertise level is invalid
   * @throws Exception If database operation fails
   */
  public function setExpertiseLevel(
    string $agentId,
    string $outputType,
    string $expertiseLevel
  ): void {
    // Validate expertise level
    if (!\in_array($expertiseLevel, self::VALID_LEVELS, true)) {
      throw new InvalidArgumentException(
        "Invalid expertise level: {$expertiseLevel}. Must be one of: " . 
        \implode(', ', self::VALID_LEVELS)
      );
    }

    try {
      $sql = "INSERT INTO :table_rag_agent_evaluator_expertise 
              (agent_id, output_type, expertise_level, expertise_weight, registered_at, updated_at)
              VALUES (:agent_id, :output_type, :expertise_level, :expertise_weight, :registered_at, :updated_at)
              ON DUPLICATE KEY UPDATE 
                expertise_level = VALUES(expertise_level),
                expertise_weight = VALUES(expertise_weight),
                updated_at = VALUES(updated_at)";

      $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
      $weight = self::EXPERTISE_WEIGHTS[$expertiseLevel];

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindValue(':output_type', $outputType);
      $stmt->bindValue(':expertise_level', $expertiseLevel);
      $stmt->bindValue(':expertise_weight', $weight);
      $stmt->bindValue(':registered_at', $now);
      $stmt->bindValue(':updated_at', $now);
      $stmt->execute();

      if ($this->debug) {
        error_log(sprintf(
          "ExpertiseWeightingSystem: Set expertise level '%s' (weight: %.1f) for agent '%s' on output type '%s'",
          $expertiseLevel,
          $weight,
          $agentId,
          $outputType
        ));
      }

    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ExpertiseWeightingSystem: Failed to set expertise level - " . $e->getMessage());
      }
      throw new Exception('Failed to set expertise level: ' . $e->getMessage());
    }
  }

  /**
   * Calculate weighted consensus from evaluations
   *
   * Calculates a consensus score by weighting each evaluation according
   * to the evaluator's expertise level for the output type. Expert
   * evaluations have more influence than novice evaluations.
   *
   * @param array $evaluations Array of AgentEvaluation objects
   * @param string $outputType The output type being evaluated
   * @return float The weighted consensus score (0.0 - 1.0)
   * @throws InvalidArgumentException If evaluations array is invalid
   */
  public function calculateWeightedConsensus(array $evaluations, string $outputType): float
  {
    if (empty($evaluations)) {
      throw new InvalidArgumentException('Evaluations array cannot be empty');
    }

    // Validate all elements are AgentEvaluation instances
    foreach ($evaluations as $evaluation) {
      if (!($evaluation instanceof AgentEvaluation)) {
        throw new InvalidArgumentException('All evaluations must be AgentEvaluation instances');
      }
    }

    try {
      $weightedSum = 0.0;
      $totalWeight = 0.0;

      foreach ($evaluations as $evaluation) {
        $agentId = $evaluation->getEvaluatorAgentId();
        $score = $evaluation->getOverallScore();
        $weight = $this->getExpertiseWeight($agentId, $outputType);

        $weightedSum += $score * $weight;
        $totalWeight += $weight;

        if ($this->debug) {
          error_log(sprintf(
            "ExpertiseWeightingSystem: Agent '%s' score %.2f with weight %.1f",
            $agentId,
            $score,
            $weight
          ));
        }
      }

      if ($totalWeight == 0) {
        // Fallback to simple average if total weight is zero
        $scores = array_map(function($eval) {
          return $eval->getOverallScore();
        }, $evaluations);
        return array_sum($scores) / count($scores);
      }

      $weightedConsensus = $weightedSum / $totalWeight;

      if ($this->debug) {
        error_log(sprintf(
          "ExpertiseWeightingSystem: Weighted consensus score: %.2f (weighted sum: %.2f, total weight: %.1f)",
          $weightedConsensus,
          $weightedSum,
          $totalWeight
        ));
      }

      return $weightedConsensus;

    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ExpertiseWeightingSystem: Failed to calculate weighted consensus - " . $e->getMessage());
      }
      
      // Fallback to simple average on error
      $scores = array_map(function($eval) {
        return $eval->getOverallScore();
      }, $evaluations);
      return array_sum($scores) / count($scores);
    }
  }

  /**
   * Get expertise level for an agent and output type
   *
   * Retrieves the expertise level an agent has for evaluating
   * a specific output type.
   *
   * @param string $agentId The agent identifier
   * @param string $outputType The output type
   * @return string|null The expertise level or null if not found
   */
  public function getExpertiseLevel(string $agentId, string $outputType): ?string
  {
    try {
      $sql = "SELECT expertise_level
              FROM :table_rag_agent_evaluator_expertise 
              WHERE agent_id = :agent_id 
              AND output_type = :output_type";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindValue(':output_type', $outputType);
      $stmt->execute();

      $row = $stmt->fetch();
      return $row ? $row['expertise_level'] : null;

    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ExpertiseWeightingSystem: Failed to get expertise level - " . $e->getMessage());
      }
      return null;
    }
  }

  /**
   * Update weight multipliers for expertise levels
   *
   * Allows administrators to adjust the weight multipliers for
   * different expertise levels. Updates all existing records with
   * the new weights.
   *
   * @param array $weights Associative array of level => weight mappings
   * @throws InvalidArgumentException If weights array is invalid
   * @throws Exception If database operation fails
   */
  public function updateWeights(array $weights): void
  {
    // Validate weights array
    foreach ($weights as $level => $weight) {
      if (!\in_array($level, self::VALID_LEVELS, true)) {
        throw new InvalidArgumentException(
          "Invalid expertise level in weights: {$level}. Must be one of: " . 
          \implode(', ', self::VALID_LEVELS)
        );
      }

      if (!\is_numeric($weight) || $weight < 0) {
        throw new InvalidArgumentException(
          "Invalid weight value for level {$level}: {$weight}. Must be a non-negative number."
        );
      }
    }

    try {
      // Update weights for each level
      foreach ($weights as $level => $weight) {
        $sql = "UPDATE :table_rag_agent_evaluator_expertise 
                SET expertise_weight = :expertise_weight,
                    updated_at = :updated_at
                WHERE expertise_level = :expertise_level";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':expertise_weight', (float)$weight);
        $stmt->bindValue(':updated_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $stmt->bindValue(':expertise_level', $level);
        $stmt->execute();

        if ($this->debug) {
          error_log(sprintf(
            "ExpertiseWeightingSystem: Updated weight for level '%s' to %.2f",
            $level,
            $weight
          ));
        }
      }

    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ExpertiseWeightingSystem: Failed to update weights - " . $e->getMessage());
      }
      throw new Exception('Failed to update weights: ' . $e->getMessage());
    }
  }

  /**
   * Get all expertise records for an agent
   *
   * Retrieves all expertise level records for a specific agent
   * across all output types.
   *
   * @param string $agentId The agent identifier
   * @return array Array of expertise records with output types and levels
   */
  public function getAgentExpertise(string $agentId): array
  {
    try {
      $sql = "SELECT output_type, expertise_level, expertise_weight, registered_at, updated_at
              FROM :table_rag_agent_evaluator_expertise 
              WHERE agent_id = :agent_id
              ORDER BY output_type ASC";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->execute();

      $expertise = [];
      while ($row = $stmt->fetch()) {
        $expertise[] = [
          'output_type' => $row['output_type'],
          'expertise_level' => $row['expertise_level'],
          'weight' => (float)$row['expertise_weight'],
          'registered_at' => $row['registered_at'],
          'updated_at' => $row['updated_at']
        ];
      }

      return $expertise;

    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ExpertiseWeightingSystem: Failed to get agent expertise - " . $e->getMessage());
      }
      return [];
    }
  }

  /**
   * Get all experts for an output type
   *
   * Retrieves all agents with 'expert' level expertise for a
   * specific output type.
   *
   * @param string $outputType The output type
   * @return array Array of expert agent IDs with their weights
   */
  public function getExperts(string $outputType): array
  {
    try {
      $sql = "SELECT agent_id, expertise_weight, registered_at, updated_at
              FROM :table_rag_agent_evaluator_expertise 
              WHERE output_type = :output_type 
              AND expertise_level = 'expert'
              ORDER BY updated_at DESC";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':output_type', $outputType);
      $stmt->execute();

      $experts = [];
      while ($row = $stmt->fetch()) {
        $experts[] = [
          'agent_id' => $row['agent_id'],
          'weight' => (float)$row['expertise_weight'],
          'registered_at' => $row['registered_at'],
          'updated_at' => $row['updated_at']
        ];
      }

      return $experts;

    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ExpertiseWeightingSystem: Failed to get experts - " . $e->getMessage());
      }
      return [];
    }
  }

  /**
   * Get expertise statistics
   *
   * Retrieves statistics about expertise levels including
   * distribution across levels and output types.
   *
   * @return array Statistics array with counts and distributions
   */
  public function getExpertiseStatistics(): array
  {
    try {
      $stats = [
        'total_records' => 0,
        'total_agents' => 0,
        'total_output_types' => 0,
        'level_distribution' => [
          'novice' => 0,
          'competent' => 0,
          'expert' => 0
        ],
        'output_types' => []
      ];

      // Get total counts
      $sql = "SELECT 
                COUNT(*) as total_records,
                COUNT(DISTINCT agent_id) as total_agents,
                COUNT(DISTINCT output_type) as total_output_types
              FROM :table_rag_agent_evaluator_expertise";

      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      $row = $stmt->fetch();

      if ($row) {
        $stats['total_records'] = (int)$row['total_records'];
        $stats['total_agents'] = (int)$row['total_agents'];
        $stats['total_output_types'] = (int)$row['total_output_types'];
      }

      // Get level distribution
      $sql = "SELECT expertise_level, COUNT(*) as count
              FROM :table_rag_agent_evaluator_expertise 
              GROUP BY expertise_level";

      $stmt = $this->db->prepare($sql);
      $stmt->execute();

      while ($row = $stmt->fetch()) {
        $stats['level_distribution'][$row['expertise_level']] = (int)$row['count'];
      }

      // Get experts per output type
      $sql = "SELECT output_type, 
                     SUM(CASE WHEN expertise_level = 'expert' THEN 1 ELSE 0 END) as expert_count,
                     SUM(CASE WHEN expertise_level = 'competent' THEN 1 ELSE 0 END) as competent_count,
                     SUM(CASE WHEN expertise_level = 'novice' THEN 1 ELSE 0 END) as novice_count
              FROM :table_rag_agent_evaluator_expertise 
              GROUP BY output_type
              ORDER BY expert_count DESC, competent_count DESC";

      $stmt = $this->db->prepare($sql);
      $stmt->execute();

      while ($row = $stmt->fetch()) {
        $stats['output_types'][$row['output_type']] = [
          'expert' => (int)$row['expert_count'],
          'competent' => (int)$row['competent_count'],
          'novice' => (int)$row['novice_count']
        ];
      }

      return $stats;

    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ExpertiseWeightingSystem: Failed to get statistics - " . $e->getMessage());
      }
      return [
        'total_records' => 0,
        'total_agents' => 0,
        'total_output_types' => 0,
        'level_distribution' => [
          'novice' => 0,
          'competent' => 0,
          'expert' => 0
        ],
        'output_types' => []
      ];
    }
  }

  /**
   * Remove expertise record
   *
   * Removes an expertise level record for an agent and output type.
   *
   * @param string $agentId The agent identifier
   * @param string $outputType The output type
   * @throws Exception If database operation fails
   */
  public function removeExpertise(string $agentId, string $outputType): void
  {
    try {
      $sql = "DELETE FROM :table_rag_agent_evaluator_expertise 
              WHERE agent_id = :agent_id 
              AND output_type = :output_type";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindValue(':output_type', $outputType);
      $stmt->execute();

      if ($this->debug) {
        error_log(sprintf(
          "ExpertiseWeightingSystem: Removed expertise record for agent '%s' on output type '%s'",
          $agentId,
          $outputType
        ));
      }

    } catch (Exception $e) {
      if ($this->debug) {
        error_log("ExpertiseWeightingSystem: Failed to remove expertise - " . $e->getMessage());
      }
      throw new Exception('Failed to remove expertise: ' . $e->getMessage());
    }
  }

  /**
   * Get default expertise weights
   *
   * Returns the default weight multipliers for each expertise level.
   *
   * @return array Associative array of level => weight mappings
   */
  public static function getDefaultWeights(): array
  {
    return self::EXPERTISE_WEIGHTS;
  }

  /**
   * Get valid expertise levels
   *
   * Returns the list of valid expertise levels.
   *
   * @return array Array of valid expertise level strings
   */
  public static function getValidLevels(): array
  {
    return self::VALID_LEVELS;
  }
}

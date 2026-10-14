<?php
/**
 * AgentCapabilityRegistry Class
 *
 * Tracks which agents can evaluate which output types and their capability levels.
 * Provides database persistence and query capabilities for agent evaluation capabilities.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

class AgentCapabilityRegistry
{
  private $db;
  private ExpertiseWeightingSystem $expertiseWeighting;
  
  // Valid capability levels
  private const VALID_LEVELS = ['novice', 'competent', 'expert'];
  
  /**
   * Constructor
   *
   * Initializes the registry with database connection and expertise weighting system.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->expertiseWeighting = new ExpertiseWeightingSystem();
  }
  
  /**
   * Register a capability for an agent
   *
   * Registers or updates an agent's capability to evaluate a specific output type
   * at a given proficiency level. Uses INSERT ... ON DUPLICATE KEY UPDATE to handle
   * both new registrations and updates. Also registers the expertise level in the
   * expertise weighting system.
   *
   * @param string $agentId The agent identifier
   * @param string $outputType The type of output the agent can evaluate
   * @param string $level The capability level: 'novice', 'competent', or 'expert'
   * @throws InvalidArgumentException If capability level is invalid
   * @throws Exception If database operation fails
   */
  public function registerCapability(
    string $agentId,
    string $outputType,
    string $level
  ): void {
    // Validate capability level
    if (!\in_array($level, self::VALID_LEVELS, true)) {
      throw new InvalidArgumentException(
        "Invalid capability level: {$level}. Must be one of: " . 
        \implode(', ', self::VALID_LEVELS)
      );
    }
    
    try {
      $sql = "INSERT INTO :table_rag_agent_capabilities 
              (agent_id, output_type, capability_level, registered_at, updated_at)
              VALUES (:agent_id, :output_type, :capability_level, :registered_at, :updated_at)
              ON DUPLICATE KEY UPDATE 
                capability_level = VALUES(capability_level),
                updated_at = VALUES(updated_at)";
      
      $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindValue(':output_type', $outputType);
      $stmt->bindValue(':capability_level', $level);
      $stmt->bindValue(':registered_at', $now);
      $stmt->bindValue(':updated_at', $now);
      $stmt->execute();
      
      // Also register expertise level in the expertise weighting system
      $this->expertiseWeighting->setExpertiseLevel($agentId, $outputType, $level);
      
    } catch (Exception $e) {
      throw new Exception('Failed to register capability: ' . $e->getMessage());
    }
  }
  
  /**
   * Get capable evaluators for an output type
   *
   * Retrieves all agents that can evaluate a specific output type,
   * filtered by minimum capability level.
   *
   * @param string $outputType The output type to find evaluators for
   * @param string $minLevel Minimum capability level (default: 'competent')
   * @return array Array of agent IDs with their capability levels
   */
  public function getCapableEvaluators(
    string $outputType,
    string $minLevel = 'competent'
  ): array {
    try {
      // Define level hierarchy for filtering
      $levelHierarchy = [
        'novice' => 1,
        'competent' => 2,
        'expert' => 3
      ];
      
      $minLevelValue = $levelHierarchy[$minLevel] ?? 2;
      
      $sql = "SELECT agent_id, capability_level, registered_at, updated_at
              FROM :table_rag_agent_capabilities 
              WHERE output_type = :output_type
              ORDER BY 
                CASE capability_level
                  WHEN 'expert' THEN 3
                  WHEN 'competent' THEN 2
                  WHEN 'novice' THEN 1
                END DESC,
                updated_at DESC";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':output_type', $outputType);
      $stmt->execute();
      
      $evaluators = [];
      while ($row = $stmt->fetch()) {
        $rowLevelValue = $levelHierarchy[$row['capability_level']] ?? 0;
        
        // Only include agents meeting minimum level requirement
        if ($rowLevelValue >= $minLevelValue) {
          $evaluators[] = [
            'agent_id' => $row['agent_id'],
            'capability_level' => $row['capability_level'],
            'registered_at' => $row['registered_at'],
            'updated_at' => $row['updated_at']
          ];
        }
      }
      
      return $evaluators;
    } catch (Exception $e) {
      return [];
    }
  }
  
  /**
   * Get all capabilities for an agent
   *
   * Retrieves all output types an agent can evaluate along with
   * their capability levels.
   *
   * @param string $agentId The agent identifier
   * @return array Array of capabilities with output types and levels
   */
  public function getAgentCapabilities(string $agentId): array
  {
    try {
      $sql = "SELECT output_type, capability_level, registered_at, updated_at
              FROM :table_rag_agent_capabilities 
              WHERE agent_id = :agent_id
              ORDER BY output_type ASC";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->execute();
      
      $capabilities = [];
      while ($row = $stmt->fetch()) {
        $capabilities[] = [
          'output_type' => $row['output_type'],
          'capability_level' => $row['capability_level'],
          'registered_at' => $row['registered_at'],
          'updated_at' => $row['updated_at']
        ];
      }
      
      return $capabilities;
    } catch (Exception $e) {
      return [];
    }
  }
  
  /**
   * Check if an agent has a capability
   *
   * Determines whether an agent is registered to evaluate a specific output type.
   *
   * @param string $agentId The agent identifier
   * @param string $outputType The output type to check
   * @return bool True if the agent has the capability, false otherwise
   */
  public function hasCapability(string $agentId, string $outputType): bool
  {
    try {
      $sql = "SELECT COUNT(*) as count
              FROM :table_rag_agent_capabilities 
              WHERE agent_id = :agent_id 
              AND output_type = :output_type";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindValue(':output_type', $outputType);
      $stmt->execute();
      
      $row = $stmt->fetch();
      return $row && (int)$row['count'] > 0;
    } catch (Exception $e) {
      return false;
    }
  }
  
  /**
   * Get capability level for an agent and output type
   *
   * Retrieves the specific capability level an agent has for an output type.
   *
   * @param string $agentId The agent identifier
   * @param string $outputType The output type
   * @return string|null The capability level or null if not found
   */
  public function getCapabilityLevel(string $agentId, string $outputType): ?string
  {
    try {
      $sql = "SELECT capability_level
              FROM :table_rag_agent_capabilities 
              WHERE agent_id = :agent_id 
              AND output_type = :output_type";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindValue(':output_type', $outputType);
      $stmt->execute();
      
      $row = $stmt->fetch();
      return $row ? $row['capability_level'] : null;
    } catch (Exception $e) {
      return null;
    }
  }
  
  /**
   * Update capability level for an agent
   *
   * Updates an existing capability level for an agent and output type.
   * If the capability doesn't exist, it will be created. Also updates
   * the expertise level in the expertise weighting system.
   *
   * @param string $agentId The agent identifier
   * @param string $outputType The output type
   * @param string $level The new capability level
   * @throws InvalidArgumentException If capability level is invalid
   * @throws Exception If database operation fails
   */
  public function updateCapabilityLevel(
    string $agentId,
    string $outputType,
    string $level
  ): void {
    // Validate capability level
    if (!\in_array($level, self::VALID_LEVELS, true)) {
      throw new InvalidArgumentException(
        "Invalid capability level: {$level}. Must be one of: " . 
        \implode(', ', self::VALID_LEVELS)
      );
    }
    
    try {
      // Check if capability exists
      if ($this->hasCapability($agentId, $outputType)) {
        // Update existing capability
        $sql = "UPDATE :table_rag_agent_capabilities 
                SET capability_level = :capability_level,
                    updated_at = :updated_at
                WHERE agent_id = :agent_id 
                AND output_type = :output_type";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':agent_id', $agentId);
        $stmt->bindValue(':output_type', $outputType);
        $stmt->bindValue(':capability_level', $level);
        $stmt->bindValue(':updated_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $stmt->execute();
      } else {
        // Create new capability
        $this->registerCapability($agentId, $outputType, $level);
      }
      
      // Also update expertise level in the expertise weighting system
      $this->expertiseWeighting->setExpertiseLevel($agentId, $outputType, $level);
      
    } catch (Exception $e) {
      throw new Exception('Failed to update capability level: ' . $e->getMessage());
    }
  }
  
  /**
   * Get all registered output types
   *
   * Retrieves a list of all unique output types that have registered evaluators.
   *
   * @return array Array of output type strings
   */
  public function getAllOutputTypes(): array
  {
    try {
      $sql = "SELECT DISTINCT output_type
              FROM :table_rag_agent_capabilities 
              ORDER BY output_type ASC";
      
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      
      $outputTypes = [];
      while ($row = $stmt->fetch()) {
        $outputTypes[] = $row['output_type'];
      }
      
      return $outputTypes;
    } catch (Exception $e) {
      return [];
    }
  }
  
  /**
   * Get all registered agents
   *
   * Retrieves a list of all unique agents that have registered capabilities.
   *
   * @return array Array of agent ID strings
   */
  public function getAllAgents(): array
  {
    try {
      $sql = "SELECT DISTINCT agent_id
              FROM :table_rag_agent_capabilities 
              ORDER BY agent_id ASC";
      
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      
      $agents = [];
      while ($row = $stmt->fetch()) {
        $agents[] = $row['agent_id'];
      }
      
      return $agents;
    } catch (Exception $e) {
      return [];
    }
  }
  
  /**
   * Remove a capability
   *
   * Removes a specific capability registration for an agent and output type.
   *
   * @param string $agentId The agent identifier
   * @param string $outputType The output type
   * @throws Exception If database operation fails
   */
  public function removeCapability(string $agentId, string $outputType): void
  {
    try {
      $sql = "DELETE FROM :table_rag_agent_capabilities 
              WHERE agent_id = :agent_id 
              AND output_type = :output_type";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindValue(':output_type', $outputType);
      $stmt->execute();
    } catch (Exception $e) {
      throw new Exception('Failed to remove capability: ' . $e->getMessage());
    }
  }
  
  /**
   * Remove all capabilities for an agent
   *
   * Removes all capability registrations for a specific agent.
   * Useful when decommissioning an agent.
   *
   * @param string $agentId The agent identifier
   * @throws Exception If database operation fails
   */
  public function removeAllCapabilitiesForAgent(string $agentId): void
  {
    try {
      $sql = "DELETE FROM :table_rag_agent_capabilities 
              WHERE agent_id = :agent_id";
      
      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->execute();
    } catch (Exception $e) {
      throw new Exception('Failed to remove agent capabilities: ' . $e->getMessage());
    }
  }
  
  /**
   * Get capability statistics
   *
   * Retrieves statistics about registered capabilities including
   * total count, agents per output type, and level distribution.
   *
   * @return array Statistics array with counts and distributions
   */
  public function getCapabilityStatistics(): array
  {
    try {
      $stats = [
        'total_capabilities' => 0,
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
                COUNT(*) as total_capabilities,
                COUNT(DISTINCT agent_id) as total_agents,
                COUNT(DISTINCT output_type) as total_output_types
              FROM :table_rag_agent_capabilities";
      
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      $row = $stmt->fetch();
      
      if ($row) {
        $stats['total_capabilities'] = (int)$row['total_capabilities'];
        $stats['total_agents'] = (int)$row['total_agents'];
        $stats['total_output_types'] = (int)$row['total_output_types'];
      }
      
      // Get level distribution
      $sql = "SELECT capability_level, COUNT(*) as count
              FROM :table_rag_agent_capabilities 
              GROUP BY capability_level";
      
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      
      while ($row = $stmt->fetch()) {
        $stats['level_distribution'][$row['capability_level']] = (int)$row['count'];
      }
      
      // Get agents per output type
      $sql = "SELECT output_type, COUNT(*) as agent_count
              FROM :table_rag_agent_capabilities 
              GROUP BY output_type
              ORDER BY agent_count DESC";
      
      $stmt = $this->db->prepare($sql);
      $stmt->execute();
      
      while ($row = $stmt->fetch()) {
        $stats['output_types'][$row['output_type']] = (int)$row['agent_count'];
      }
      
      return $stats;
    } catch (Exception $e) {
      return [
        'total_capabilities' => 0,
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
   * Get expertise level for an agent and output type
   *
   * Retrieves the expertise level from the expertise weighting system.
   * This provides access to the detailed expertise tracking including weights.
   *
   * @param string $agentId The agent identifier
   * @param string $outputType The output type
   * @return string|null The expertise level or null if not found
   */
  public function getExpertiseLevel(string $agentId, string $outputType): ?string
  {
    return $this->expertiseWeighting->getExpertiseLevel($agentId, $outputType);
  }

  /**
   * Get expertise weight for an agent and output type
   *
   * Retrieves the weight multiplier for an agent's expertise level.
   *
   * @param string $agentId The agent identifier
   * @param string $outputType The output type
   * @return float The expertise weight multiplier
   */
  public function getExpertiseWeight(string $agentId, string $outputType): float
  {
    return $this->expertiseWeighting->getExpertiseWeight($agentId, $outputType);
  }

  /**
   * Get all expertise records for an agent
   *
   * Retrieves all expertise level records for a specific agent
   * across all output types from the expertise weighting system.
   *
   * @param string $agentId The agent identifier
   * @return array Array of expertise records with output types and levels
   */
  public function getAgentExpertise(string $agentId): array
  {
    return $this->expertiseWeighting->getAgentExpertise($agentId);
  }

  /**
   * Get experts for an output type
   *
   * Retrieves all agents with 'expert' level expertise for a
   * specific output type from the expertise weighting system.
   *
   * @param string $outputType The output type
   * @return array Array of expert agent IDs with their weights
   */
  public function getExperts(string $outputType): array
  {
    return $this->expertiseWeighting->getExperts($outputType);
  }

  /**
   * Get expertise weighting system
   *
   * Returns the expertise weighting system instance for direct access.
   *
   * @return ExpertiseWeightingSystem The expertise weighting system
   */
  public function getExpertiseWeightingSystem(): ExpertiseWeightingSystem
  {
    return $this->expertiseWeighting;
  }
}

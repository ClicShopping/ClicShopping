<?php
declare(strict_types=1);

namespace ClicShopping\AI\RegistryAI;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\InterfacesAI\ActorInterface;
use ClicShopping\AI\RegistryAI\Exceptions\InvalidActorException;
use ClicShopping\AI\RegistryAI\Exceptions\NoCapableActorException;

/**
 * Registry for actor agents with registration, querying, and load tracking
 * 
 * Manages all actor agents in the system, tracks their capabilities,
 * monitors their load, and provides performance metrics.
 */
class ActorRegistry
{
    private $db;
    private string $prefix;
    private array $actors = [];
    private array $loadTracking = [];
    private array $performanceCache = [];
    private int $cacheTimeout = 300; // 5 minutes
    
    public function __construct()
    {
        $this->db = Registry::get('Db');
        $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
    }
    
    /**
     * Register an actor agent with validation and persistence
     * 
     * @param ActorInterface $actor Actor to register
     * @return void
     * @throws InvalidActorException If actor invalid
     */
    public function registerActor(ActorInterface $actor): void
    {
        $actorId = $actor->getActorId();
        
        // Validate actor implements interface
        if (!$actor instanceof ActorInterface) {
            throw new InvalidActorException("Actor must implement ActorInterface");
        }
        
        // Validate actor ID is not empty
        if (empty($actorId)) {
            throw new InvalidActorException("Actor ID cannot be empty");
        }
        
        // Store in memory
        $this->actors[$actorId] = $actor;
        $this->loadTracking[$actorId] = 0;
        
        // Persist to database
        $capabilities = $actor->getCapabilities();
        foreach ($capabilities as $actionType => $capability) {
            $this->persistActorCapability($actorId, $actionType, $capability);
        }
        
        error_log("ActorRegistry: Actor registered: {$actorId} with " . count($capabilities) . " capabilities");
    }
    
    /**
     * Persist actor capability to database
     *
     * @param string $actorId Actor ID
     * @param string $actionType Action type
     * @param mixed $capability Capability object
     * @return void
     */
    private function persistActorCapability(string $actorId, string $actionType, $capability): void
    {
        // Handle both ActorCapability objects and simple arrays
        $confidence = 0.5;
        $domain = null;

        if (is_object($capability) && method_exists($capability, 'getConfidence')) {
            $confidence = $capability->getConfidence();
            if (method_exists($capability, 'getDomain')) {
                $domain = $capability->getDomain();
            }
        } elseif (is_array($capability)) {
            $confidence = $capability['confidence'] ?? 0.5;
            $domain = $capability['domain'] ?? null;
        }

        $sql = "
            INSERT INTO {$this->prefix}rag_agent_actor_registry (
                actor_id, action_type, confidence, domain, registered_at
            ) VALUES (
                :actor_id, :action_type, :confidence, :domain, NOW()
            ) ON DUPLICATE KEY UPDATE
                confidence = VALUES(confidence),
                domain = VALUES(domain),
                updated_at = NOW()
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'actor_id' => $actorId,
            'action_type' => $actionType,
            'confidence' => $confidence,
            'domain' => $domain
        ]);
    }
    
    /**
     * Get current load for actor (0.0-1.0)
     * 
     * @param string $actorId Actor ID
     * @return float Load percentage
     */
    public function getActorLoad(string $actorId): float
    {
        $currentLoad = $this->loadTracking[$actorId] ?? 0;
        $maxConcurrent = $this->getMaxConcurrentActions($actorId);
        
        return $maxConcurrent > 0 ? min(1.0, $currentLoad / $maxConcurrent) : 0.0;
    }
    
    /**
     * Get maximum concurrent actions for actor
     *
     * @param string $actorId Actor ID
     * @return int Maximum concurrent actions
     */
    private function getMaxConcurrentActions(string $actorId): int
    {
        // Default to 5, could be configurable per actor
        return 5;
    }
    
    /**
     * Increment actor load when action starts
     * 
     * @param string $actorId Actor ID
     * @return void
     */
    public function incrementLoad(string $actorId): void
    {
        $this->loadTracking[$actorId] = ($this->loadTracking[$actorId] ?? 0) + 1;
        
        error_log("ActorRegistry: Actor load incremented: {$actorId} -> {$this->loadTracking[$actorId]}");
    }
    
    /**
     * Decrement actor load when action completes
     * 
     * @param string $actorId Actor ID
     * @return void
     */
    public function decrementLoad(string $actorId): void
    {
        $this->loadTracking[$actorId] = max(0, ($this->loadTracking[$actorId] ?? 0) - 1);
        
        error_log("ActorRegistry: Actor load decremented: {$actorId} -> {$this->loadTracking[$actorId]}");
    }
    
    /**
     * Get all registered actors
     * 
     * @return array<string, ActorInterface> Map of actor ID to actor
     */
    public function getAllActors(): array
    {
        return $this->actors;
    }
    
    /**
     * Get all registered actor IDs from database
     * 
     * @return array<string> List of actor IDs
     */
    public function getAllActorIds(): array
    {
        $sql = "SELECT DISTINCT actor_id FROM :table_rag_agent_actor_registry";
        $result = $this->db->query($sql);
        
        $actorIds = [];
        while ($row = $result->fetch()) {
            $actorIds[] = $row['actor_id'];
        }
        
        return $actorIds;
    }
    
    /**
     * Check if actor is registered
     * 
     * @param string $actorId Actor ID
     * @return bool True if registered
     */
    public function isActorRegistered(string $actorId): bool
    {
        return isset($this->actors[$actorId]);
    }
    
    /**
     * Get actor by ID
     * 
     * @param string $actorId Actor ID
     * @return ActorInterface|null Actor or null if not found
     */
    public function getActor(string $actorId): ?ActorInterface
    {
        return $this->actors[$actorId] ?? null;
    }
    
    /**
     * Get actors by domain specialization
     * 
     * Requirements: 23.1, 23.2
     * 
     * @param string $domain Domain name
     * @return array<ActorInterface> Domain-specialized actors
     */
    public function getActorsByDomain(string $domain): array
    {
        $sql = "SELECT DISTINCT actor_id FROM :table_rag_agent_actor_registry WHERE domain = :domain";
        $result = $this->db->prepare($sql);
        $result->execute(['domain' => $domain]);
        
        $domainActors = [];
        while ($row = $result->fetch()) {
            if (isset($this->actors[$row['actor_id']])) {
                $domainActors[] = $this->actors[$row['actor_id']];
            }
        }
        
        return $domainActors;
    }
    
    /**
     * Get actors capable of executing action type with domain preference
     * 
     * Requirements: 23.2, 23.3
     * 
     * @param string $actionType Action type to match
     * @param string|null $preferredDomain Preferred domain (null for no preference)
     * @return array<ActorInterface> Capable actors (domain-specialized first)
     */
    public function getCapableActorsWithDomainPreference(string $actionType, ?string $preferredDomain = null): array
    {
        if ($preferredDomain === null) {
            return $this->getCapableActors($actionType);
        }
        
        // Get domain-specialized actors first
        $sql = "
            SELECT DISTINCT actor_id, 
                   CASE WHEN domain = :domain THEN 1 ELSE 0 END as is_specialized
            FROM :table_rag_agent_actor_registry 
            WHERE action_type = :action_type
            ORDER BY is_specialized DESC
        ";
        
        $result = $this->db->prepare($sql);
        $result->execute([
            'action_type' => $actionType,
            'domain' => $preferredDomain
        ]);
        
        $capableActors = [];
        while ($row = $result->fetch()) {
            if (isset($this->actors[$row['actor_id']])) {
                $capableActors[] = $this->actors[$row['actor_id']];
            }
        }
        
        return $capableActors;
    }
    
    /**
     * Get actors capable of executing specific action type
     *
     * @param string $actionType Action type to match
     * @return array<ActorInterface> Capable actors
     */
    public function getCapableActors(string $actionType): array
    {
        $sql = "SELECT DISTINCT actor_id FROM :table_rag_agent_actor_registry WHERE action_type = :action_type";
        $result = $this->db->prepare($sql);
        $result->execute(['action_type' => $actionType]);

        $capableActors = [];
        while ($row = $result->fetch()) {
            if (isset($this->actors[$row['actor_id']])) {
                $capableActors[] = $this->actors[$row['actor_id']];
            }
        }

        return $capableActors;
    }
    
    /**
     * Get actor performance for specific domain
     *
     * Requirements: 23.4
     *
     * @param string $actorId Actor ID
     * @param string $domain Domain name
     * @return float Performance score (0.0-1.0)
     */
    public function getActorPerformanceForDomain(string $actorId, string $domain): float
    {
        // Check cache first
        $cacheKey = "performance_{$actorId}_{$domain}";
        if (isset($this->performanceCache[$cacheKey])) {
            $cached = $this->performanceCache[$cacheKey];
            if (time() - $cached['timestamp'] < $this->cacheTimeout) {
                return $cached['score'];
            }
        }

        $sql = "
            SELECT 
                AVG(e.quality_score) as avg_quality,
                COUNT(*) as total_executions,
                SUM(CASE WHEN e.status = 'success' THEN 1 ELSE 0 END) as successful
            FROM :table_rag_agent_actor_executions e
            JOIN :table_rag_agent_actor_registry r ON e.actor_id = r.actor_id AND e.action_type = r.action_type
            WHERE e.actor_id = :actor_id
              AND r.domain = :domain
              AND e.executed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";

        $result = $this->db->prepare($sql);
        $result->execute([
            'actor_id' => $actorId,
            'domain' => $domain
        ]);
        $data = $result->fetch();

        if (!$data || $data['total_executions'] == 0) {
            // Fall back to overall performance
            return $this->getActorPerformance($actorId);
        }

        $successRate = $data['successful'] / $data['total_executions'];
        $qualityScore = $data['avg_quality'] ?? 0.5;

        // Performance score: success rate (60%) + quality (40%)
        $score = ($successRate * 0.6) + ($qualityScore * 0.4);

        // Cache the result
        $this->performanceCache[$cacheKey] = [
            'score' => $score,
            'timestamp' => time()
        ];

        return $score;
    }
    
    /**
     * Get actor performance score based on recent history
     *
     * @param string $actorId Actor ID
     * @return float Performance score (0.0-1.0)
     */
    public function getActorPerformance(string $actorId): float
    {
        // Check cache first
        $cacheKey = "performance_{$actorId}";
        if (isset($this->performanceCache[$cacheKey])) {
            $cached = $this->performanceCache[$cacheKey];
            if (time() - $cached['timestamp'] < $this->cacheTimeout) {
                return $cached['score'];
            }
        }

        $sql = "
            SELECT 
                AVG(quality_score) as avg_quality,
                COUNT(*) as total_executions,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                AVG(execution_time_ms) as avg_execution_time
            FROM :table_rag_agent_actor_executions
            WHERE actor_id = :actor_id
              AND executed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";

        $result = $this->db->prepare($sql);
        $result->execute(['actor_id' => $actorId]);
        $data = $result->fetch();

        if (!$data || $data['total_executions'] == 0) {
            $score = 0.5; // Neutral for new actors
        } else {
            $successRate = $data['successful'] / $data['total_executions'];
            $qualityScore = $data['avg_quality'] ?? 0.5;

            // Performance score: success rate (60%) + quality (40%)
            $score = ($successRate * 0.6) + ($qualityScore * 0.4);
        }

        // Cache the result
        $this->performanceCache[$cacheKey] = [
            'score' => $score,
            'timestamp' => time()
        ];

        return $score;
    }
    
    /**
     * Get performance metrics by domain
     *
     * Requirements: 23.4
     *
     * @param string $domain Domain name
     * @return array Performance metrics for domain
     */
    public function getPerformanceMetricsByDomain(string $domain): array
    {
        $sql = "
            SELECT 
                COUNT(DISTINCT e.actor_id) as total_actors,
                AVG(CASE WHEN e.status = 'success' THEN 1.0 ELSE 0.0 END) as avg_success_rate,
                AVG(e.execution_time_ms) as avg_execution_time,
                AVG(e.quality_score) as avg_quality_score,
                COUNT(*) as total_executions
            FROM :table_rag_agent_actor_executions e
            JOIN :table_rag_agent_actor_registry r ON e.actor_id = r.actor_id AND e.action_type = r.action_type
            WHERE r.domain = :domain
              AND e.executed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ";

        $result = $this->db->prepare($sql);
        $result->execute(['domain' => $domain]);
        $metrics = $result->fetch();

        return [
            'domain' => $domain,
            'total_actors' => (int)$metrics['total_actors'],
            'avg_success_rate' => (float)$metrics['avg_success_rate'],
            'avg_execution_time_ms' => (float)$metrics['avg_execution_time'],
            'avg_quality_score' => (float)$metrics['avg_quality_score'],
            'total_executions_24h' => (int)$metrics['total_executions']
        ];
    }
    
    /**
     * Update actor performance metrics after execution
     *
     * @param string $actorId Actor ID
     * @param string $actionId Action ID
     * @param string $resultId Result ID
     * @param string $actionType Action type
     * @param string $status Execution status
     * @param int $executionTimeMs Execution time in milliseconds
     * @param float|null $qualityScore Quality score if available
     * @param string $outputType Output type
     * @return void
     */
    public function recordExecution(
        string $actorId,
        string $actionId,
        string $resultId,
        string $actionType,
        string $status,
        int $executionTimeMs,
        ?float $qualityScore = null,
        string $outputType = 'unknown'
    ): void {
        $sql = "
            INSERT INTO {$this->prefix}rag_agent_actor_executions (
                action_id, result_id, actor_id, action_type, status,
                execution_time_ms, quality_score, output_type, executed_at
            ) VALUES (
                :action_id, :result_id, :actor_id, :action_type, :status,
                :execution_time_ms, :quality_score, :output_type, NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'action_id' => $actionId,
            'result_id' => $resultId,
            'actor_id' => $actorId,
            'action_type' => $actionType,
            'status' => $status,
            'execution_time_ms' => $executionTimeMs,
            'quality_score' => $qualityScore,
            'output_type' => $outputType
        ]);

        // Clear performance cache for this actor
        unset($this->performanceCache["performance_{$actorId}"]);
    }
    
    /**
     * Get performance metrics for dashboard
     *
     * @return array Performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $sql = "
            SELECT 
                COUNT(DISTINCT actor_id) as total_actors,
                AVG(CASE WHEN status = 'success' THEN 1.0 ELSE 0.0 END) as avg_success_rate,
                AVG(execution_time_ms) as avg_execution_time,
                AVG(quality_score) as avg_quality_score,
                COUNT(*) as total_executions
            FROM {$this->prefix}rag_agent_actor_executions
            WHERE executed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ";

        $result = $this->db->prepare($sql);
        $result->execute();
        $metrics = $result->fetch();

        return [
            'total_actors' => (int)$metrics['total_actors'],
            'avg_success_rate' => (float)$metrics['avg_success_rate'],
            'avg_execution_time_ms' => (float)$metrics['avg_execution_time'],
            'avg_quality_score' => (float)$metrics['avg_quality_score'],
            'total_executions_24h' => (int)$metrics['total_executions'],
            'current_load' => array_sum($this->loadTracking)
        ];
    }
}

<?php
declare(strict_types=1);

namespace ClicShopping\AI\RegistryAI;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\InterfacesAI\CriticAgentInterface;
use ClicShopping\AI\RegistryAI\Exceptions\InvalidCriticException;
use ClicShopping\AI\RegistryAI\Exceptions\InsufficientCriticsException;

/**
 * Registry for critic agents with registration, querying, and load tracking
 * 
 * Manages all critic agents in the system, tracks their evaluation capabilities,
 * monitors their load, and provides agreement metrics.
 */
class CriticRegistry
{
    private $db;
    private string $prefix;
    private array $critics = [];
    private array $loadTracking = [];
    private array $agreementCache = [];
    private int $cacheTimeout = 300; // 5 minutes
    
    public function __construct()
    {
        $this->db = Registry::get('Db');
        $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
    }
    
    /**
     * Register a critic agent with validation and persistence
     * 
     * @param CriticAgentInterface $critic Critic to register
     * @return void
     * @throws InvalidCriticException If critic invalid
     */
    public function registerCritic(CriticAgentInterface $critic): void
    {
        $criticId = $critic->getCriticId();
        
        // Validate critic implements interface
        if (!$critic instanceof CriticAgentInterface) {
            throw new InvalidCriticException("Critic must implement CriticAgentInterface");
        }
        
        // Validate critic ID is not empty
        if (empty($criticId)) {
            throw new InvalidCriticException("Critic ID cannot be empty");
        }
        
        // Store in memory
        $this->critics[$criticId] = $critic;
        $this->loadTracking[$criticId] = 0;
        
        // Persist to database
        $criteria = $critic->getEvaluationCriteria();
        foreach ($criteria as $outputType => $criterion) {
            $this->persistCriticCapability($criticId, $outputType, $criterion);
        }
        
        error_log("CriticRegistry: Critic registered: {$criticId} with " . \count($criteria) . " evaluation capabilities");
    }
    
    /**
     * Get critics qualified to evaluate specific output type
     * 
     * @param string $outputType Output type to match
     * @return array<CriticAgentInterface> Qualified critics
     */
    public function getQualifiedCritics(string $outputType): array
    {
        $sql = "SELECT DISTINCT critic_id 
	         FROM :table_rag_agent_critic_registry 
		 WHERE output_type = :output_type
		 ";
        $result = $this->db->prepare($sql);
        $result->bindValue(':output_type', $outputType);
        $result->execute();
        
        $qualifiedCritics = [];
        while ($row = $result->fetch()) {
            if (isset($this->critics[$row['critic_id']])) {
                $qualifiedCritics[] = $this->critics[$row['critic_id']];
            }
        }
        
        return $qualifiedCritics;
    }
    
    /**
     * Get current load for critic (0.0-1.0)
     * 
     * @param string $criticId Critic ID
     * @return float Load percentage
     */
    public function getCriticLoad(string $criticId): float
    {
        $currentLoad = $this->loadTracking[$criticId] ?? 0;
        $maxConcurrent = $this->getMaxConcurrentEvaluations($criticId);
        
        return $maxConcurrent > 0 ? min(1.0, $currentLoad / $maxConcurrent) : 0.0;
    }
    
    /**
     * Get critic agreement with consensus based on recent evaluations
     * 
     * @param string $criticId Critic ID
     * @return float Agreement score (0.0-1.0)
     */
    public function getCriticAgreement(string $criticId): float
    {
        // Check cache first
        $cacheKey = "agreement_{$criticId}";
        if (isset($this->agreementCache[$cacheKey])) {
            $cached = $this->agreementCache[$cacheKey];
            if (time() - $cached['timestamp'] < $this->cacheTimeout) {
                return $cached['score'];
            }
        }
        
        $sql = "
            SELECT 
                AVG(ABS(ce.overall_score - c.consensus_score)) as avg_deviation,
                COUNT(*) as total_evaluations
            FROM :table_rag_agent_critic_evaluations ce
            LEFT JOIN :table_rag_agent_coordinated_results cr ON ce.output_id = cr.result_id
            LEFT JOIN (
                SELECT output_id, AVG(overall_score) as consensus_score
                FROM :table_rag_agent_critic_evaluations
                GROUP BY output_id
            ) c ON ce.output_id = c.output_id
            WHERE ce.critic_id = :critic_id
              AND ce.evaluated_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        $result = $this->db->prepare($sql);
        $result->bindValue(':critic_id', $criticId);
        $result->execute();
	
        $data = $result->fetch();
        
        $score = (!$data || $data['total_evaluations'] == 0 || $data['avg_deviation'] === null)
            ? 0.5 // Neutral for new critics
            : 1.0 - min(1.0, $data['avg_deviation']); // Convert deviation to agreement
        
        // Cache the result
        $this->agreementCache[$cacheKey] = [
            'score' => $score,
            'timestamp' => time()
        ];
        
        return $score;
    }
    
    /**
     * Increment critic load when evaluation starts
     * 
     * @param string $criticId Critic ID
     * @return void
     */
    public function incrementLoad(string $criticId): void
    {
        $this->loadTracking[$criticId] = ($this->loadTracking[$criticId] ?? 0) + 1;
        
        error_log("CriticRegistry: Critic load incremented: {$criticId} -> {$this->loadTracking[$criticId]}");
    }
    
    /**
     * Decrement critic load when evaluation completes
     * 
     * @param string $criticId Critic ID
     * @return void
     */
    public function decrementLoad(string $criticId): void
    {
        $this->loadTracking[$criticId] = max(0, ($this->loadTracking[$criticId] ?? 0) - 1);
        
        error_log("CriticRegistry: Critic load decremented: {$criticId} -> {$this->loadTracking[$criticId]}");
    }
    
    /**
     * Get all registered critics
     * 
     * @return array<string, CriticAgentInterface> Map of critic ID to critic
     */
    public function getAllCritics(): array
    {
        return $this->critics;
    }
    
    /**
     * Get all registered critic IDs from database
     * 
     * @return array<string> List of critic IDs
     */
    public function getAllCriticIds(): array
    {
        $sql = "SELECT DISTINCT critic_id FROM {$this->prefix}rag_agent_critic_registry";
        $result = $this->db->query($sql);
        
        $criticIds = [];
        while ($row = $result->fetch()) {
            $criticIds[] = $row['critic_id'];
        }
        
        return $criticIds;
    }
    
    /**
     * Check if critic is registered
     * 
     * @param string $criticId Critic ID
     * @return bool True if registered
     */
    public function isCriticRegistered(string $criticId): bool
    {
        return isset($this->critics[$criticId]);
    }
    
    /**
     * Get critic by ID
     * 
     * @param string $criticId Critic ID
     * @return CriticAgentInterface|null Critic or null if not found
     */
    public function getCritic(string $criticId): ?CriticAgentInterface
    {
        return $this->critics[$criticId] ?? null;
    }
    
    /**
     * Get critics by domain specialization
     * 
     * Requirements: 24.1, 24.2
     * 
     * @param string $domain Domain name
     * @return array<CriticAgentInterface> Domain-specialized critics
     */
    public function getCriticsByDomain(string $domain): array
    {
        $sql = "SELECT DISTINCT critic_id 
	        FROM :table_rag_agent_critic_registry 
		WHERE domain = :domain";
        $result = $this->db->prepare($sql);
        $result->bindValue(':domain', $domain);
        $result->execute();
        
        $domainCritics = [];
        while ($row = $result->fetch()) {
            if (isset($this->critics[$row['critic_id']])) {
                $domainCritics[] = $this->critics[$row['critic_id']];
            }
        }
        
        return $domainCritics;
    }
    
    /**
     * Get critics qualified to evaluate output type with domain preference
     * 
     * Requirements: 24.2, 24.3
     * 
     * @param string $outputType Output type to match
     * @param string|null $preferredDomain Preferred domain (null for no preference)
     * @return array<CriticAgentInterface> Qualified critics (domain-specialized first)
     */
    public function getQualifiedCriticsWithDomainPreference(string $outputType, ?string $preferredDomain = null): array
    {
        if ($preferredDomain === null) {
            return $this->getQualifiedCritics($outputType);
        }
        
        // Get domain-specialized critics first
        $sql = "
            SELECT DISTINCT critic_id, 
                   CASE WHEN domain = :domain THEN 1 ELSE 0 END as is_specialized
            FROM :table_rag_agent_critic_registry 
            WHERE output_type = :output_type
            ORDER BY is_specialized DESC
        ";
        
        $result = $this->db->prepare($sql);
        $result->bindValue(':output_type', $outputType);
        $result->bindValue(':domain', $preferredDomain);	
        $result->execute();
        
        $qualifiedCritics = [];
        while ($row = $result->fetch()) {
            if (isset($this->critics[$row['critic_id']])) {
                $qualifiedCritics[] = $this->critics[$row['critic_id']];
            }
        }
        
        return $qualifiedCritics;
    }
    
    /**
     * Get critic agreement for specific domain
     * 
     * Requirements: 24.4
     * 
     * @param string $criticId Critic ID
     * @param string $domain Domain name
     * @return float Agreement score (0.0-1.0)
     */
    public function getCriticAgreementForDomain(string $criticId, string $domain): float
    {
        // Check cache first
        $cacheKey = "agreement_{$criticId}_{$domain}";
        if (isset($this->agreementCache[$cacheKey])) {
            $cached = $this->agreementCache[$cacheKey];
            if (time() - $cached['timestamp'] < $this->cacheTimeout) {
                return $cached['score'];
            }
        }
        
        $sql = "
            SELECT 
                AVG(ABS(ce.overall_score - c.consensus_score)) as avg_deviation,
                COUNT(*) as total_evaluations
            FROM :table_rag_agent_critic_evaluations ce
            JOIN :table_rag_agent_critic_registry cr ON ce.critic_id = cr.critic_id AND ce.output_type = cr.output_type
            LEFT JOIN (
                SELECT output_id, AVG(overall_score) as consensus_score
                FROM :table_rag_agent_critic_evaluations
                GROUP BY output_id
            ) c ON ce.output_id = c.output_id
            WHERE ce.critic_id = :critic_id
              AND cr.domain = :domain
              AND ce.evaluated_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        
        $result = $this->db->prepare($sql);
        $result->bindValue(':critic_id', $criticId);
        $result->bindValue(':domain', $domain);
        $result->execute();

        $data = $result->fetch();
        
        if (!$data || $data['total_evaluations'] == 0 || $data['avg_deviation'] === null) {
            // Fall back to overall agreement
            return $this->getCriticAgreement($criticId);
        }
        
        // Convert deviation to agreement (lower deviation = higher agreement)
        $score = 1.0 - min(1.0, $data['avg_deviation']);
        
        // Cache the result
        $this->agreementCache[$cacheKey] = [
            'score' => $score,
            'timestamp' => time()
        ];
        
        return $score;
    }
    
    /**
     * Get performance metrics by domain
     * 
     * Requirements: 24.4
     * 
     * @param string $domain Domain name
     * @return array Performance metrics for domain
     */
    public function getPerformanceMetricsByDomain(string $domain): array
    {
        $sql = "
            SELECT 
                COUNT(DISTINCT ce.critic_id) as total_critics,
                AVG(ce.overall_score) as avg_evaluation_score,
                AVG(ce.evaluation_time_ms) as avg_evaluation_time,
                COUNT(*) as total_evaluations,
                AVG(ce.accuracy_score) as avg_accuracy,
                AVG(ce.completeness_score) as avg_completeness,
                AVG(ce.efficiency_score) as avg_efficiency,
                AVG(ce.clarity_score) as avg_clarity
            FROM :table_rag_agent_critic_evaluations ce
            JOIN :table_rag_agent_critic_registry cr ON ce.critic_id = cr.critic_id AND ce.output_type = cr.output_type
            WHERE cr.domain = :domain
              AND ce.evaluated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ";
        
        $result = $this->db->prepare($sql);
        $result->bindValue(':domain', $domain);
        $result->execute();
        $metrics = $result->fetch();
        
        return [
            'domain' => $domain,
            'total_critics' => (int)$metrics['total_critics'],
            'avg_evaluation_score' => (float)$metrics['avg_evaluation_score'],
            'avg_evaluation_time_ms' => (float)$metrics['avg_evaluation_time'],
            'total_evaluations_24h' => (int)$metrics['total_evaluations'],
            'avg_accuracy' => (float)$metrics['avg_accuracy'],
            'avg_completeness' => (float)$metrics['avg_completeness'],
            'avg_efficiency' => (float)$metrics['avg_efficiency'],
            'avg_clarity' => (float)$metrics['avg_clarity']
        ];
    }
    
    /**
     * Get critics by expertise level for output type
     * 
     * @param string $outputType Output type
     * @param float $minExpertise Minimum expertise level
     * @return array<CriticAgentInterface> Expert critics
     */
    public function getCriticsByExpertise(string $outputType, float $minExpertise = 0.7): array
    {
        $sql = "
            SELECT DISTINCT critic_id 
            FROM :table_rag_agent_critic_registry 
            WHERE output_type = :output_type 
              AND expertise_level >= :min_expertise
        ";
        
        $result = $this->db->prepare($sql);        
	$result->bindValue(':output_type', $outputType);
        $result->bindValue(':min_expertise', $minExpertise);	
        $result->execute();
        
        $expertCritics = [];
        while ($row = $result->fetch()) {
            if (isset($this->critics[$row['critic_id']])) {
                $expertCritics[] = $this->critics[$row['critic_id']];
            }
        }
        
        return $expertCritics;
    }
    
    /**
     * Record critic evaluation for performance tracking
     * 
     * @param string $criticId Critic ID
     * @param string $evaluationId Evaluation ID
     * @param string $outputId Output ID
     * @param string $outputType Output type
     * @param string $producerAgentId Producer agent ID
     * @param array $scores Dimension scores
     * @param float $overallScore Overall score
     * @param string $feedback Feedback text
     * @param array $strengths Strengths array
     * @param array $improvements Improvements array
     * @param int $evaluationTimeMs Evaluation time in milliseconds
     * @return void
     */
    public function recordEvaluation(
        string $criticId,
        string $evaluationId,
        string $outputId,
        string $outputType,
        string $producerAgentId,
        array $scores,
        float $overallScore,
        string $feedback,
        array $strengths,
        array $improvements,
        int $evaluationTimeMs
    ): void {
        $sql = "
            INSERT INTO :table_rag_agent_critic_evaluations (
                evaluation_id, critic_id, output_id, output_type, producer_agent_id,
                accuracy_score, completeness_score, efficiency_score, clarity_score,
                overall_score, feedback, strengths, improvements, evaluation_time_ms, evaluated_at
            ) VALUES (
                :evaluation_id, :critic_id, :output_id, :output_type, :producer_agent_id,
                :accuracy_score, :completeness_score, :efficiency_score, :clarity_score,
                :overall_score, :feedback, :strengths, :improvements, :evaluation_time_ms, NOW()
            )
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'evaluation_id' => $evaluationId,
            'critic_id' => $criticId,
            'output_id' => $outputId,
            'output_type' => $outputType,
            'producer_agent_id' => $producerAgentId,
            'accuracy_score' => $scores['accuracy'] ?? 0.0,
            'completeness_score' => $scores['completeness'] ?? 0.0,
            'efficiency_score' => $scores['efficiency'] ?? 0.0,
            'clarity_score' => $scores['clarity'] ?? 0.0,
            'overall_score' => $overallScore,
            'feedback' => $feedback,
            'strengths' => json_encode($strengths),
            'improvements' => json_encode($improvements),
            'evaluation_time_ms' => $evaluationTimeMs
        ]);
        
        // Clear agreement cache for this critic
        unset($this->agreementCache["agreement_{$criticId}"]);
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
                COUNT(DISTINCT critic_id) as total_critics,
                AVG(overall_score) as avg_evaluation_score,
                AVG(evaluation_time_ms) as avg_evaluation_time,
                COUNT(*) as total_evaluations,
                AVG(accuracy_score) as avg_accuracy,
                AVG(completeness_score) as avg_completeness,
                AVG(efficiency_score) as avg_efficiency,
                AVG(clarity_score) as avg_clarity
            FROM :table_rag_agent_critic_evaluations
            WHERE evaluated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ";
        
        $result = $this->db->prepare($sql);
        $result->execute();
        $metrics = $result->fetch();
        
        return [
            'total_critics' => (int)$metrics['total_critics'],
            'avg_evaluation_score' => (float)$metrics['avg_evaluation_score'],
            'avg_evaluation_time_ms' => (float)$metrics['avg_evaluation_time'],
            'total_evaluations_24h' => (int)$metrics['total_evaluations'],
            'avg_accuracy' => (float)$metrics['avg_accuracy'],
            'avg_completeness' => (float)$metrics['avg_completeness'],
            'avg_efficiency' => (float)$metrics['avg_efficiency'],
            'avg_clarity' => (float)$metrics['avg_clarity'],
            'current_load' => array_sum($this->loadTracking)
        ];
    }
    
    /**
     * Select diverse critics for balanced evaluation
     * 
     * @param array $qualifiedCritics Qualified critics
     * @param int $count Number to select
     * @param string $excludeActorId Actor ID to exclude (self-evaluation prevention)
     * @return array<CriticAgentInterface> Selected critics
     * @throws InsufficientCriticsException If too few critics available
     */
    public function selectDiverseCritics(array $qualifiedCritics, int $count, string $excludeActorId = ''): array
    {
        // Filter out excluded actor
        $validCritics = array_filter($qualifiedCritics, fn($c) => $c->getCriticId() !== $excludeActorId);
        
        if (\count($validCritics) < 2) {
            throw new InsufficientCriticsException(
                "Insufficient critics available. Required: 2, Available: " . \count($validCritics)
            );
        }
        
        // Score critics by expertise, agreement, and load
        $scoredCritics = [];
        foreach ($validCritics as $critic) {
            $criticId = $critic->getCriticId();
            $load = $this->getCriticLoad($criticId);
            $agreement = $this->getCriticAgreement($criticId);
            
            // Get expertise level from first evaluation criteria
            $criteria = $critic->getEvaluationCriteria();
            $expertise = 0.5; // Default
            if (!empty($criteria)) {
                $firstCriterion = reset($criteria);
                if (\is_object($firstCriterion) && method_exists($firstCriterion, 'getExpertiseLevel')) {
                    $expertise = $firstCriterion->getExpertiseLevel();
                }
            }
            
            // Combined score: expertise (40%) + agreement (40%) + load (20%)
            $score = ($expertise * 0.4) + ($agreement * 0.4) + ((1.0 - $load) * 0.2);
            $scoredCritics[] = ['critic' => $critic, 'score' => $score];
        }
        
        // Sort by score descending
        usort($scoredCritics, fn($a, $b) => $b['score'] <=> $a['score']);
        
        // Select top N critics
        $selected = \array_slice($scoredCritics, 0, min($count, \count($scoredCritics)));
        
        return array_map(fn($s) => $s['critic'], $selected);
    }
    
    /**
     * Persist critic capability to database
     * 
     * @param string $criticId Critic ID
     * @param string $outputType Output type
     * @param mixed $criterion Evaluation criterion object
     * @return void
     */
    private function persistCriticCapability(string $criticId, string $outputType, $criterion): void
    {
        // Handle both EvaluationCriteria objects and simple arrays
        $expertiseLevel = 0.5;
        $domain = null;
        
        if (\is_object($criterion) && method_exists($criterion, 'getExpertiseLevel')) {
            $expertiseLevel = $criterion->getExpertiseLevel();
            if (method_exists($criterion, 'getDomain')) {
                $domain = $criterion->getDomain();
            }
        } elseif (\is_array($criterion)) {
            $expertiseLevel = $criterion['expertise_level'] ?? 0.5;
            $domain = $criterion['domain'] ?? null;
        }
        
        $sql = "
            INSERT INTO :table_rag_agent_critic_registry (
                critic_id, output_type, expertise_level, domain, registered_at
            ) VALUES (
                :critic_id, :output_type, :expertise_level, :domain, NOW()
            ) ON DUPLICATE KEY UPDATE
                expertise_level = VALUES(expertise_level),
                domain = VALUES(domain),
                updated_at = NOW()
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'critic_id' => $criticId,
            'output_type' => $outputType,
            'expertise_level' => $expertiseLevel,
            'domain' => $domain
        ]);
    }
    
    /**
     * Get maximum concurrent evaluations for critic
     * 
     * @param string $criticId Critic ID
     * @return int Maximum concurrent evaluations
     */
    private function getMaxConcurrentEvaluations(string $criticId): int
    {
        // Default to 10, could be configurable per critic
        return 10;
    }
}

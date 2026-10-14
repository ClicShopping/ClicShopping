<?php
declare(strict_types=1);

namespace ClicShopping\AI\LoadBalancing;

/**
 * Load shedding manager for extreme load scenarios
 * 
 * Implements intelligent load shedding strategies to maintain system
 * stability during extreme load conditions by rejecting or deferring
 * low-priority requests.
 */
class LoadShedder
{
    private float $criticalLoadThreshold;
    private float $warningLoadThreshold;
    private array $sheddingMetrics = [];
    private bool $enabled;
    
    /**
     * Constructor
     * 
     * @param float $criticalLoadThreshold Critical load threshold (0.0-1.0)
     * @param float $warningLoadThreshold Warning load threshold (0.0-1.0)
     * @param bool $enabled Enable load shedding
     */
    public function __construct(
        float $criticalLoadThreshold = 0.9,
        float $warningLoadThreshold = 0.75,
        bool $enabled = true
    ) {
        $this->criticalLoadThreshold = $criticalLoadThreshold;
        $this->warningLoadThreshold = $warningLoadThreshold;
        $this->enabled = $enabled;
        $this->initializeMetrics();
    }
    
    /**
     * Check if request should be shed based on current load
     * 
     * @param float $currentLoad Current system load (0.0-1.0)
     * @param string $priority Request priority (high, medium, low)
     * @param string $agentId Agent ID
     * @return bool True if request should be shed
     */
    public function shouldShedLoad(float $currentLoad, string $priority, string $agentId): bool
    {
        if (!$this->enabled) {
            return false;
        }
        
        // Never shed high priority requests
        if ($priority === 'high' || $priority === 'critical') {
            return false;
        }
        
        // Critical load: shed medium and low priority
        if ($currentLoad >= $this->criticalLoadThreshold) {
            $this->recordShedding($agentId, $priority, 'critical_load');
            error_log("LoadShedder: Shedding {$priority} priority request for {$agentId} (critical load: {$currentLoad})");
            return true;
        }
        
        // Warning load: shed low priority only
        if ($currentLoad >= $this->warningLoadThreshold && $priority === 'low') {
            $this->recordShedding($agentId, $priority, 'warning_load');
            error_log("LoadShedder: Shedding low priority request for {$agentId} (warning load: {$currentLoad})");
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if agent is overloaded and should reject new requests
     * 
     * @param string $agentId Agent ID
     * @param float $agentLoad Agent load (0.0-1.0)
     * @param int $queueSize Current queue size
     * @param int $maxQueueSize Maximum queue size
     * @return bool True if agent is overloaded
     */
    public function isAgentOverloaded(
        string $agentId,
        float $agentLoad,
        int $queueSize,
        int $maxQueueSize
    ): bool {
        if (!$this->enabled) {
            return false;
        }
        
        // Agent at capacity and queue full
        if ($agentLoad >= 1.0 && $queueSize >= $maxQueueSize) {
            $this->recordShedding($agentId, 'any', 'agent_overloaded');
            error_log("LoadShedder: Agent {$agentId} overloaded (load: {$agentLoad}, queue: {$queueSize}/{$maxQueueSize})");
            return true;
        }
        
        // Agent critically loaded and queue nearly full
        if ($agentLoad >= $this->criticalLoadThreshold && $queueSize >= ($maxQueueSize * 0.9)) {
            $this->recordShedding($agentId, 'any', 'agent_critical');
            error_log("LoadShedder: Agent {$agentId} critically loaded (load: {$agentLoad}, queue: {$queueSize}/{$maxQueueSize})");
            return true;
        }
        
        return false;
    }
    
    /**
     * Get load shedding recommendation based on system state
     * 
     * @param array $systemMetrics System metrics
     * @return array Recommendation with action and reason
     */
    public function getLoadSheddingRecommendation(array $systemMetrics): array
    {
        $avgLoad = $systemMetrics['avg_load'] ?? 0.0;
        $totalQueued = $systemMetrics['total_queued'] ?? 0;
        $activeAgents = $systemMetrics['active_agents'] ?? 1;
        
        if ($avgLoad >= $this->criticalLoadThreshold) {
            return [
                'action' => 'shed_medium_and_low',
                'reason' => 'System at critical load',
                'severity' => 'critical',
                'recommended_threshold' => $this->criticalLoadThreshold
            ];
        }
        
        if ($avgLoad >= $this->warningLoadThreshold) {
            return [
                'action' => 'shed_low',
                'reason' => 'System at warning load',
                'severity' => 'warning',
                'recommended_threshold' => $this->warningLoadThreshold
            ];
        }
        
        if ($totalQueued > ($activeAgents * 50)) {
            return [
                'action' => 'shed_low',
                'reason' => 'Queue backlog excessive',
                'severity' => 'warning',
                'recommended_threshold' => 0.0
            ];
        }
        
        return [
            'action' => 'none',
            'reason' => 'System load normal',
            'severity' => 'normal',
            'recommended_threshold' => 0.0
        ];
    }
    
    /**
     * Get shedding metrics
     * 
     * @return array Shedding metrics
     */
    public function getSheddingMetrics(): array
    {
        return [
            'total_shed' => $this->sheddingMetrics['total_shed'],
            'by_priority' => $this->sheddingMetrics['by_priority'],
            'by_reason' => $this->sheddingMetrics['by_reason'],
            'by_agent' => $this->sheddingMetrics['by_agent'],
            'enabled' => $this->enabled,
            'critical_threshold' => $this->criticalLoadThreshold,
            'warning_threshold' => $this->warningLoadThreshold
        ];
    }
    
    /**
     * Reset shedding metrics
     * 
     * @return void
     */
    public function resetMetrics(): void
    {
        $this->initializeMetrics();
    }
    
    /**
     * Enable load shedding
     * 
     * @return void
     */
    public function enable(): void
    {
        $this->enabled = true;
        error_log("LoadShedder: Load shedding enabled");
    }
    
    /**
     * Disable load shedding
     * 
     * @return void
     */
    public function disable(): void
    {
        $this->enabled = false;
        error_log("LoadShedder: Load shedding disabled");
    }
    
    /**
     * Check if load shedding is enabled
     * 
     * @return bool True if enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    /**
     * Update thresholds
     * 
     * @param float $criticalThreshold Critical threshold
     * @param float $warningThreshold Warning threshold
     * @return void
     */
    public function updateThresholds(float $criticalThreshold, float $warningThreshold): void
    {
        $this->criticalLoadThreshold = $criticalThreshold;
        $this->warningLoadThreshold = $warningThreshold;
        error_log("LoadShedder: Thresholds updated (critical: {$criticalThreshold}, warning: {$warningThreshold})");
    }
    
    /**
     * Record shedding event
     * 
     * @param string $agentId Agent ID
     * @param string $priority Priority
     * @param string $reason Reason
     * @return void
     */
    private function recordShedding(string $agentId, string $priority, string $reason): void
    {
        $this->sheddingMetrics['total_shed']++;
        
        if (!isset($this->sheddingMetrics['by_priority'][$priority])) {
            $this->sheddingMetrics['by_priority'][$priority] = 0;
        }
        $this->sheddingMetrics['by_priority'][$priority]++;
        
        if (!isset($this->sheddingMetrics['by_reason'][$reason])) {
            $this->sheddingMetrics['by_reason'][$reason] = 0;
        }
        $this->sheddingMetrics['by_reason'][$reason]++;
        
        if (!isset($this->sheddingMetrics['by_agent'][$agentId])) {
            $this->sheddingMetrics['by_agent'][$agentId] = 0;
        }
        $this->sheddingMetrics['by_agent'][$agentId]++;
    }
    
    /**
     * Initialize metrics
     * 
     * @return void
     */
    private function initializeMetrics(): void
    {
        $this->sheddingMetrics = [
            'total_shed' => 0,
            'by_priority' => [],
            'by_reason' => [],
            'by_agent' => []
        ];
    }
}

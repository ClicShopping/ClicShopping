<?php
declare(strict_types=1);

namespace ClicShopping\AI\LoadBalancing;

use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\RegistryAI\CriticRegistry;

/**
 * Enhanced load balancer with sophisticated algorithms and queue management
 * 
 * Provides advanced load balancing capabilities including:
 * - Multiple load balancing strategies
 * - Queue management for overloaded agents
 * - Load shedding for extreme scenarios
 * - Maximum concurrent limits enforcement
 */
class EnhancedLoadBalancer
{
    private ActorRegistry $actorRegistry;
    private CriticRegistry $criticRegistry;
    private LoadBalancingStrategy $strategy;
    private AgentQueue $queue;
    private LoadShedder $loadShedder;
    private array $config;
    
    /**
     * Constructor
     * 
     * @param ActorRegistry $actorRegistry Actor registry
     * @param CriticRegistry $criticRegistry Critic registry
     * @param array $config Configuration options
     */
    public function __construct(
        ActorRegistry $actorRegistry,
        CriticRegistry $criticRegistry,
        array $config = []
    ) {
        $this->actorRegistry = $actorRegistry;
        $this->criticRegistry = $criticRegistry;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        // Initialize components
        $this->strategy = $this->createStrategy($this->config['strategy']);
        $this->queue = new AgentQueue(
            $this->config['max_queue_size'],
            $this->config['queue_timeout']
        );
        $this->loadShedder = new LoadShedder(
            $this->config['critical_load_threshold'],
            $this->config['warning_load_threshold'],
            $this->config['load_shedding_enabled']
        );
        
        error_log("EnhancedLoadBalancer: Initialized with strategy: {$this->strategy->getName()}");
    }
    
    /**
     * Select actor with load balancing
     * 
     * @param array $capableActors Capable actors
     * @param string $priority Request priority
     * @return mixed|null Selected actor or null if all overloaded
     */
    public function selectActor(array $capableActors, string $priority = 'medium')
    {
        if (empty($capableActors)) {
            return null;
        }
        
        // Build load information
        $loadInfo = [];
        $availableActors = [];
        
        foreach ($capableActors as $actor) {
            $actorId = $actor->getActorId();
            $load = $this->actorRegistry->getActorLoad($actorId);
            $queueSize = $this->queue->getQueueSize($actorId);
            
            $loadInfo[$actorId] = [
                'load' => $load,
                'capacity' => 1.0,
                'active_connections' => (int)($load * $this->config['max_concurrent_actions']),
                'queue_size' => $queueSize
            ];
            
            // Check if actor can accept new work
            if ($this->canAcceptWork($actorId, $load, $queueSize, $priority)) {
                $availableActors[] = $actor;
            }
        }
        
        // If no actors available, check load shedding
        if (empty($availableActors)) {
            $systemLoad = $this->calculateSystemLoad($loadInfo);
            if ($this->loadShedder->shouldShedLoad($systemLoad, $priority, 'system')) {
                error_log("EnhancedLoadBalancer: Load shed - no actors available for priority: {$priority}");
                return null;
            }
            
            // Try to dequeue from least loaded actor
            return $this->selectFromQueue($capableActors);
        }
        
        // Use strategy to select best actor
        $selected = $this->strategy->selectAgent($availableActors, $loadInfo);
        
        if ($selected) {
            $selectedId = $selected->getActorId();
            error_log("EnhancedLoadBalancer: Selected actor {$selectedId} (load: {$loadInfo[$selectedId]['load']})");
        }
        
        return $selected;
    }
    
    /**
     * Select critics with load balancing
     * 
     * @param array $qualifiedCritics Qualified critics
     * @param int $count Number to select
     * @param string $excludeActorId Actor to exclude
     * @param string $priority Request priority
     * @return array Selected critics
     */
    public function selectCritics(
        array $qualifiedCritics,
        int $count,
        string $excludeActorId = '',
        string $priority = 'medium'
    ): array {
        if (empty($qualifiedCritics)) {
            return [];
        }
        
        // Filter out excluded actor
        $validCritics = array_filter($qualifiedCritics, fn($c) => $c->getCriticId() !== $excludeActorId);
        
        // Build load information
        $loadInfo = [];
        $availableCritics = [];
        
        foreach ($validCritics as $critic) {
            $criticId = $critic->getCriticId();
            $load = $this->criticRegistry->getCriticLoad($criticId);
            $queueSize = $this->queue->getQueueSize($criticId);
            
            $loadInfo[$criticId] = [
                'load' => $load,
                'capacity' => 1.0,
                'active_connections' => (int)($load * $this->config['max_concurrent_evaluations']),
                'queue_size' => $queueSize
            ];
            
            // Check if critic can accept new work
            if ($this->canAcceptWork($criticId, $load, $queueSize, $priority)) {
                $availableCritics[] = $critic;
            }
        }
        
        // Select multiple critics using strategy
        $selected = [];
        $attempts = 0;
        $maxAttempts = count($availableCritics);
        
        while (count($selected) < $count && $attempts < $maxAttempts) {
            $critic = $this->strategy->selectAgent($availableCritics, $loadInfo);
            
            if ($critic && !in_array($critic, $selected, true)) {
                $selected[] = $critic;
                
                // Remove from available to ensure diversity
                $availableCritics = array_filter($availableCritics, fn($c) => $c !== $critic);
            }
            
            $attempts++;
        }
        
        error_log("EnhancedLoadBalancer: Selected " . count($selected) . " critics (requested: {$count})");
        
        return $selected;
    }
    
    /**
     * Queue action for overloaded actor
     * 
     * @param string $actorId Actor ID
     * @param mixed $action Action to queue
     * @param string $priority Priority
     * @return bool True if queued successfully
     */
    public function queueAction(string $actorId, $action, string $priority = 'medium'): bool
    {
        return $this->queue->enqueue($actorId, $action, $priority);
    }
    
    /**
     * Queue evaluation for overloaded critic
     * 
     * @param string $criticId Critic ID
     * @param mixed $evaluation Evaluation to queue
     * @param string $priority Priority
     * @return bool True if queued successfully
     */
    public function queueEvaluation(string $criticId, $evaluation, string $priority = 'medium'): bool
    {
        return $this->queue->enqueue($criticId, $evaluation, $priority);
    }
    
    /**
     * Process queued items for agent
     * 
     * @param string $agentId Agent ID
     * @return mixed|null Next queued item or null
     */
    public function processQueue(string $agentId)
    {
        return $this->queue->dequeue($agentId);
    }
    
    /**
     * Get load balancing metrics
     * 
     * @return array Metrics
     */
    public function getMetrics(): array
    {
        $actorMetrics = $this->actorRegistry->getPerformanceMetrics();
        $criticMetrics = $this->criticRegistry->getPerformanceMetrics();
        $queueMetrics = $this->queue->getAllQueueMetrics();
        $sheddingMetrics = $this->loadShedder->getSheddingMetrics();
        
        return [
            'actors' => $actorMetrics,
            'critics' => $criticMetrics,
            'queues' => $queueMetrics,
            'load_shedding' => $sheddingMetrics,
            'strategy' => $this->strategy->getName(),
            'total_queued' => $this->queue->getTotalQueuedItems(),
            'system_load' => $this->calculateSystemLoad([])
        ];
    }
    
    /**
     * Update load balancing strategy
     * 
     * @param string $strategyName Strategy name
     * @return void
     */
    public function setStrategy(string $strategyName): void
    {
        $this->strategy = $this->createStrategy($strategyName);
        error_log("EnhancedLoadBalancer: Strategy changed to: {$strategyName}");
    }
    
    /**
     * Update configuration
     * 
     * @param array $config Configuration updates
     * @return void
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        
        // Update load shedder thresholds if changed
        if (isset($config['critical_load_threshold']) || isset($config['warning_load_threshold'])) {
            $this->loadShedder->updateThresholds(
                $this->config['critical_load_threshold'],
                $this->config['warning_load_threshold']
            );
        }
        
        error_log("EnhancedLoadBalancer: Configuration updated");
    }
    
    /**
     * Check if agent can accept new work
     * 
     * @param string $agentId Agent ID
     * @param float $load Current load
     * @param int $queueSize Queue size
     * @param string $priority Priority
     * @return bool True if can accept work
     */
    private function canAcceptWork(string $agentId, float $load, int $queueSize, string $priority): bool
    {
        // Check if agent is overloaded
        if ($this->loadShedder->isAgentOverloaded(
            $agentId,
            $load,
            $queueSize,
            $this->config['max_queue_size']
        )) {
            return false;
        }
        
        // Check maximum concurrent limit
        $maxConcurrent = $this->config['max_concurrent_actions'];
        if ($load >= 1.0) {
            // At capacity, only accept high priority if queue has space
            return $priority === 'high' && $queueSize < $this->config['max_queue_size'];
        }
        
        return true;
    }
    
    /**
     * Select actor from queue
     * 
     * @param array $actors Actors
     * @return mixed|null Actor with queued items or null
     */
    private function selectFromQueue(array $actors)
    {
        foreach ($actors as $actor) {
            $actorId = $actor->getActorId();
            if ($this->queue->hasQueuedItems($actorId)) {
                return $actor;
            }
        }
        return null;
    }
    
    /**
     * Calculate system load
     * 
     * @param array $loadInfo Load information
     * @return float System load (0.0-1.0)
     */
    private function calculateSystemLoad(array $loadInfo): float
    {
        if (empty($loadInfo)) {
            // Calculate from registries
            $actorMetrics = $this->actorRegistry->getPerformanceMetrics();
            $criticMetrics = $this->criticRegistry->getPerformanceMetrics();
            
            $totalLoad = ($actorMetrics['current_load'] ?? 0) + ($criticMetrics['current_load'] ?? 0);
            $totalCapacity = ($actorMetrics['total_actors'] ?? 1) * $this->config['max_concurrent_actions'] +
                           ($criticMetrics['total_critics'] ?? 1) * $this->config['max_concurrent_evaluations'];
            
            return $totalCapacity > 0 ? min(1.0, $totalLoad / $totalCapacity) : 0.0;
        }
        
        $loads = array_column($loadInfo, 'load');
        return !empty($loads) ? array_sum($loads) / count($loads) : 0.0;
    }
    
    /**
     * Create load balancing strategy
     * 
     * @param string $strategyName Strategy name
     * @return LoadBalancingStrategy Strategy instance
     */
    private function createStrategy(string $strategyName): LoadBalancingStrategy
    {
        switch ($strategyName) {
            case 'least_connections':
                return new LeastConnectionsStrategy();
            case 'weighted_round_robin':
            default:
                return new WeightedRoundRobinStrategy();
        }
    }
    
    /**
     * Get default configuration
     * 
     * @return array Default config
     */
    private function getDefaultConfig(): array
    {
        return [
            'strategy' => 'weighted_round_robin',
            'max_concurrent_actions' => 5,
            'max_concurrent_evaluations' => 10,
            'max_queue_size' => 100,
            'queue_timeout' => 300,
            'critical_load_threshold' => 0.9,
            'warning_load_threshold' => 0.75,
            'load_shedding_enabled' => true
        ];
    }
}

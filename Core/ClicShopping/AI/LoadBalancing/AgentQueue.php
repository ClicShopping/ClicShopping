<?php
declare(strict_types=1);

namespace ClicShopping\AI\LoadBalancing;

/**
 * Queue manager for overloaded agents
 * 
 * Manages queuing of actions/evaluations when agents are at capacity,
 * with priority-based processing and timeout handling.
 */
class AgentQueue
{
    private array $queues = [];
    private array $queueMetrics = [];
    private int $maxQueueSize;
    private int $defaultTimeout;
    
    /**
     * Constructor
     * 
     * @param int $maxQueueSize Maximum queue size per agent
     * @param int $defaultTimeout Default timeout in seconds
     */
    public function __construct(int $maxQueueSize = 100, int $defaultTimeout = 300)
    {
        $this->maxQueueSize = $maxQueueSize;
        $this->defaultTimeout = $defaultTimeout;
    }
    
    /**
     * Enqueue an item for processing
     * 
     * @param string $agentId Agent ID
     * @param mixed $item Item to queue
     * @param string $priority Priority level (high, medium, low)
     * @param int|null $timeout Timeout in seconds
     * @return bool True if enqueued successfully
     */
    public function enqueue(string $agentId, $item, string $priority = 'medium', ?int $timeout = null): bool
    {
        if (!isset($this->queues[$agentId])) {
            $this->queues[$agentId] = [];
            $this->queueMetrics[$agentId] = [
                'total_enqueued' => 0,
                'total_dequeued' => 0,
                'total_timeouts' => 0,
                'total_dropped' => 0
            ];
        }
        
        // Check queue size limit
        if (count($this->queues[$agentId]) >= $this->maxQueueSize) {
            $this->queueMetrics[$agentId]['total_dropped']++;
            error_log("AgentQueue: Queue full for agent {$agentId}, item dropped");
            return false;
        }
        
        // Create queue entry
        $entry = [
            'item' => $item,
            'priority' => $priority,
            'enqueued_at' => time(),
            'timeout' => $timeout ?? $this->defaultTimeout,
            'id' => uniqid('queue_', true)
        ];
        
        $this->queues[$agentId][] = $entry;
        $this->queueMetrics[$agentId]['total_enqueued']++;
        
        // Sort by priority
        $this->sortQueue($agentId);
        
        error_log("AgentQueue: Item enqueued for agent {$agentId}, queue size: " . count($this->queues[$agentId]));
        
        return true;
    }
    
    /**
     * Dequeue next item for processing
     * 
     * @param string $agentId Agent ID
     * @return mixed|null Next item or null if queue empty
     */
    public function dequeue(string $agentId)
    {
        if (!isset($this->queues[$agentId]) || empty($this->queues[$agentId])) {
            return null;
        }
        
        // Remove expired items first
        $this->removeExpiredItems($agentId);
        
        if (empty($this->queues[$agentId])) {
            return null;
        }
        
        // Get highest priority item
        $entry = array_shift($this->queues[$agentId]);
        $this->queueMetrics[$agentId]['total_dequeued']++;
        
        error_log("AgentQueue: Item dequeued for agent {$agentId}, remaining: " . count($this->queues[$agentId]));
        
        return $entry['item'];
    }
    
    /**
     * Get queue size for agent
     * 
     * @param string $agentId Agent ID
     * @return int Queue size
     */
    public function getQueueSize(string $agentId): int
    {
        return isset($this->queues[$agentId]) ? count($this->queues[$agentId]) : 0;
    }
    
    /**
     * Check if agent has queued items
     * 
     * @param string $agentId Agent ID
     * @return bool True if queue has items
     */
    public function hasQueuedItems(string $agentId): bool
    {
        return $this->getQueueSize($agentId) > 0;
    }
    
    /**
     * Get queue metrics for agent
     * 
     * @param string $agentId Agent ID
     * @return array Queue metrics
     */
    public function getQueueMetrics(string $agentId): array
    {
        return $this->queueMetrics[$agentId] ?? [
            'total_enqueued' => 0,
            'total_dequeued' => 0,
            'total_timeouts' => 0,
            'total_dropped' => 0,
            'current_size' => 0
        ];
    }
    
    /**
     * Get all queue metrics
     * 
     * @return array All queue metrics
     */
    public function getAllQueueMetrics(): array
    {
        $metrics = [];
        foreach ($this->queueMetrics as $agentId => $agentMetrics) {
            $metrics[$agentId] = array_merge($agentMetrics, [
                'current_size' => $this->getQueueSize($agentId)
            ]);
        }
        return $metrics;
    }
    
    /**
     * Clear queue for agent
     * 
     * @param string $agentId Agent ID
     * @return int Number of items cleared
     */
    public function clearQueue(string $agentId): int
    {
        $count = $this->getQueueSize($agentId);
        unset($this->queues[$agentId]);
        return $count;
    }
    
    /**
     * Get total queued items across all agents
     * 
     * @return int Total queued items
     */
    public function getTotalQueuedItems(): int
    {
        $total = 0;
        foreach ($this->queues as $queue) {
            $total += count($queue);
        }
        return $total;
    }
    
    /**
     * Sort queue by priority
     * 
     * @param string $agentId Agent ID
     * @return void
     */
    private function sortQueue(string $agentId): void
    {
        if (!isset($this->queues[$agentId])) {
            return;
        }
        
        $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
        
        usort($this->queues[$agentId], function($a, $b) use ($priorityOrder) {
            $aPriority = $priorityOrder[$a['priority']] ?? 2;
            $bPriority = $priorityOrder[$b['priority']] ?? 2;
            
            if ($aPriority !== $bPriority) {
                return $bPriority <=> $aPriority; // Higher priority first
            }
            
            // Same priority, FIFO
            return $a['enqueued_at'] <=> $b['enqueued_at'];
        });
    }
    
    /**
     * Remove expired items from queue
     * 
     * @param string $agentId Agent ID
     * @return void
     */
    private function removeExpiredItems(string $agentId): void
    {
        if (!isset($this->queues[$agentId])) {
            return;
        }
        
        $currentTime = time();
        $originalCount = count($this->queues[$agentId]);
        
        $this->queues[$agentId] = array_filter($this->queues[$agentId], function($entry) use ($currentTime) {
            $age = $currentTime - $entry['enqueued_at'];
            return $age < $entry['timeout'];
        });
        
        // Reindex array
        $this->queues[$agentId] = array_values($this->queues[$agentId]);
        
        $expiredCount = $originalCount - count($this->queues[$agentId]);
        if ($expiredCount > 0) {
            $this->queueMetrics[$agentId]['total_timeouts'] += $expiredCount;
            error_log("AgentQueue: Removed {$expiredCount} expired items from agent {$agentId} queue");
        }
    }
}

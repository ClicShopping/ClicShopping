<?php
declare(strict_types=1);

namespace ClicShopping\AI\LoadBalancing;

/**
 * Weighted Round Robin load balancing strategy
 * 
 * Distributes load based on agent capacity and current load,
 * ensuring fair distribution while respecting agent capabilities.
 */
class WeightedRoundRobinStrategy implements LoadBalancingStrategy
{
    private array $roundRobinCounters = [];
    
    /**
     * Select agent using weighted round robin
     * 
     * @param array $candidates Candidate agents with scores
     * @param array $loadInfo Load information
     * @return mixed Selected agent
     */
    public function selectAgent(array $candidates, array $loadInfo)
    {
        if (empty($candidates)) {
            return null;
        }
        
        // Calculate weights based on capacity and inverse load
        $weightedCandidates = [];
        foreach ($candidates as $candidate) {
            $agentId = $this->getAgentId($candidate);
            $load = $loadInfo[$agentId]['load'] ?? 0.0;
            $capacity = $loadInfo[$agentId]['capacity'] ?? 1.0;
            
            // Weight = capacity * (1 - load)
            $weight = $capacity * (1.0 - $load);
            $weightedCandidates[] = [
                'agent' => $candidate,
                'weight' => max(0.1, $weight), // Minimum weight to ensure all get chances
                'agent_id' => $agentId
            ];
        }
        
        // Sort by weight descending
        usort($weightedCandidates, fn($a, $b) => $b['weight'] <=> $a['weight']);
        
        // Use round robin among top weighted candidates
        $topCandidates = array_slice($weightedCandidates, 0, min(3, count($weightedCandidates)));
        
        // Get or initialize counter
        $counterKey = $this->getCounterKey($topCandidates);
        if (!isset($this->roundRobinCounters[$counterKey])) {
            $this->roundRobinCounters[$counterKey] = 0;
        }
        
        // Select using round robin
        $index = $this->roundRobinCounters[$counterKey] % count($topCandidates);
        $this->roundRobinCounters[$counterKey]++;
        
        return $topCandidates[$index]['agent'];
    }
    
    /**
     * Get strategy name
     * 
     * @return string Strategy name
     */
    public function getName(): string
    {
        return 'weighted_round_robin';
    }
    
    /**
     * Get agent ID from candidate
     * 
     * @param mixed $candidate Candidate agent
     * @return string Agent ID
     */
    private function getAgentId($candidate): string
    {
        if (is_array($candidate) && isset($candidate['agent'])) {
            $agent = $candidate['agent'];
        } else {
            $agent = $candidate;
        }
        
        if (is_object($agent)) {
            if (method_exists($agent, 'getActorId')) {
                return $agent->getActorId();
            } elseif (method_exists($agent, 'getCriticId')) {
                return $agent->getCriticId();
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Get counter key for round robin tracking
     * 
     * @param array $candidates Candidates
     * @return string Counter key
     */
    private function getCounterKey(array $candidates): string
    {
        $ids = array_map(fn($c) => $c['agent_id'], $candidates);
        sort($ids);
        return implode('_', $ids);
    }
}

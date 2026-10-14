<?php
declare(strict_types=1);

namespace ClicShopping\AI\LoadBalancing;

/**
 * Least Connections load balancing strategy
 * 
 * Selects the agent with the fewest active connections/tasks,
 * ensuring even distribution of load across agents.
 */
class LeastConnectionsStrategy implements LoadBalancingStrategy
{
    /**
     * Select agent with least connections
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
        
        $minLoad = PHP_FLOAT_MAX;
        $selectedAgent = null;
        
        foreach ($candidates as $candidate) {
            $agentId = $this->getAgentId($candidate);
            $load = $loadInfo[$agentId]['load'] ?? 0.0;
            $activeConnections = $loadInfo[$agentId]['active_connections'] ?? 0;
            
            // Prefer agents with fewer active connections
            if ($activeConnections < $minLoad) {
                $minLoad = $activeConnections;
                $selectedAgent = $candidate;
            } elseif ($activeConnections === $minLoad && $load < ($loadInfo[$this->getAgentId($selectedAgent)]['load'] ?? 1.0)) {
                // If same connections, prefer lower overall load
                $selectedAgent = $candidate;
            }
        }
        
        return $selectedAgent;
    }
    
    /**
     * Get strategy name
     * 
     * @return string Strategy name
     */
    public function getName(): string
    {
        return 'least_connections';
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
}

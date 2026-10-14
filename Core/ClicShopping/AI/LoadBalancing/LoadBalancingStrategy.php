<?php
declare(strict_types=1);

namespace ClicShopping\AI\LoadBalancing;

/**
 * Interface for load balancing strategies
 * 
 * Defines the contract for different load balancing algorithms
 * that can be used to select actors and critics based on load.
 */
interface LoadBalancingStrategy
{
    /**
     * Select best agent from candidates based on load balancing strategy
     * 
     * @param array $candidates Array of candidate agents with scores
     * @param array $loadInfo Load information for each candidate
     * @return mixed Selected agent
     */
    public function selectAgent(array $candidates, array $loadInfo);
    
    /**
     * Get strategy name
     * 
     * @return string Strategy name
     */
    public function getName(): string;
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\AI\InterfacesAI\ActorAgentInterface;
use ClicShopping\AI\InterfacesAI\CriticAgentInterface;
use ClicShopping\AI\RegistryAI\Exceptions\NoCapableActorException;
use ClicShopping\AI\RegistryAI\Exceptions\InsufficientCriticsException;

/**
 * ActorCriticSelector Class
 *
 * Scores and selects actors and critics for the actor-critic workflow.
 * Extracted verbatim from ActorCriticCoordinator (god-class reduction, BACKLOG §Y).
 *
 * Holds the selection/scoring concern only: capability matching, confidence/
 * performance/load scoring for actors, expertise/agreement/load scoring for critics,
 * self-evaluation prevention, diversity selection, and fallback (alternative actor /
 * additional critics) selection used by the coordinator's retry paths.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-06-20
 */
class ActorCriticSelector
{
    private ActorRegistry $actorRegistry;
    private CriticRegistry $criticRegistry;
    private bool $debug;

    // Minimum critics required for a valid evaluation (kept in sync with the coordinator)
    private const DEFAULT_MIN_CRITICS_REQUIRED = 2;

    /**
     * Constructor
     *
     * @param ActorRegistry $actorRegistry Actor registry
     * @param CriticRegistry $criticRegistry Critic registry
     * @param bool $debug Debug logging flag
     */
    public function __construct(ActorRegistry $actorRegistry, CriticRegistry $criticRegistry, bool $debug = false)
    {
        $this->actorRegistry = $actorRegistry;
        $this->criticRegistry = $criticRegistry;
        $this->debug = $debug;
    }

    /**
     * Select best actor for action based on capabilities, confidence, and load
     *
     * Implements sophisticated actor selection algorithm considering:
     * - Capability match for action type
     * - Domain preference (if specified)
     * - Actor confidence for specific action
     * - Current load and availability
     * - Historical performance
     *
     * Requirements: 3.1, 10.1, 10.2, 10.3, 10.4, 10.5, 23.2, 23.3
     *
     * @param Action $action Action to execute
     * @param string|null $preferredDomain Preferred domain (null for no preference)
     * @return ActorAgentInterface Selected actor
     * @throws NoCapableActorException If no capable actor found
     */
    public function selectActor(Action $action, ?string $preferredDomain = null): ActorAgentInterface
    {
        // Get capable actors with domain preference (Requirements 10.1, 23.2, 23.3)
        if ($preferredDomain !== null) {
            $capableActors = $this->actorRegistry->getCapableActorsWithDomainPreference(
                $action->getType(),
                $preferredDomain
            );
        } else {
            $capableActors = $this->actorRegistry->getCapableActors($action->getType());
        }
        
        if (empty($capableActors)) {
            throw new NoCapableActorException(
                "No capable actor for action type: {$action->getType()}" .
                ($preferredDomain ? " (preferred domain: {$preferredDomain})" : "")
            );
        }
        
        // Score actors (Requirements 10.2, 10.3, 10.4, 23.4)
        $scoredActors = [];
        foreach ($capableActors as $actor) {
            $actorId = $actor->getActorId();
            
            // Get confidence for this specific action
            $confidence = $actor->evaluateConfidence($action);
            
            // Get current load
            $load = $this->actorRegistry->getActorLoad($actorId);
            
            // Get historical performance (domain-specific if available)
            if ($preferredDomain !== null) {
                $performance = $this->actorRegistry->getActorPerformanceForDomain($actorId, $preferredDomain);
            } else {
                $performance = $this->actorRegistry->getActorPerformance($actorId);
            }
            
            // Combined score: confidence (50%) + performance (30%) + availability (20%)
            $score = ($confidence * 0.5) + ($performance * 0.3) + ((1.0 - $load) * 0.2);
            
            $scoredActors[$actorId] = [
                'actor' => $actor,
                'score' => $score,
                'confidence' => $confidence,
                'load' => $load,
                'performance' => $performance
            ];
        }
        
        // Sort by score descending
        uasort($scoredActors, fn($a, $b) => $b['score'] <=> $a['score']);
        
        // Select best actor (Requirement 10.5)
        $selected = reset($scoredActors);
        $selectedActor = $selected['actor'];
        
        if ($this->debug) {
            error_log(sprintf(
                "ActorCriticCoordinator: Selected actor %s (score: %.2f, confidence: %.2f, load: %.2f, performance: %.2f) from %d candidates%s",
                $selectedActor->getActorId(),
                $selected['score'],
                $selected['confidence'],
                $selected['load'],
                $selected['performance'],
                count($capableActors),
                $preferredDomain ? " (domain: {$preferredDomain})" : ""
            ));
        }
        
        return $selectedActor;
    }
    
    /**
     * Select critics for evaluation excluding producing actor
     *
     * Implements sophisticated critic selection algorithm with self-evaluation prevention:
     * - Capability match for output type
     * - Domain preference (if specified)
     * - Exclude producing actor (self-evaluation prevention)
     * - Diverse expertise levels
     * - Load balancing
     * - Historical agreement with consensus
     *
     * Requirements: 3.2, 11.1, 11.2, 11.3, 11.4, 11.5, 24.2, 24.3
     *
     * @param ActionResult $result Result to evaluate
     * @param int $count Number of critics to select
     * @param string|null $preferredDomain Preferred domain (null for no preference)
     * @return array<CriticAgentInterface> Selected critics
     * @throws InsufficientCriticsException If too few critics available
     */
    public function selectCritics(ActionResult $result, int $count, ?string $preferredDomain = null): array
    {
        // Get qualified critics with domain preference (Requirements 11.1, 24.2, 24.3)
        if ($preferredDomain !== null) {
            $qualifiedCritics = $this->criticRegistry->getQualifiedCriticsWithDomainPreference(
                $result->getOutputType(),
                $preferredDomain
            );
        } else {
            $qualifiedCritics = $this->criticRegistry->getQualifiedCritics($result->getOutputType());
        }
        
        // Exclude producing actor (Requirements 3.2, 11.2)
        $producerId = $result->getProducerAgentId();
        $validCritics = array_filter($qualifiedCritics, fn($c) => $c->getCriticId() !== $producerId);
        
        $minCriticsRequired = self::DEFAULT_MIN_CRITICS_REQUIRED;
        
        if (count($validCritics) < $minCriticsRequired) {
            throw new InsufficientCriticsException(
                "Insufficient critics for output type: {$result->getOutputType()}. " .
                "Required: {$minCriticsRequired}, Available: " . count($validCritics) .
                " (Excluded producer: {$producerId})" .
                ($preferredDomain ? " (preferred domain: {$preferredDomain})" : "")
            );
        }
        
        // Score critics by expertise, agreement, and load (Requirements 11.3, 11.4, 24.4)
        $scoredCritics = [];
        foreach ($validCritics as $critic) {
            $criticId = $critic->getCriticId();
            
            // Get evaluation criteria
            $criteria = $critic->getEvaluationCriteria();
            $expertise = 0.5; // Default
            
            if (isset($criteria[$result->getOutputType()])) {
                $criterion = $criteria[$result->getOutputType()];
                if (is_object($criterion) && method_exists($criterion, 'getExpertiseLevel')) {
                    $expertise = $criterion->getExpertiseLevel();
                }
            }
            
            // Get current load
            $load = $this->criticRegistry->getCriticLoad($criticId);
            
            // Get agreement with consensus (domain-specific if available)
            if ($preferredDomain !== null) {
                $agreement = $this->criticRegistry->getCriticAgreementForDomain($criticId, $preferredDomain);
            } else {
                $agreement = $this->criticRegistry->getCriticAgreement($criticId);
            }
            
            // Combined score: expertise (40%) + agreement (40%) + availability (20%)
            $score = ($expertise * 0.4) + ($agreement * 0.4) + ((1.0 - $load) * 0.2);
            
            $scoredCritics[$criticId] = [
                'critic' => $critic,
                'score' => $score,
                'expertise' => $expertise,
                'load' => $load,
                'agreement' => $agreement
            ];
        }
        
        // Sort by score descending
        uasort($scoredCritics, fn($a, $b) => $b['score'] <=> $a['score']);
        
        // Select top N critics with diversity (Requirement 11.5)
        $selected = $this->selectDiverseCritics($scoredCritics, $count);
        
        if ($this->debug) {
            error_log(sprintf(
                "ActorCriticCoordinator: Selected %d critics from %d candidates (excluded producer: %s)%s",
                count($selected),
                count($validCritics),
                $producerId,
                $preferredDomain ? " (domain: {$preferredDomain})" : ""
            ));
        }
        
        return $selected;
    }

    /**
     * Select diverse critics to ensure balanced evaluation
     *
     * @param array $scoredCritics Scored critics with metadata
     * @param int $count Number to select
     * @return array<CriticAgentInterface> Selected critics
     */
    private function selectDiverseCritics(array $scoredCritics, int $count): array
    {
        // For now, select top N by score
        // Future enhancement: ensure diversity in expertise levels
        $selected = array_slice($scoredCritics, 0, min($count, count($scoredCritics)));
        
        return array_map(fn($s) => $s['critic'], $selected);
    }
    
    /**
     * Select alternative actor excluding failed ones
     *
     * @param Action $action Action to execute
     * @param array $excludeActorIds Actor IDs to exclude
     * @return ActorAgentInterface Alternative actor
     * @throws NoCapableActorException If no alternative actor available
     */
    public function selectAlternativeActor(Action $action, array $excludeActorIds): ActorAgentInterface
    {
        $capableActors = $this->actorRegistry->getCapableActors($action->getType());
        $alternatives = array_filter($capableActors, fn($a) => !in_array($a->getActorId(), $excludeActorIds, true));
        
        if (empty($alternatives)) {
            throw new NoCapableActorException("No alternative actor available for action type: {$action->getType()}");
        }
        
        // Select best alternative
        $scoredActors = [];
        foreach ($alternatives as $actor) {
            $actorId = $actor->getActorId();
            $confidence = $actor->evaluateConfidence($action);
            $load = $this->actorRegistry->getActorLoad($actorId);
            $performance = $this->actorRegistry->getActorPerformance($actorId);
            
            $score = ($confidence * 0.5) + ($performance * 0.3) + ((1.0 - $load) * 0.2);
            $scoredActors[$actorId] = ['actor' => $actor, 'score' => $score];
        }
        
        uasort($scoredActors, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return reset($scoredActors)['actor'];
    }
    
    /**
     * Select additional critics when initial selection is insufficient
     *
     * @param ActionResult $result Result to evaluate
     * @param int $count Number of additional critics needed
     * @param array $excludeCriticIds Critic IDs to exclude
     * @return array<CriticAgentInterface> Additional critics
     * @throws InsufficientCriticsException If not enough critics available
     */
    public function selectAdditionalCritics(
        ActionResult $result,
        int $count,
        array $excludeCriticIds
    ): array {
        $qualifiedCritics = $this->criticRegistry->getQualifiedCritics($result->getOutputType());
        $validCritics = array_filter($qualifiedCritics, fn($c) => !in_array($c->getCriticId(), $excludeCriticIds, true));
        
        if (count($validCritics) < $count) {
            throw new InsufficientCriticsException(
                "Insufficient additional critics available. Needed: {$count}, Available: " . count($validCritics)
            );
        }
        
        // Score and select best available critics
        $scoredCritics = [];
        foreach ($validCritics as $critic) {
            $criticId = $critic->getCriticId();
            $criteria = $critic->getEvaluationCriteria();
            $expertise = 0.5;
            
            if (isset($criteria[$result->getOutputType()])) {
                $criterion = $criteria[$result->getOutputType()];
                if (is_object($criterion) && method_exists($criterion, 'getExpertiseLevel')) {
                    $expertise = $criterion->getExpertiseLevel();
                }
            }
            
            $load = $this->criticRegistry->getCriticLoad($criticId);
            $agreement = $this->criticRegistry->getCriticAgreement($criticId);
            
            $score = ($expertise * 0.4) + ($agreement * 0.4) + ((1.0 - $load) * 0.2);
            $scoredCritics[] = ['critic' => $critic, 'score' => $score];
        }
        
        usort($scoredCritics, fn($a, $b) => $b['score'] <=> $a['score']);
        
        $selected = array_slice($scoredCritics, 0, $count);
        
        return array_map(fn($s) => $s['critic'], $selected);
    }
}

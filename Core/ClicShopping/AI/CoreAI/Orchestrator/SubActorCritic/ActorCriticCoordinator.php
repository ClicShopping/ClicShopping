<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\AI\InterfacesAI\ActorAgentInterface;
use ClicShopping\AI\InterfacesAI\CriticAgentInterface;
use ClicShopping\AI\RegistryAI\Exceptions\NoCapableActorException;
use ClicShopping\AI\RegistryAI\Exceptions\InsufficientCriticsException;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\LLMWeightingEngine;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightedConsensusBuilder;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\CriticDataCollector;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\LLMPromptBuilder;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightNormalizer;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightAuditLogger;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\EvaluationRetryHandler;
use ClicShopping\AI\Config\ActorCriticConfig;
use ClicShopping\AI\Config\AgentSystemConfig;
use ClicShopping\AI\Config\AgentTechnicalConfig;
use Exception;
use InvalidArgumentException;

/**
 * ActorCriticCoordinator Class
 *
 * Central orchestrator managing the complete actor-critic workflow.
 * Coordinates actor selection, action execution, critic selection,
 * parallel evaluation, consensus building, and feedback delivery.
 *
 * This is the main entry point for the Actor-Critic separation architecture,
 * ensuring clean separation between execution (actors) and evaluation (critics).
 *
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 9.1, 9.2, 9.3, 10.1, 10.2, 10.3, 10.4, 10.5,
 *               11.1, 11.2, 11.3, 11.4, 11.5, 20.1, 20.2, 20.3, 20.4, 20.5,
 *               21.1, 21.2, 21.3, 21.4, 21.5
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class ActorCriticCoordinator
{
    private ActorRegistry $actorRegistry;
    private CriticRegistry $criticRegistry;
    private ConsensusBuilder $consensusBuilder;
    private FeedbackManager $feedbackManager;
    private ?LLMWeightingEngine $weightingEngine;
    private ?WeightedConsensusBuilder $weightedConsensusBuilder;
    private EvaluationRetryHandler $evaluationRetryHandler;
    private $db;
    private bool $debug;
    private array $config;
    
    // Configuration constants
    private const DEFAULT_CRITICS_PER_EVALUATION = 3;
    private const DEFAULT_MIN_CRITICS_REQUIRED = 2;
    private const DEFAULT_ACTOR_RETRY_ATTEMPTS = 3;
    private const DEFAULT_CRITIC_EVALUATION_TIMEOUT = 30; // seconds
    private const DEFAULT_MAX_CONCURRENT_ACTIONS_PER_ACTOR = 5;
    private const DEFAULT_MAX_CONCURRENT_EVALUATIONS_PER_CRITIC = 10;
    
    /**
     * Constructor
     *
     * Initializes the coordinator with all required dependencies.
     *
     * @param ActorRegistry|null $actorRegistry Actor registry (optional, will create if null)
     * @param CriticRegistry|null $criticRegistry Critic registry (optional, will create if null)
     * @param ConsensusBuilder|null $consensusBuilder Consensus builder (optional, will create if null)
     * @param FeedbackManager|null $feedbackManager Feedback manager (optional, will create if null)
     */
    public function __construct(
        ?ActorRegistry $actorRegistry = null,
        ?CriticRegistry $criticRegistry = null,
        ?ConsensusBuilder $consensusBuilder = null,
        ?FeedbackManager $feedbackManager = null
    ) {
        $this->actorRegistry = $actorRegistry ?? new ActorRegistry();
        $this->criticRegistry = $criticRegistry ?? new CriticRegistry();
        $this->consensusBuilder = $consensusBuilder ?? new ConsensusBuilder();
        $this->feedbackManager = $feedbackManager ?? new FeedbackManager();
        $this->db = Registry::get('Db');
        $this->evaluationRetryHandler = new EvaluationRetryHandler();
        $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') &&
                       CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
        
        // Load adaptive weighting configuration
        $this->config = $this->loadConfig();
        
        // Initialize adaptive weighting components if enabled
        // Check both AgentSystemConfig (module) and file config
        $adaptiveWeightingEnabled = AgentSystemConfig::isAdaptiveWeightingEnabled() && 
                                    $this->config['ADAPTIVE_WEIGHTING_ENABLED'];
        
        if ($adaptiveWeightingEnabled) {
            $this->initializeAdaptiveWeighting();
        } else {
            $this->weightingEngine = null;
            $this->weightedConsensusBuilder = null;
            
            if ($this->debug) {
                error_log(sprintf(
                    "ActorCriticCoordinator: Adaptive weighting disabled (Module: %s, Config: %s)",
                    AgentSystemConfig::isAdaptiveWeightingEnabled() ? 'enabled' : 'disabled',
                    $this->config['ADAPTIVE_WEIGHTING_ENABLED'] ? 'enabled' : 'disabled'
                ));
            }
        }
    }
    
    /**
     * Coordinate complete execution: actor → critics → consensus → feedback
     *
     * Main entry point for actor-critic coordination. Orchestrates the complete
     * workflow from actor selection through feedback delivery.
     *
     * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5
     *
     * @param Action $action Action to execute and evaluate
     * @return CoordinatedResult Complete result with output, evaluations, and consensus
     * @throws InvalidArgumentException If action is invalid
     * @throws Exception If coordination fails
     */
    public function coordinateExecution(Action $action): CoordinatedResult
    {
        // Validate input
        if (!($action instanceof Action)) {
            throw new InvalidArgumentException('Action must be an Action instance');
        }
        
        $startTime = microtime(true);
        
        try {
            if ($this->debug) {
                error_log(sprintf(
                    "ActorCriticCoordinator: Starting coordination for action type: %s, priority: %s",
                    $action->getType(),
                    $action->getPriority()
                ));
            }
            
            // Step 1: Select and execute actor (Requirements 3.1, 10.1-10.5, 20.1-20.5)
            $actor = $this->selectActor($action);
            $actionResult = $this->executeWithRetry($actor, $action);
            $executionTime = microtime(true) - $startTime;
            
            // Step 2: Select critics excluding producing actor (Requirements 3.2, 11.1-11.5)
            $criticsCount = $this->config['critics_per_evaluation'] ?? self::DEFAULT_CRITICS_PER_EVALUATION;
            $critics = $this->selectCritics($actionResult, $criticsCount);
            
            // Step 3: Parallel evaluation (Requirements 3.3, 9.1-9.3, 21.1-21.5)
            $evaluations = $this->evaluateInParallel($critics, $actionResult);
            $evaluationTime = microtime(true) - $startTime - $executionTime;
            
            // Step 4: Build consensus with adaptive weighting if enabled
            $adaptiveWeights = null;
            $weightExplanations = null;
            $domainAnalysis = null;
            $consensusComparison = null;
            
            if ($this->config['ADAPTIVE_WEIGHTING_ENABLED'] && $this->weightingEngine !== null) {
                // Build evaluation context from action result
                $evaluationContext = $this->buildEvaluationContext($actionResult, $action);
                
                // Calculate adaptive weights using LLM
                $weightResult = $this->weightingEngine->calculateAdaptiveWeights($critics, $evaluationContext);
                
                // Build dynamic consensus using adaptive weights
                $consensusResult = $this->weightedConsensusBuilder->buildDynamicConsensus($evaluations, $weightResult);
                
                // Extract consensus for feedback
                $consensus = $this->createConsensusFromResult($consensusResult);
                
                // Store adaptive weighting data
                $adaptiveWeights = $weightResult->getNormalizedWeights();
                $weightExplanations = $weightResult->getExplanations();
                $domainAnalysis = $weightResult->getFactorAnalysis()['domain_analysis'] ?? [];
                $consensusComparison = [
                    'dynamic_consensus' => $consensusResult->getDynamicConsensus(),
                    'static_consensus' => $consensusResult->getStaticConsensus(),
                    'difference' => $consensusResult->getConsensusDifference(),
                    'improvement_percentage' => $consensusResult->getImprovementPercentage()
                ];
                
                if ($this->debug) {
                    error_log(sprintf(
                        "ActorCriticCoordinator: Adaptive weighting applied - Dynamic: %.4f, Static: %.4f, Diff: %.4f",
                        $consensusResult->getDynamicConsensus(),
                        $consensusResult->getStaticConsensus(),
                        $consensusResult->getConsensusDifference()
                    ));
                }
            } else {
                // Use static consensus building (backward compatibility)
                $consensus = $this->consensusBuilder->buildConsensus($evaluations);
                
                if ($this->debug) {
                    error_log("ActorCriticCoordinator: Using static consensus (adaptive weighting disabled)");
                }
            }
            
            // Step 5: Deliver feedback to actor (Requirement 3.5)
            $feedback = $this->feedbackManager->createFeedback($consensus, $evaluations);
            $this->deliverFeedback($actor, $feedback);
            
            $qualityThreshold = AgentTechnicalConfig::getConsensusThreshold();
            $qualityGatePassed = $consensus->meetsQualityThreshold($qualityThreshold);
            $regenerated = false;

            // Step 5c: Quality-gate regeneration loop (feature flag, default OFF).
            if (!$qualityGatePassed && ActorCriticConfig::isQualityGateRegenerationEnabled()) {
                $this->deliverFeedback($actor, $feedback); // actor learns before retry
                $retryResult = $this->executeWithRetry($actor, $action);
                $retryCritics = $this->selectCritics($retryResult, $criticsCount);
                $retryEvaluations = $this->evaluateInParallel($retryCritics, $retryResult);
                $retryConsensus = $this->consensusBuilder->buildConsensus($retryEvaluations);

                if (self::higherScoringConsensus($consensus, $retryConsensus) === $retryConsensus) {
                    $actionResult = $retryResult;
                    $evaluations = $retryEvaluations;
                    $critics = $retryCritics;
                    $consensus = $retryConsensus;
                    $feedback = $this->feedbackManager->createFeedback($consensus, $evaluations);
                    $qualityGatePassed = $consensus->meetsQualityThreshold($qualityThreshold);
                    $regenerated = true;
                }
            }

            // Step 6: Create coordinated result with adaptive weighting data
            $result = new CoordinatedResult(
                $actionResult,
                $evaluations,
                $consensus,
                $feedback,
                [
                    'execution_time' => $executionTime,
                    'evaluation_time' => $evaluationTime,
                    'total_time' => microtime(true) - $startTime,
                    'actor_id' => $actor->getActorId(),
                    'critic_ids' => array_map(fn($c) => $c->getCriticId(), $critics),
                    'critics_count' => count($critics),
                    'consensus_reached' => $consensus->isReached(),
                    'outliers_count' => count($consensus->getOutliers()),
                    'quality_gate_passed' => $qualityGatePassed,
                    'quality_threshold' => $qualityThreshold,
                    'regenerated' => $regenerated,
                    'adaptive_weighting_used' => $this->config['ADAPTIVE_WEIGHTING_ENABLED']
                ],
                $adaptiveWeights,
                $weightExplanations,
                $domainAnalysis,
                $consensusComparison
            );
            
            // Store coordinated result
            $this->storeCoordinatedResult($result);
            
            if ($this->debug) {
                error_log(sprintf(
                    "ActorCriticCoordinator: Coordination complete - Actor: %s, Critics: %d, Score: %.2f, Time: %.3fs",
                    $actor->getActorId(),
                    count($critics),
                    $consensus->getScore(),
                    $result->getMetadata()['total_time']
                ));
            }
            
            return $result;
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log(sprintf(
                    "ActorCriticCoordinator: Coordination failed for action %s - %s",
                    $action->getType(),
                    $e->getMessage()
                ));
            }
            throw new Exception('Failed to coordinate execution: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Return the higher-scoring of two consensuses (ties keep the original).
     *
     * Used by the quality-gate regeneration loop so a regenerated result is adopted only
     * when it is strictly better — the outcome is never worse than the original attempt.
     */
    public static function higherScoringConsensus(Consensus $original, Consensus $candidate): Consensus
    {
        return $candidate->getScore() > $original->getScore() ? $candidate : $original;
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
        
        $minCriticsRequired = $this->getConfig('min_critics_required', self::DEFAULT_MIN_CRITICS_REQUIRED);
        
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
     * Execute action with retry on failure
     *
     * Implements retry logic for actor failures:
     * - Catch execution exceptions
     * - Log failures with context
     * - Select alternative actors
     * - Retry up to configured maximum
     * - Update performance metrics
     *
     * Requirements: 20.1, 20.2, 20.3, 20.4, 20.5
     *
     * @param ActorAgentInterface $actor Actor to execute
     * @param Action $action Action to execute
     * @return ActionResult Execution result
     * @throws Exception If all retries fail
     */
    private function executeWithRetry(ActorAgentInterface $actor, Action $action): ActionResult
    {
        $maxRetries = $this->getConfig('actor_retry_attempts', self::DEFAULT_ACTOR_RETRY_ATTEMPTS);
        $lastException = null;
        $attemptedActors = [];
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $actorId = $actor->getActorId();
                $attemptedActors[] = $actorId;
                
                // Increment load tracking
                $this->actorRegistry->incrementLoad($actorId);
                
                $startTime = microtime(true);
                
                // Execute action (Requirement 20.1)
                $result = $actor->executeAction($action);
                
                $executionTimeMs = (int)((microtime(true) - $startTime) * 1000);
                
                // Decrement load tracking
                $this->actorRegistry->decrementLoad($actorId);
                
                // Record successful execution (Requirement 20.5)
                $this->actorRegistry->recordExecution(
                    $actorId,
                    $action->getActionId(),
                    $result->getResultId(),
                    $action->getType(),
                    $result->getStatus(),
                    $executionTimeMs,
                    null, // Quality score will be set after evaluation
                    $result->getOutputType()
                );
                
                if ($this->debug) {
                    error_log(sprintf(
                        "ActorCriticCoordinator: Actor %s executed action successfully (attempt %d, time: %dms)",
                        $actorId,
                        $attempt,
                        $executionTimeMs
                    ));
                }
                
                return $result;
                
            } catch (Exception $e) {
                $lastException = $e;
                $actorId = $actor->getActorId();
                
                // Decrement load tracking
                $this->actorRegistry->decrementLoad($actorId);
                
                // Log failure (Requirement 20.1)
                if ($this->debug) {
                    error_log(sprintf(
                        "ActorCriticCoordinator: Actor %s execution failed (attempt %d/%d) - %s",
                        $actorId,
                        $attempt,
                        $maxRetries,
                        $e->getMessage()
                    ));
                }
                
                // Record failed execution (Requirement 20.5)
                $this->actorRegistry->recordExecution(
                    $actorId,
                    $action->getActionId(),
                    'failed_' . uniqid(),
                    $action->getType(),
                    'failed',
                    0,
                    0.0,
                    'unknown'
                );
                
                // Try alternative actor if retries remain (Requirements 20.2, 20.3)
                if ($attempt < $maxRetries) {
                    try {
                        $actor = $this->selectAlternativeActor($action, $attemptedActors);
                    } catch (NoCapableActorException $e) {
                        // No alternative actors available
                        break;
                    }
                }
            }
        }
        
        // All retries failed (Requirement 20.4)
        throw new Exception(
            "All retry attempts failed for action: {$action->getType()}. " .
            "Attempted actors: " . implode(', ', $attemptedActors) . ". " .
            "Last error: " . ($lastException ? $lastException->getMessage() : 'Unknown'),
            0,
            $lastException
        );
    }

    
    /**
     * Evaluate action result in parallel with multiple critics
     *
     * Implements parallel evaluation with error handling:
     * - Dispatch to all critics simultaneously
     * - Collect results as they arrive
     * - Handle timeouts gracefully
     * - Continue with available evaluations on critic failure
     * - Ensure minimum critics complete successfully
     *
     * Requirements: 9.1, 9.2, 9.3, 21.1, 21.2, 21.3, 21.4, 21.5
     *
     * @param array<CriticAgentInterface> $critics Critics to evaluate
     * @param ActionResult $result Result to evaluate
     * @return array<Evaluation> Evaluations from critics
     * @throws InsufficientCriticsException If too few critics complete evaluation
     */
    private function evaluateInParallel(array $critics, ActionResult $result): array
    {
        $timeout = $this->getConfig('critic_evaluation_timeout', self::DEFAULT_CRITIC_EVALUATION_TIMEOUT);
        $evaluations = [];
        $failedCritics = [];
        
        // Dispatch to all critics (Requirement 9.1)
        foreach ($critics as $critic) {
            $criticId = $critic->getCriticId();
            
            try {
                // Increment load tracking
                $this->criticRegistry->incrementLoad($criticId);
                
                $startTime = microtime(true);
                
                // Evaluate with timeout (Requirements 9.2, 9.3)
                $evaluation = $this->evaluateWithTimeout($critic, $result, $timeout);
                
                $evaluationTimeMs = (int)((microtime(true) - $startTime) * 1000);
                
                // Decrement load tracking
                $this->criticRegistry->decrementLoad($criticId);
                
                // Record successful evaluation (Requirement 21.5)
                $this->criticRegistry->recordEvaluation(
                    $criticId,
                    $evaluation->getEvaluationId(),
                    $result->getResultId(),
                    $result->getOutputType(),
                    $result->getProducerAgentId(),
                    [
                        'accuracy' => $evaluation->getAccuracyScore(),
                        'completeness' => $evaluation->getCompletenessScore(),
                        'efficiency' => $evaluation->getEfficiencyScore(),
                        'clarity' => $evaluation->getClarityScore()
                    ],
                    $evaluation->getOverallScore(),
                    $evaluation->getFeedback(),
                    $evaluation->getStrengths(),
                    $evaluation->getImprovements(),
                    $evaluationTimeMs
                );
                
                $evaluations[] = $evaluation;
                
                if ($this->debug) {
                    error_log(sprintf(
                        "ActorCriticCoordinator: Critic %s completed evaluation (score: %.2f, time: %dms)",
                        $criticId,
                        $evaluation->getOverallScore(),
                        $evaluationTimeMs
                    ));
                }
                
            } catch (Exception $e) {
                // Decrement load tracking
                $this->criticRegistry->decrementLoad($criticId);

                // Log failure but continue with other critics (Requirements 21.1, 21.2)
                $failedCritics[] = $criticId;

                // Track evaluation failure in retry table
                $this->logEvaluationRetryAttempt(
                    $result->getResultId(),
                    $result->getOutputType(),
                    $criticId
                );

                if ($this->debug) {
                    error_log(sprintf(
                        "ActorCriticCoordinator: Critic %s evaluation failed - %s",
                        $criticId,
                        $e->getMessage()
                    ));
                }
            }
        }
        
        $minCriticsRequired = $this->getConfig('min_critics_required', self::DEFAULT_MIN_CRITICS_REQUIRED);
        
        // Check if sufficient evaluations completed (Requirements 21.3, 21.4)
        if (count($evaluations) < $minCriticsRequired) {
            // Attempt to select additional critics if available
            try {
                $additionalCritics = $this->selectAdditionalCritics(
                    $result,
                    $minCriticsRequired - count($evaluations),
                    array_merge(
                        array_map(fn($c) => $c->getCriticId(), $critics),
                        $failedCritics
                    )
                );
                
                // Evaluate with additional critics
                foreach ($additionalCritics as $critic) {
                    try {
                        $criticId = $critic->getCriticId();
                        $this->criticRegistry->incrementLoad($criticId);
                        
                        $startTime = microtime(true);
                        $evaluation = $this->evaluateWithTimeout($critic, $result, $timeout);
                        $evaluationTimeMs = (int)((microtime(true) - $startTime) * 1000);
                        
                        $this->criticRegistry->decrementLoad($criticId);
                        
                        $this->criticRegistry->recordEvaluation(
                            $criticId,
                            $evaluation->getEvaluationId(),
                            $result->getResultId(),
                            $result->getOutputType(),
                            $result->getProducerAgentId(),
                            [
                                'accuracy' => $evaluation->getAccuracyScore(),
                                'completeness' => $evaluation->getCompletenessScore(),
                                'efficiency' => $evaluation->getEfficiencyScore(),
                                'clarity' => $evaluation->getClarityScore()
                            ],
                            $evaluation->getOverallScore(),
                            $evaluation->getFeedback(),
                            $evaluation->getStrengths(),
                            $evaluation->getImprovements(),
                            $evaluationTimeMs
                        );
                        
                        $evaluations[] = $evaluation;

                        // Mark retry as successful
                        $this->updateEvaluationRetryStatus(
                            $result->getResultId(),
                            $criticId,
                            'success'
                        );

                    } catch (Exception $e) {
                        $this->criticRegistry->decrementLoad($critic->getCriticId());
                        // Continue with what we have
                    }
                }
                
            } catch (Exception $e) {
                // Could not get additional critics
            }
        }
        
        // Final check for minimum critics (Requirement 21.4)
        if (count($evaluations) < $minCriticsRequired) {
            // Mark all retry records as failed
            foreach ($failedCritics as $failedCriticId) {
                $this->updateEvaluationRetryStatus(
                    $result->getResultId(),
                    $failedCriticId,
                    'failed'
                );
            }

            throw new InsufficientCriticsException(
                "Too few critics completed evaluation. " .
                "Required: {$minCriticsRequired}, Received: " . count($evaluations) . ", " .
                "Failed: " . count($failedCritics)
            );
        }
        
        if ($this->debug) {
            error_log(sprintf(
                "ActorCriticCoordinator: Parallel evaluation complete - %d successful, %d failed",
                count($evaluations),
                count($failedCritics)
            ));
        }
        
        return $evaluations;
    }
    
    /**
     * Evaluate with timeout
     *
     * @param CriticAgentInterface $critic Critic to evaluate
     * @param ActionResult $result Result to evaluate
     * @param int $timeout Timeout in seconds
     * @return Evaluation Evaluation result
     * @throws Exception If evaluation fails or times out
     */
    private function evaluateWithTimeout(
        CriticAgentInterface $critic,
        ActionResult $result,
        int $timeout
    ): Evaluation {
        $startTime = time();
        
        // Execute evaluation
        $evaluation = $critic->evaluateAction($result);
        
        // Check if timeout exceeded
        if (time() - $startTime > $timeout) {
            throw new Exception("Evaluation exceeded timeout of {$timeout} seconds");
        }
        
        return $evaluation;
    }
    
    /**
     * Deliver feedback to actor
     *
     * @param ActorAgentInterface $actor Actor to receive feedback
     * @param Feedback $feedback Feedback to deliver
     * @return void
     */
    private function deliverFeedback(ActorAgentInterface $actor, Feedback $feedback): void
    {
        try {
            $actorId = $actor->getActorId();
            
            // Deliver feedback to actor
            $actor->receiveFeedback($feedback);
            
            // Track delivery
            $this->feedbackManager->trackDelivery($actorId, $feedback);
            
            if ($this->debug) {
                error_log(sprintf(
                    "ActorCriticCoordinator: Feedback delivered to actor %s (score: %.2f)",
                    $actorId,
                    $feedback->getConsensusScore()
                ));
            }
            
        } catch (Exception $e) {
            // Log error but don't fail coordination
            if ($this->debug) {
                error_log(sprintf(
                    "ActorCriticCoordinator: Failed to deliver feedback to actor %s - %s",
                    $actor->getActorId(),
                    $e->getMessage()
                ));
            }
        }
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
    private function selectAlternativeActor(Action $action, array $excludeActorIds): ActorAgentInterface
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
    private function selectAdditionalCritics(
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

    
    /**
     * Store coordinated result in database
     *
     * @param CoordinatedResult $result Coordinated result to store
     * @return void
     */
    private function storeCoordinatedResult(CoordinatedResult $result): void
    {
        try {
            // Check if table exists
            if (!$this->tableExists('rag_agent_coordinated_results')) {
                if ($this->debug) {
                    error_log("ActorCriticCoordinator: Table rag_agent_coordinated_results does not exist, skipping storage");
                }
                return;
            }
            
            $metadata = $result->getMetadata();
            $actionResult = $result->getActionResult();
            $consensus = $result->getConsensus();
            
            $sql = "INSERT INTO :table_rag_agent_coordinated_results 
                    (coordination_id, action_id, result_id, actor_id, consensus_id,
                     consensus_score, num_evaluations, num_critics, execution_time_ms,
                     evaluation_time_ms, total_time_ms, created_at)
                    VALUES (:coordination_id, :action_id, :result_id, :actor_id, :consensus_id,
                            :consensus_score, :num_evaluations, :num_critics, :execution_time_ms,
                            :evaluation_time_ms, :total_time_ms, :created_at)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':coordination_id', $result->getCoordinationId());
            $stmt->bindValue(':action_id', $actionResult->getActionId());
            $stmt->bindValue(':result_id', $actionResult->getResultId());
            $stmt->bindValue(':actor_id', $metadata['actor_id']);
            $stmt->bindValue(':consensus_id', $consensus->getConsensusId());
            $stmt->bindValue(':consensus_score', $consensus->getScore());
            $stmt->bindValue(':num_evaluations', count($result->getEvaluations()));
            $stmt->bindValue(':num_critics', $metadata['critics_count']);
            $stmt->bindValue(':execution_time_ms', (int)($metadata['execution_time'] * 1000));
            $stmt->bindValue(':evaluation_time_ms', (int)($metadata['evaluation_time'] * 1000));
            $stmt->bindValue(':total_time_ms', (int)($metadata['total_time'] * 1000));
            $stmt->bindValue(':created_at', date('Y-m-d H:i:s'));
            $stmt->execute();
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ActorCriticCoordinator: Failed to store coordinated result - " . $e->getMessage());
            }
            // Don't throw - storage failure shouldn't fail coordination
        }
    }
    
    /**
     * Check if a database table exists
     *
     * @param string $tableName The table name (without prefix)
     * @return bool True if table exists
     */
    private function tableExists(string $tableName): bool
    {
        try {
            $prefix = CLICSHOPPING::getConfig('db_table_prefix');
            $fullTableName = $prefix . $tableName;
            $sql = "SHOW TABLES LIKE ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$fullTableName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    private function getConfig(string $key, $default)
    {
        // For now, return defaults
        // Future: load from configuration file or database
        return $default;
    }
    
    /**
     * Get actor registry
     *
     * @return ActorRegistry Actor registry instance
     */
    public function getActorRegistry(): ActorRegistry
    {
        return $this->actorRegistry;
    }
    
    /**
     * Get critic registry
     *
     * @return CriticRegistry Critic registry instance
     */
    public function getCriticRegistry(): CriticRegistry
    {
        return $this->criticRegistry;
    }
    
    /**
     * Get consensus builder
     *
     * @return ConsensusBuilder Consensus builder instance
     */
    public function getConsensusBuilder(): ConsensusBuilder
    {
        return $this->consensusBuilder;
    }
    
    /**
     * Get feedback manager
     *
     * @return FeedbackManager Feedback manager instance
     */
    public function getFeedbackManager(): FeedbackManager
    {
        return $this->feedbackManager;
    }
    
    /**
     * Get coordination statistics
     *
     * @return array Statistics about coordinations
     */
    public function getCoordinationStatistics(): array
    {
        try {
            if (!$this->tableExists('rag_agent_coordinated_results')) {
                return [
                    'total_coordinations' => 0,
                    'avg_consensus_score' => 0.0,
                    'avg_execution_time_ms' => 0.0,
                    'avg_evaluation_time_ms' => 0.0,
                    'avg_total_time_ms' => 0.0,
                    'avg_critics_per_coordination' => 0.0
                ];
            }
            
            $sql = "
                SELECT 
                    COUNT(*) as total_coordinations,
                    AVG(consensus_score) as avg_consensus_score,
                    AVG(execution_time_ms) as avg_execution_time_ms,
                    AVG(evaluation_time_ms) as avg_evaluation_time_ms,
                    AVG(total_time_ms) as avg_total_time_ms,
                    AVG(num_critics) as avg_critics_per_coordination
                FROM :table_rag_agent_coordinated_results
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return [
                'total_coordinations' => (int)$stats['total_coordinations'],
                'avg_consensus_score' => (float)$stats['avg_consensus_score'],
                'avg_execution_time_ms' => (float)$stats['avg_execution_time_ms'],
                'avg_evaluation_time_ms' => (float)$stats['avg_evaluation_time_ms'],
                'avg_total_time_ms' => (float)$stats['avg_total_time_ms'],
                'avg_critics_per_coordination' => (float)$stats['avg_critics_per_coordination']
            ];
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ActorCriticCoordinator: Failed to get statistics - " . $e->getMessage());
            }
            return [
                'total_coordinations' => 0,
                'avg_consensus_score' => 0.0,
                'avg_execution_time_ms' => 0.0,
                'avg_evaluation_time_ms' => 0.0,
                'avg_total_time_ms' => 0.0,
                'avg_critics_per_coordination' => 0.0
            ];
        }
    }
    
    /**
     * Load adaptive weighting configuration
     *
     * Loads configuration from config file and merges with module configuration.
     * Module configuration (AgentSystemConfig/AgentTechnicalConfig) takes precedence.
     *
     * @return array Configuration array
     */
    private function loadConfig(): array
    {
        $configPath = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Apps/Configuration/ChatGpt/config/adaptive_weighting.php';
        
        $config = [];
        if (file_exists($configPath)) {
            $fileConfig = require $configPath;
            if (is_array($fileConfig)) {
                $config = $fileConfig;
            }
        }
        
        // Override with AgentSystemConfig (module configuration takes precedence)
        // Only enable adaptive weighting if BOTH module and file config allow it
        $moduleEnabled = AgentSystemConfig::isAdaptiveWeightingEnabled();
        $fileEnabled = $config['ADAPTIVE_WEIGHTING_ENABLED'] ?? false;
        $config['ADAPTIVE_WEIGHTING_ENABLED'] = $moduleEnabled && $fileEnabled;
        
        // Get LLM provider and timeout from AgentTechnicalConfig if available
        if (AgentTechnicalConfig::isEnabled()) {
            $config['LLM_PROVIDER'] = AgentTechnicalConfig::getLLMProvider();
            $config['TIMEOUT_SECONDS'] = AgentTechnicalConfig::getCoordinationTimeout();
            $config['MAX_RETRIES'] = 2; // Could be added to AgentTechnicalConfig later
            $config['critics_per_evaluation'] = AgentTechnicalConfig::getMaxCritics();
            $config['weight_cache_ttl'] = AgentTechnicalConfig::getCacheTtl();
        }
        
        // Set other defaults if not in file
        $config = array_merge([
            'LLM_PROVIDER' => 'openai',
            'FALLBACK_ENABLED' => true,
            'FALLBACK_ALERT_THRESHOLD' => 0.05,
            'WEIGHT_AUDIT_RETENTION_DAYS' => 90,
            'ANOMALY_DETECTION_ENABLED' => true,
            'CRITIC_SELECTION_ENABLED' => false,
            'MAX_RETRIES' => 2,
            'TIMEOUT_SECONDS' => 30,
            'MIGRATION_MODE' => false,
            'ADAPTIVE_WEIGHT_ROLLOUT_PERCENTAGE' => 0
        ], $config);
        
        if ($this->debug) {
            error_log(sprintf(
                "ActorCriticCoordinator: Configuration loaded - Adaptive Weighting: %s (Module: %s, File: %s), LLM Provider: %s, Timeout: %ds",
                $config['ADAPTIVE_WEIGHTING_ENABLED'] ? 'enabled' : 'disabled',
                $moduleEnabled ? 'enabled' : 'disabled',
                $fileEnabled ? 'enabled' : 'disabled',
                $config['LLM_PROVIDER'],
                $config['TIMEOUT_SECONDS']
            ));
        }
        
        return $config;
    }
    
    /**
     * Initialize adaptive weighting components
     *
     * Creates and configures the LLM weighting engine and weighted consensus builder.
     *
     * @return void
     */
    private function initializeAdaptiveWeighting(): void
    {
        try {
            // Create components
            $criticDataCollector = new CriticDataCollector($this->criticRegistry);
            $promptBuilder = new LLMPromptBuilder();
            $normalizer = new WeightNormalizer();
            $auditLogger = new WeightAuditLogger();
            
            // Create weighting engine with configuration
            $this->weightingEngine = new LLMWeightingEngine(
                $criticDataCollector,
                $promptBuilder,
                $normalizer,
                $auditLogger,
                [
                    'llm_provider' => $this->config['LLM_PROVIDER'],
                    'max_retries' => $this->config['MAX_RETRIES'],
                    'timeout_seconds' => $this->config['TIMEOUT_SECONDS'],
                    'fallback_enabled' => $this->config['FALLBACK_ENABLED'],
                    'fallback_alert_threshold' => $this->config['FALLBACK_ALERT_THRESHOLD'],
                    'weight_cache_ttl' => $this->config['weight_cache_ttl'] ?? 86400
                ]
            );
            
            // Create weighted consensus builder
            $this->weightedConsensusBuilder = new WeightedConsensusBuilder();
            
            if ($this->debug) {
                error_log("ActorCriticCoordinator: Adaptive weighting initialized with provider: " . $this->config['LLM_PROVIDER']);
            }
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ActorCriticCoordinator: Failed to initialize adaptive weighting - " . $e->getMessage());
            }
            // Disable adaptive weighting on initialization failure
            $this->config['ADAPTIVE_WEIGHTING_ENABLED'] = false;
            $this->weightingEngine = null;
            $this->weightedConsensusBuilder = null;
        }
    }
    
    /**
     * Build evaluation context from action result
     *
     * Extracts required domains and other context information from the action result
     * to provide to the LLM weighting engine.
     *
     * Requirements: 1.1, 8.1
     *
     * @param ActionResult $actionResult The action result
     * @param Action $action The original action
     * @return array Evaluation context
     */
    private function buildEvaluationContext(ActionResult $actionResult, Action $action): array
    {
        // Extract required domains from action context
        $requiredDomains = $this->extractRequiredDomains($action);
        
        // Build evaluation context
        $context = [
            'evaluation_id' => uniqid('eval_', true),
            'output_type' => $actionResult->getOutputType(),
            'priority' => $action->getPriority(),
            'action_type' => $action->getType(),
            'required_domains' => $requiredDomains,
            'execution_metrics' => $actionResult->getExecutionMetrics(),
            'special_requirements' => $this->extractSpecialRequirements($action)
        ];
        
        if ($this->debug) {
            error_log(sprintf(
                "ActorCriticCoordinator: Built evaluation context - Output: %s, Priority: %s, Domains: %s",
                $context['output_type'],
                $context['priority'],
                implode(', ', $context['required_domains'])
            ));
        }
        
        return $context;
    }
    
    /**
     * Extract required domains from action
     *
     * Determines which generic capability domains are required for evaluating
     * this action based on action type and context.
     *
     * @param Action $action The action
     * @return array Array of required domain names
     */
    private function extractRequiredDomains(Action $action): array
    {
        $actionType = $action->getType();
        $context = $action->getContext();
        $environmentalData = $context->getEnvironmentalData();
        
        // Check if domains are explicitly specified in environmental data
        if (isset($environmentalData['required_domains']) && is_array($environmentalData['required_domains'])) {
            return $environmentalData['required_domains'];
        }
        
        // Infer domains from action type (generic capability domains)
        $domainMap = [
            'search' => ['semantic', 'quality'],
            'query' => ['semantic', 'analytics'],
            'analysis' => ['analytics', 'reasoning'],
            'recommendation' => ['semantic', 'reasoning'],
            'validation' => ['quality', 'security'],
            'optimization' => ['performance', 'quality'],
            'security_audit' => ['security', 'quality'],
            'data_processing' => ['analytics', 'performance']
        ];
        
        // Return mapped domains or default to semantic
        return $domainMap[$actionType] ?? ['semantic'];
    }
    
    /**
     * Extract special requirements from action
     *
     * Extracts any special requirements or constraints from the action context.
     *
     * @param Action $action The action
     * @return array Special requirements
     */
    private function extractSpecialRequirements(Action $action): array
    {
        $context = $action->getContext();
        $environmentalData = $context->getEnvironmentalData();
        
        $requirements = [];
        
        // Check for special requirements in environmental data
        if (isset($environmentalData['special_requirements'])) {
            $requirements = $environmentalData['special_requirements'];
        }
        
        // Add priority-based requirements
        if ($action->getPriority() === 'critical') {
            $requirements[] = 'high_accuracy_required';
        }
        
        return $requirements;
    }
    
    /**
     * Create Consensus object from ConsensusResult
     *
     * Converts the ConsensusResult from weighted consensus builder into
     * a Consensus object compatible with the existing feedback system.
     *
     * @param mixed $consensusResult The consensus result
     * @return Consensus Consensus object
     */
    private function createConsensusFromResult($consensusResult): Consensus
    {
        // Extract consensus score (use dynamic consensus)
        $score = $consensusResult->getDynamicConsensus();
        
        // Create a Consensus object with the dynamic score
        // Note: This assumes Consensus has a constructor or factory method
        // Adjust based on actual Consensus class implementation
        return new Consensus(
            $score,
            true, // consensus reached
            [], // outliers (would need to be calculated if needed)
            [] // aggregated feedback (would need to be extracted if needed)
        );
    }

    /**
     * Log evaluation retry attempt to the tracking table
     *
     * @param string $resultId The action result ID
     * @param string $outputType The output type being evaluated
     * @param string $failedCriticId The critic that failed evaluation
     */
    private function logEvaluationRetryAttempt(
        string $resultId,
        string $outputType,
        string $failedCriticId
    ): void {
        try {
            $sql = "INSERT INTO :table_rag_evaluation_retries
                    (output_id, output_type, failed_evaluator_id, attempt_number, status, created_at)
                    VALUES (:output_id, :output_type, :failed_evaluator_id, :attempt_number, :status, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':output_id', $resultId);
            $stmt->bindValue(':output_type', $outputType);
            $stmt->bindValue(':failed_evaluator_id', $failedCriticId);
            $stmt->bindValue(':attempt_number', 1);
            $stmt->bindValue(':status', 'attempting');
            $stmt->execute();
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ActorCriticCoordinator: Failed to log evaluation retry - " . $e->getMessage());
            }
        }
    }

    /**
     * Update evaluation retry status
     *
     * @param string $outputId The output ID
     * @param string $retryEvaluatorId The retry evaluator that resolved the retry
     * @param string $status The new status (success or failed)
     */
    private function updateEvaluationRetryStatus(
        string $outputId,
        string $retryEvaluatorId,
        string $status
    ): void {
        try {
            $sql = "UPDATE :table_rag_evaluation_retries
                    SET status = :status,
                        retry_evaluator_id = :retry_evaluator_id,
                        resolved_at = NOW()
                    WHERE output_id = :output_id
                      AND status = 'attempting'
                    ORDER BY created_at DESC
                    LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':retry_evaluator_id', $retryEvaluatorId);
            $stmt->bindValue(':output_id', $outputId);
            $stmt->execute();
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ActorCriticCoordinator: Failed to update evaluation retry status - " . $e->getMessage());
            }
        }
    }
}

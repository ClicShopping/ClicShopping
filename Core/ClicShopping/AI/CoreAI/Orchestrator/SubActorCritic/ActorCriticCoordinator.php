<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

use ClicShopping\OM\Registry;
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
    private CoordinationResultStore $resultStore;
    private EvaluationContextBuilder $contextBuilder;
    private ActorCriticSelector $selector;
    
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
        $this->resultStore = new CoordinationResultStore($this->db, $this->debug);
        $this->contextBuilder = new EvaluationContextBuilder($this->debug);
        $this->selector = new ActorCriticSelector($this->actorRegistry, $this->criticRegistry, $this->debug);

        // Load adaptive weighting configuration
        $this->config = (new CoordinatorConfigLoader($this->debug))->load();
        
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
                $evaluationContext = $this->contextBuilder->build($actionResult, $action);
                
                // Calculate adaptive weights using LLM
                $weightResult = $this->weightingEngine->calculateAdaptiveWeights($critics, $evaluationContext);
                
                // Build dynamic consensus using adaptive weights
                $consensusResult = $this->weightedConsensusBuilder->buildDynamicConsensus($evaluations, $weightResult);

                // Build the consensus from the real evaluations (feedback, outliers, agreement) but substitute the dynamic (adaptive-weighted) score.
                $consensus = $this->consensusBuilder->buildConsensus(
                    $evaluations,
                    $consensusResult->getDynamicConsensus()
                );
                
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

            // Step 5b: Quality gate — compare the consensus SCORE against the configured AT_CONSENSUS_THRESHOLD (admin-tunable min score), distinct from isReached()
            $qualityThreshold = AgentTechnicalConfig::getConsensusThreshold();
            $qualityGatePassed = $consensus->meetsQualityThreshold($qualityThreshold);
            $regenerated = false;

            // Step 5c: Quality-gate regeneration loop (feature flag, default OFF).
            // On a gate miss, regenerate ONCE: the actor re-executes with the critic feedback, is re-evaluated, and we KEEP THE HIGHER-SCORING of the two results (never worse than the original — guards against the "regenerate-on-rejection degrades" risk).
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
            $this->resultStore->store($result);
            
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
     * Select best actor for action (delegates to ActorCriticSelector)
     *
     * @param Action $action Action to execute
     * @param string|null $preferredDomain Preferred domain (null for no preference)
     * @return ActorAgentInterface Selected actor
     * @throws NoCapableActorException If no capable actor found
     */
    public function selectActor(Action $action, ?string $preferredDomain = null): ActorAgentInterface
    {
        return $this->selector->selectActor($action, $preferredDomain);
    }

    /**
     * Select critics for evaluation excluding producing actor (delegates to ActorCriticSelector)
     *
     * @param ActionResult $result Result to evaluate
     * @param int $count Number of critics to select
     * @param string|null $preferredDomain Preferred domain (null for no preference)
     * @return array<CriticAgentInterface> Selected critics
     * @throws InsufficientCriticsException If too few critics available
     */
    public function selectCritics(ActionResult $result, int $count, ?string $preferredDomain = null): array
    {
        return $this->selector->selectCritics($result, $count, $preferredDomain);
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
     * @param ActorAgentInterface $actor Actor to execute
     * @param Action $action Action to execute
     * @return ActionResult Execution result
     * @throws Exception If all retries fail
     */
    private function executeWithRetry(ActorAgentInterface $actor, Action $action): ActionResult
    {
        $maxRetries = self::DEFAULT_ACTOR_RETRY_ATTEMPTS;
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
                        $actor = $this->selector->selectAlternativeActor($action, $attemptedActors);
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
            "Last error: " . $lastException->getMessage(),
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
     * @param array<CriticAgentInterface> $critics Critics to evaluate
     * @param ActionResult $result Result to evaluate
     * @return array<Evaluation> Evaluations from critics
     * @throws InsufficientCriticsException If too few critics complete evaluation
     */
    private function evaluateInParallel(array $critics, ActionResult $result): array
    {
        $timeout = self::DEFAULT_CRITIC_EVALUATION_TIMEOUT;
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
                $this->resultStore->logRetryAttempt(
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
        
        $minCriticsRequired = self::DEFAULT_MIN_CRITICS_REQUIRED;
        
        // Check if sufficient evaluations completed (Requirements 21.3, 21.4)
        if (count($evaluations) < $minCriticsRequired) {
            // Attempt to select additional critics if available
            try {
                $additionalCritics = $this->selector->selectAdditionalCritics(
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
                        $this->resultStore->updateRetryStatus(
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
                $this->resultStore->updateRetryStatus(
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
        return $this->resultStore->getStatistics();
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
}

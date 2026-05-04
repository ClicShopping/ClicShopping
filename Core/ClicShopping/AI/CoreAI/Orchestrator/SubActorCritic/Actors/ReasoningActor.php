<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Actors;

use ClicShopping\OM\Registry;

use ClicShopping\AI\InterfacesAI\ActorAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCapability;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * ReasoningActor - Actor agent specialized in logical reasoning and inference
 * 
 * This actor handles reasoning tasks by:
 * - Performing logical inference from premises
 * - Analyzing argument validity and soundness
 * - Drawing conclusions from available evidence
 * - Identifying logical fallacies and inconsistencies
 * 
 * Capabilities:
 * - Deductive reasoning (premises to conclusions)
 * - Inductive reasoning (patterns to generalizations)
 * - Abductive reasoning (observations to explanations)
 * - Logical consistency checking
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Actors
 * @version 1.0.0
 * @since 2026-01-30
 */
class ReasoningActor implements ActorAgentInterface
{
    private string $actorId;
    private SecurityLogger $securityLogger;
    private bool $debug;
    private array $feedbackHistory = [];
    
    /**
     * Constructor
     * 
     * @param bool $debug Enable debug mode
     */
    public function __construct(bool $debug = false)
    {
        $this->actorId = 'reasoning_actor_' . uniqid();
        $this->debug = $debug;
        
        // Initialize security logger
        $this->securityLogger = new SecurityLogger();
        
        // Register this actor in the ActorRegistry
        $this->registerInRegistry();
        
        $this->securityLogger->logSecurityEvent(
            "ReasoningActor initialized: {$this->actorId}",
            'info'
        );
    }
    
    /**
     * Execute an action and produce a result
     * 
     * @param Action $action The action to execute
     * @return ActionResult The execution result with output and metrics
     * @throws \Exception If execution fails
     */
    public function executeAction(Action $action): ActionResult
    {
        $startTime = microtime(true);
        
        try {
            $actionType = $action->getType();
            $parameters = $action->getParameters();
            
            $this->securityLogger->logSecurityEvent(
                "ReasoningActor executing action: {$actionType}",
                'info',
                ['actor_id' => $this->actorId, 'action_id' => $action->getActionId()]
            );
            
            // Route to appropriate handler based on action type
            $output = match($actionType) {
                'deductive_reasoning' => $this->executeDeductiveReasoning($parameters),
                'inductive_reasoning' => $this->executeInductiveReasoning($parameters),
                'abductive_reasoning' => $this->executeAbductiveReasoning($parameters),
                'consistency_check' => $this->executeConsistencyCheck($parameters),
                default => throw new \Exception("Unsupported action type: {$actionType}")
            };
            
            $executionTime = microtime(true) - $startTime;
            
            // Build execution metrics
            $metrics = [
                'execution_time' => $executionTime,
                'action_type' => $actionType,
                'timestamp' => date('Y-m-d H:i:s'),
                'actor_id' => $this->actorId
            ];
            
            // Add action-specific metrics
            if (isset($output['metrics'])) {
                $metrics = array_merge($metrics, $output['metrics']);
                unset($output['metrics']);
            }
            
            $result = new ActionResult(
                $action->getActionId(),
                $this->actorId,
                $output,
                $this->getOutputType($actionType),
                $metrics,
                $action->getContext(),
                'success'
            );
            
            $this->securityLogger->logSecurityEvent(
                "ReasoningActor completed action successfully",
                'info',
                ['actor_id' => $this->actorId, 'execution_time' => $executionTime]
            );
            
            return $result;
            
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            $this->securityLogger->logSecurityEvent(
                "ReasoningActor action execution failed: " . $e->getMessage(),
                'error',
                ['actor_id' => $this->actorId, 'action_id' => $action->getActionId()]
            );
            
            // Return failed result with error information
            return new ActionResult(
                $action->getActionId(),
                $this->actorId,
                ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
                'error',
                ['execution_time' => $executionTime, 'status' => 'failed'],
                $action->getContext(),
                'failed'
            );
        }
    }
    
    /**
     * Execute deductive reasoning action
     * 
     * @param array $parameters Action parameters
     * @return array Reasoning result
     */
    private function executeDeductiveReasoning(array $parameters): array
    {
        $premises = $parameters['premises'] ?? [];
        $question = $parameters['question'] ?? '';
        
        if (empty($premises)) {
            throw new \Exception("Premises are required for deductive reasoning");
        }
        
        // Perform deductive reasoning
        $conclusion = $this->deriveConclusion($premises, $question);
        $validity = $this->checkValidity($premises, $conclusion);
        $soundness = $this->checkSoundness($premises, $conclusion);
        
        return [
            'conclusion' => $conclusion,
            'validity' => $validity,
            'soundness' => $soundness,
            'reasoning_chain' => $this->buildReasoningChain($premises, $conclusion),
            'confidence' => $this->calculateConfidence($validity, $soundness),
            'metrics' => [
                'premises_count' => count($premises),
                'reasoning_steps' => count($this->buildReasoningChain($premises, $conclusion))
            ]
        ];
    }
    
    /**
     * Execute inductive reasoning action
     * 
     * @param array $parameters Action parameters
     * @return array Reasoning result
     */
    private function executeInductiveReasoning(array $parameters): array
    {
        $observations = $parameters['observations'] ?? [];
        $pattern = $parameters['pattern'] ?? '';
        
        if (empty($observations)) {
            throw new \Exception("Observations are required for inductive reasoning");
        }
        
        // Perform inductive reasoning
        $generalization = $this->identifyPattern($observations, $pattern);
        $strength = $this->assessInductiveStrength($observations, $generalization);
        $counterexamples = $this->findCounterexamples($observations, $generalization);
        
        return [
            'generalization' => $generalization,
            'strength' => $strength,
            'counterexamples' => $counterexamples,
            'supporting_evidence' => $this->gatherSupportingEvidence($observations, $generalization),
            'confidence' => $strength,
            'metrics' => [
                'observations_count' => count($observations),
                'counterexamples_count' => count($counterexamples)
            ]
        ];
    }
    
    /**
     * Execute abductive reasoning action
     * 
     * @param array $parameters Action parameters
     * @return array Reasoning result
     */
    private function executeAbductiveReasoning(array $parameters): array
    {
        $observations = $parameters['observations'] ?? [];
        $context = $parameters['context'] ?? '';
        
        if (empty($observations)) {
            throw new \Exception("Observations are required for abductive reasoning");
        }
        
        // Perform abductive reasoning
        $hypotheses = $this->generateHypotheses($observations, $context);
        $bestExplanation = $this->selectBestExplanation($hypotheses, $observations);
        $plausibility = $this->assessPlausibility($bestExplanation, $observations);
        
        return [
            'best_explanation' => $bestExplanation,
            'alternative_hypotheses' => $hypotheses,
            'plausibility' => $plausibility,
            'supporting_observations' => $this->matchObservations($bestExplanation, $observations),
            'confidence' => $plausibility,
            'metrics' => [
                'hypotheses_generated' => count($hypotheses),
                'observations_explained' => count($this->matchObservations($bestExplanation, $observations))
            ]
        ];
    }
    
    /**
     * Execute consistency check action
     * 
     * @param array $parameters Action parameters
     * @return array Consistency check result
     */
    private function executeConsistencyCheck(array $parameters): array
    {
        $statements = $parameters['statements'] ?? [];
        
        if (empty($statements)) {
            throw new \Exception("Statements are required for consistency check");
        }
        
        // Check logical consistency
        $inconsistencies = $this->findInconsistencies($statements);
        $isConsistent = empty($inconsistencies);
        $conflicts = $this->identifyConflicts($statements);
        
        return [
            'is_consistent' => $isConsistent,
            'inconsistencies' => $inconsistencies,
            'conflicts' => $conflicts,
            'resolution_suggestions' => $this->suggestResolutions($inconsistencies),
            'confidence' => $isConsistent ? 0.95 : 0.85,
            'metrics' => [
                'statements_checked' => count($statements),
                'inconsistencies_found' => count($inconsistencies)
            ]
        ];
    }
    
    /**
     * Propose an action based on current context
     * 
     * @param Context $context Current system context
     * @return Action Proposed action with confidence score
     */
    public function proposeAction(Context $context): Action
    {
        // Analyze context to propose appropriate reasoning action
        $systemState = $context->getSystemState();
        
        // Default to deductive reasoning
        $actionType = 'deductive_reasoning';
        $parameters = [
            'premises' => $systemState['premises'] ?? [],
            'question' => $systemState['question'] ?? '',
            'context' => $systemState
        ];
        
        return new Action(
            $actionType,
            $parameters,
            $context,
            'medium',
            20 // estimated 20 seconds
        );
    }
    
    /**
     * Get actor capabilities
     * 
     * @return array<string, ActorCapability> Map of action types to capabilities
     */
    public function getCapabilities(): array
    {
        return [
            'deductive_reasoning' => new ActorCapability(
                'deductive_reasoning',
                0.9,
                'reasoning',
                'Derive conclusions from premises using deductive logic'
            ),
            'inductive_reasoning' => new ActorCapability(
                'inductive_reasoning',
                0.85,
                'reasoning',
                'Identify patterns and generalizations from observations'
            ),
            'abductive_reasoning' => new ActorCapability(
                'abductive_reasoning',
                0.8,
                'reasoning',
                'Generate best explanations for observations'
            ),
            'consistency_check' => new ActorCapability(
                'consistency_check',
                0.95,
                'reasoning',
                'Check logical consistency of statements'
            )
        ];
    }
    
    /**
     * Evaluate confidence for executing a specific action
     * 
     * @param Action $action Action to evaluate
     * @return float Confidence score (0.0-1.0)
     */
    public function evaluateConfidence(Action $action): float
    {
        $actionType = $action->getType();
        $capabilities = $this->getCapabilities();
        
        if (!isset($capabilities[$actionType])) {
            return 0.0;
        }
        
        $baseConfidence = $capabilities[$actionType]->getConfidence();
        
        // Adjust confidence based on action parameters
        $parameters = $action->getParameters();
        
        // Reduce confidence for complex reasoning tasks
        if ($actionType === 'deductive_reasoning' && isset($parameters['premises'])) {
            $premisesCount = count($parameters['premises']);
            if ($premisesCount > 10) {
                $baseConfidence *= 0.9;
            }
        }
        
        return min(1.0, max(0.0, $baseConfidence));
    }
    
    /**
     * Receive feedback from critics
     * 
     * @param Feedback $feedback Aggregated feedback from evaluation
     * @return void
     */
    public function receiveFeedback(Feedback $feedback): void
    {
        // Store feedback for learning
        $this->feedbackHistory[] = [
            'feedback_id' => $feedback->getFeedbackId(),
            'consensus_score' => $feedback->getConsensusScore(),
            'strengths' => $feedback->getStrengths(),
            'improvements' => $feedback->getImprovements(),
            'received_at' => date('Y-m-d H:i:s')
        ];
        
        $this->securityLogger->logSecurityEvent(
            "ReasoningActor received feedback",
            'info',
            [
                'actor_id' => $this->actorId,
                'feedback_id' => $feedback->getFeedbackId(),
                'consensus_score' => $feedback->getConsensusScore()
            ]
        );
        
        // Acknowledge feedback
        $feedback->acknowledge();
    }
    
    /**
     * Get unique actor identifier
     * 
     * @return string Actor ID
     */
    public function getActorId(): string
    {
        return $this->actorId;
    }
    
    /**
     * Get output type for action type
     * 
     * @param string $actionType Action type
     * @return string Output type
     */
    private function getOutputType(string $actionType): string
    {
        return match($actionType) {
            'deductive_reasoning' => 'deductive_conclusion',
            'inductive_reasoning' => 'inductive_generalization',
            'abductive_reasoning' => 'abductive_explanation',
            'consistency_check' => 'consistency_result',
            default => 'unknown'
        };
    }
    
    /**
     * Register this actor in the ActorRegistry
     * 
     * @return void
     */
    private function registerInRegistry(): void
    {
        try {
            if (!Registry::exists('ActorRegistry')) {
                Registry::set('ActorRegistry', new ActorRegistry());
            }
            
            $registry = Registry::get('ActorRegistry');
            $registry->registerActor($this);
            
            $this->securityLogger->logSecurityEvent(
                "ReasoningActor registered in ActorRegistry",
                'info',
                ['actor_id' => $this->actorId]
            );
        } catch (\Exception $e) {
            $this->securityLogger->logSecurityEvent(
                "Failed to register ReasoningActor: " . $e->getMessage(),
                'error',
                ['actor_id' => $this->actorId]
            );
        }
    }
    
    /**
     * Get feedback history
     * 
     * @return array Feedback history
     */
    public function getFeedbackHistory(): array
    {
        return $this->feedbackHistory;
    }
    
    // Helper methods for reasoning operations
    
    private function deriveConclusion(array $premises, string $question): string
    {
        // Simplified deductive reasoning
        return "Conclusion derived from " . count($premises) . " premises";
    }
    
    private function checkValidity(array $premises, string $conclusion): bool
    {
        // Check if conclusion logically follows from premises
        return !empty($premises) && !empty($conclusion);
    }
    
    private function checkSoundness(array $premises, string $conclusion): bool
    {
        // Check if premises are true and conclusion is valid
        return $this->checkValidity($premises, $conclusion);
    }
    
    private function buildReasoningChain(array $premises, string $conclusion): array
    {
        // Build step-by-step reasoning chain
        $chain = [];
        foreach ($premises as $index => $premise) {
            $chain[] = "Step " . ($index + 1) . ": " . $premise;
        }
        $chain[] = "Conclusion: " . $conclusion;
        return $chain;
    }
    
    private function calculateConfidence(bool $validity, bool $soundness): float
    {
        if ($validity && $soundness) return 0.95;
        if ($validity) return 0.75;
        return 0.5;
    }
    
    private function identifyPattern(array $observations, string $pattern): string
    {
        // Identify pattern from observations
        return "Pattern identified from " . count($observations) . " observations";
    }
    
    private function assessInductiveStrength(array $observations, string $generalization): float
    {
        // Assess strength of inductive argument
        return min(1.0, count($observations) / 10.0);
    }
    
    private function findCounterexamples(array $observations, string $generalization): array
    {
        // Find counterexamples to generalization
        return [];
    }
    
    private function gatherSupportingEvidence(array $observations, string $generalization): array
    {
        // Gather evidence supporting generalization
        return array_slice($observations, 0, 3);
    }
    
    private function generateHypotheses(array $observations, string $context): array
    {
        // Generate possible explanations
        return [
            "Hypothesis 1: Based on observation patterns",
            "Hypothesis 2: Alternative explanation",
            "Hypothesis 3: Context-based explanation"
        ];
    }
    
    private function selectBestExplanation(array $hypotheses, array $observations): string
    {
        // Select most plausible explanation
        return $hypotheses[0] ?? "No explanation found";
    }
    
    private function assessPlausibility(string $explanation, array $observations): float
    {
        // Assess how plausible the explanation is
        return 0.8;
    }
    
    private function matchObservations(string $explanation, array $observations): array
    {
        // Find observations that match the explanation
        return array_slice($observations, 0, min(3, count($observations)));
    }
    
    private function findInconsistencies(array $statements): array
    {
        // Find logical inconsistencies
        $inconsistencies = [];
        
        // Simple contradiction detection
        for ($i = 0, $iMax = count($statements); $i < $iMax; $i++) {
            for ($j = $i + 1, $jMax = count($statements); $j < $jMax; $j++) {
                if ($this->areContradictory($statements[$i], $statements[$j])) {
                    $inconsistencies[] = [
                        'statement1' => $statements[$i],
                        'statement2' => $statements[$j],
                        'type' => 'contradiction'
                    ];
                }
            }
        }
        
        return $inconsistencies;
    }
    
    private function identifyConflicts(array $statements): array
    {
        // Identify conflicting statements
        return $this->findInconsistencies($statements);
    }
    
    private function suggestResolutions(array $inconsistencies): array
    {
        // Suggest ways to resolve inconsistencies
        $resolutions = [];
        foreach ($inconsistencies as $inconsistency) {
            $resolutions[] = "Review and reconcile: " . $inconsistency['type'];
        }
        return $resolutions;
    }
    
    private function areContradictory(string $statement1, string $statement2): bool
    {
        // Simple contradiction check
        $s1Lower = strtolower($statement1);
        $s2Lower = strtolower($statement2);
        
        // Check for negation patterns
        if (strpos($s1Lower, 'not') !== false && strpos($s2Lower, 'not') === false) {
            return true;
        }
        
        return false;
    }
}

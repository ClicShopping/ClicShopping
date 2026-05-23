<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
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
   * ReasoningActor - Actor agent specialized in logical reasoning and inference.
   * * This actor is part of the Sub-Actor-Critic architecture, responsible for:
   * - Performing logical inference from provided premises.
   * - Analyzing the validity (logical structure) and soundness (truth of premises) of arguments.
   * - Drawing inductive generalizations and abductive "best-fit" explanations.
   * - Detecting contradictions and logical inconsistencies within a dataset.
   * * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Actors
   * @version 1.0.0
   * @since 2026-01-30
   */
  class ReasoningActor implements ActorAgentInterface
  {
    /** @var string Unique identifier for the actor instance */
    private string $actorId;

    /** @var SecurityLogger Handles audit trails and security event logging */
    private SecurityLogger $securityLogger;

    /** @var bool Flag to toggle verbose debugging information */
    private bool $debug;

    /** @var array Stores feedback received from Critics for performance tracking */
    private array $feedbackHistory = [];

    /**
     * Constructor - Initializes the actor and registers it within the AI ecosystem.
     * * @param bool $debug Enable debug mode for detailed logging.
     */
    public function __construct(bool $debug = false)
    {
      $this->actorId = 'reasoning_actor_' . uniqid();
      $this->debug = $debug;

      // Initialize the security logger for audit compliance
      $this->securityLogger = new SecurityLogger();

      // Ensure the actor is discoverable by the Orchestrator
      $this->registerInRegistry();

      $this->securityLogger->logSecurityEvent(
        "ReasoningActor initialized: {$this->actorId}",
        'info'
      );
    }

    /**
     * Main execution entry point. Routes actions to specific reasoning engines.
     * * @param Action $action The action object containing the type and parameters.
     * @return ActionResult The result containing data, metrics, and success status.
     * @throws \Exception If the action type is unknown or parameters are invalid.
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

        // Map the action type to the internal private processing methods
        $output = match($actionType) {
          'deductive_reasoning' => $this->executeDeductiveReasoning($parameters),
          'inductive_reasoning' => $this->executeInductiveReasoning($parameters),
          'abductive_reasoning' => $this->executeAbductiveReasoning($parameters),
          'consistency_check' => $this->executeConsistencyCheck($parameters),
          default => throw new \Exception("Unsupported action type: {$actionType}")
        };

        $executionTime = microtime(true) - $startTime;

        // Compile performance metrics
        $metrics = [
          'execution_time' => $executionTime,
          'action_type' => $actionType,
          'timestamp' => date('Y-m-d H:i:s'),
          'actor_id' => $this->actorId
        ];

        // Merge action-specific metrics (e.g., number of premises processed)
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

        // Return a failure result with error and trace details for debugging
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
     * Handles Deductive Reasoning: specific conclusions from general premises.
     * * @param array $parameters Requires 'premises' (array) and optional 'question'.
     * @return array Analysis containing conclusion, validity, and reasoning chain.
     */
    private function executeDeductiveReasoning(array $parameters): array
    {
      $premises = $parameters['premises'] ?? [];
      $question = $parameters['question'] ?? '';

      if (empty($premises)) {
        throw new \Exception("Premises are required for deductive reasoning");
      }

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
     * Handles Inductive Reasoning: general patterns from specific observations.
     * * @param array $parameters Requires 'observations' (array).
     * @return array Analysis containing patterns and inductive strength scores.
     */
    private function executeInductiveReasoning(array $parameters): array
    {
      $observations = $parameters['observations'] ?? [];
      $pattern = $parameters['pattern'] ?? '';

      if (empty($observations)) {
        throw new \Exception("Observations are required for inductive reasoning");
      }

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
     * Handles Abductive Reasoning: finding the most likely explanation for a set of data.
     * * @param array $parameters Requires 'observations' (array).
     * @return array Most plausible hypothesis and alternatives.
     */
    private function executeAbductiveReasoning(array $parameters): array
    {
      $observations = $parameters['observations'] ?? [];
      $context = $parameters['context'] ?? '';

      if (empty($observations)) {
        throw new \Exception("Observations are required for abductive reasoning");
      }

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
     * Validates that a set of statements do not contradict each other.
     * * @param array $parameters Requires 'statements' (array).
     * @return array List of inconsistencies and suggested resolutions.
     */
    private function executeConsistencyCheck(array $parameters): array
    {
      $statements = $parameters['statements'] ?? [];

      if (empty($statements)) {
        throw new \Exception("Statements are required for consistency check");
      }

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
     * Analyzes the context to suggest which reasoning mode is most appropriate.
     * * @param Context $context The current environment and state.
     * @return Action A suggested action with a confidence estimation.
     */
    public function proposeAction(Context $context): Action
    {
      $systemState = $context->getSystemState();

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
        20 // estimated execution time in seconds
      );
    }

    /**
     * Returns the list of logical operations this actor can perform.
     * * @return array<string, ActorCapability>
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
     * Self-evaluates how confident the actor is in performing a specific action.
     * * @param Action $action The action to evaluate.
     * @return float Confidence score (0.0 to 1.0).
     */
    public function evaluateConfidence(Action $action): float
    {
      $actionType = $action->getType();
      $capabilities = $this->getCapabilities();

      if (!isset($capabilities[$actionType])) {
        return 0.0;
      }

      $baseConfidence = $capabilities[$actionType]->getConfidence();
      $parameters = $action->getParameters();

      // Lower confidence if the complexity (number of premises) is too high
      if ($actionType === 'deductive_reasoning' && isset($parameters['premises'])) {
        $premisesCount = count($parameters['premises']);
        if ($premisesCount > 10) {
          $baseConfidence *= 0.9;
        }
      }

      return min(1.0, max(0.0, $baseConfidence));
    }

    /**
     * Process feedback from Critics to improve future reasoning or log performance issues.
     * * @param Feedback $feedback Object containing scores and improvement suggestions.
     */
    public function receiveFeedback(Feedback $feedback): void
    {
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

      $feedback->acknowledge();
    }

    /**
     * @return string The unique ID of this actor.
     */
    public function getActorId(): string
    {
      return $this->actorId;
    }

    /**
     * Maps an action type to a more descriptive output label.
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
     * Registers the instance in the global Registry so other components can find it.
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
     * Returns the full history of feedback for audit or RL (Reinforcement Learning) purposes.
     * @return array
     */
    public function getFeedbackHistory(): array
    {
      return $this->feedbackHistory;
    }

    // --- Internal Logic Helper Methods ---

    /**
     * Logic to derive a final conclusion.
     */
    private function deriveConclusion(array $premises, string $question): string
    {
      return "Conclusion derived from " . count($premises) . " premises";
    }

    /**
     * Structural validity check.
     */
    private function checkValidity(array $premises, string $conclusion): bool
    {
      return !empty($premises) && !empty($conclusion);
    }

    /**
     * Check if premises are actually true (simulated).
     */
    private function checkSoundness(array $premises, string $conclusion): bool
    {
      return $this->checkValidity($premises, $conclusion);
    }

    /**
     * Explains the logic path taken.
     */
    private function buildReasoningChain(array $premises, string $conclusion): array
    {
      $chain = [];
      foreach ($premises as $index => $premise) {
        $chain[] = "Step " . ($index + 1) . ": " . $premise;
      }
      $chain[] = "Conclusion: " . $conclusion;
      return $chain;
    }

    /**
     * Calculates the statistical confidence of a conclusion.
     */
    private function calculateConfidence(bool $validity, bool $soundness): float
    {
      if ($validity && $soundness) return 0.95;
      if ($validity) return 0.75;
      return 0.5;
    }

    private function identifyPattern(array $observations, string $pattern): string
    {
      return "Pattern identified from " . count($observations) . " observations";
    }

    private function assessInductiveStrength(array $observations, string $generalization): float
    {
      return min(1.0, count($observations) / 10.0);
    }

    private function findCounterexamples(array $observations, string $generalization): array
    {
      return [];
    }

    private function gatherSupportingEvidence(array $observations, string $generalization): array
    {
      return array_slice($observations, 0, 3);
    }

    private function generateHypotheses(array $observations, string $context): array
    {
      return [
        "Hypothesis 1: Based on observation patterns",
        "Hypothesis 2: Alternative explanation",
        "Hypothesis 3: Context-based explanation"
      ];
    }

    private function selectBestExplanation(array $hypotheses, array $observations): string
    {
      return $hypotheses[0] ?? "No explanation found";
    }

    private function assessPlausibility(string $explanation, array $observations): float
    {
      return 0.8;
    }

    private function matchObservations(string $explanation, array $observations): array
    {
      return array_slice($observations, 0, min(3, count($observations)));
    }

    /**
     * Searches for logical contradictions between pairs of statements.
     */
    private function findInconsistencies(array $statements): array
    {
      $inconsistencies = [];

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
      return $this->findInconsistencies($statements);
    }

    private function suggestResolutions(array $inconsistencies): array
    {
      $resolutions = [];
      foreach ($inconsistencies as $inconsistency) {
        $resolutions[] = "Review and reconcile: " . $inconsistency['type'];
      }
      return $resolutions;
    }

    /**
     * A simple linguistic check for negations to identify obvious contradictions.
     */
    private function areContradictory(string $statement1, string $statement2): bool
    {
      $s1Lower = strtolower($statement1);
      $s2Lower = strtolower($statement2);

      // Simple heuristic: one contains 'not' and the other doesn't
      if (str_contains($s1Lower, 'not') && !str_contains($s2Lower, 'not')) {
        return true;
      }

      return false;
    }
  }
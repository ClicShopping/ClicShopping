<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Actors;

use ClicShopping\AI\InterfacesAI\ActorAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCapability;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents\SeoOptimizationAgent;
use ClicShopping\AI\Security\SecurityLogger;


/**
 * SeoOptimizationActor
 *
 * Role:
 * Structural wrapper between the Actor-Critic orchestration layer
 * and the domain-level SeoOptimizationAgent.
 *
 * Responsibilities:
 * - Declare capabilities to the ActorRegistry.
 * - Delegate execution to the underlying SEO agent.
 * - Enrich execution metrics (timing, wrapper layer).
 * - Normalize results into ActionResult objects.
 * - Log security-relevant events.
 *
 * This class contains no SEO logic.
 * This class contains no optimization strategy.
 * It is a transactional and orchestration adapter only.
 */
class SeoOptimizationActor implements ActorAgentInterface
{
  /**
   * Unique runtime identifier for this actor instance.
   * Used by the orchestrator for correlation and tracing.
   */
  private string $actorId;

  /**
   * Debug mode flag.
   * Can be used to alter behavior or verbosity if needed.
   */
  private bool $debug;

  /**
   * Domain-level SEO optimization agent.
   * Performs the actual optimization logic.
   */
  private SeoOptimizationAgent $agent;

  /**
   * Centralized security logger.
   * Ensures traceability of AI-driven actions.
   */
  private SecurityLogger $securityLogger;

  /**
   * In-memory feedback history.
   * Not persisted.
   */
  private array $feedbackHistory = [];

  /**
   * Constructor.
   *
   * - Generates a unique actor ID.
   * - Instantiates the SEO agent if not injected.
   * - Optionally registers the actor into the ActorRegistry.
   */
  public function __construct(
    bool                  $debug = false,
    ?ActorRegistry        $registry = null,
    ?SeoOptimizationAgent $agent = null
  )
  {
    $this->actorId = 'seo_optimization_actor_' . uniqid();
    $this->debug = $debug;
    $this->agent = $agent ?? new SeoOptimizationAgent();
    $this->securityLogger = new SecurityLogger();

    if ($registry !== null) {
      $registry->registerActor($this);
    }
  }

  /**
   * Executes a given action.
   *
   * Execution flow:
   * 1. Log security event.
   * 2. Delegate execution to the underlying SEO agent.
   * 3. Enrich execution metrics with wrapper timing.
   * 4. Return a normalized ActionResult instance.
   *
   * Error handling:
   * Any exception is caught and transformed into a failed ActionResult.
   */
  public function executeAction(Action $action): ActionResult
  {
    $start = microtime(true);

    try {
      $this->securityLogger->logSecurityEvent(
        'SeoOptimizationActor executing action',
        'info',
        ['actor_id' => $this->actorId, 'action_id' => $action->getActionId()]
      );

      $result = $this->agent->executeAction($action);

      $metrics = $result->getExecutionMetrics();
      $metrics['actor_wrapper_ms'] = (int)((microtime(true) - $start) * 1000);

      $actionResult = new ActionResult(
        $action->getActionId(),
        $this->actorId,
        $result->getOutput(),
        $result->getOutputType(),
        $metrics,
        $result->getExecutionContext(),
        $result->getStatus()
      );

      $this->persistExecution($action->getActionId(), $actionResult, $start);

      return $actionResult;
    } catch (\Throwable $e) {

      $metrics = [
        'actor_wrapper_ms' => (int)((microtime(true) - $start) * 1000),
        'error' => $e->getMessage(),
      ];

      return new ActionResult(
        $action->getActionId(),
        $this->actorId,
        ['error' => $e->getMessage()],
        'seo_proposal',
        $metrics,
        $action->getContext(),
        'failed'
      );
    }
  }

  /**
   * Proposes a default action for this actor.
   *
   * Defines:
   * - Action name: "seo_optimize"
   * - No initial parameters
   * - High priority (90)
   */
  public function proposeAction(Context $context): Action
  {
    return new Action('seo_optimize', [], $context, 'high', 90);
  }

  /**
   * Declares this actor’s capabilities.
   *
   * Structure:
   * - Action name
   * - Structural confidence score
   * - Domain
   * - Expertise level
   * - Required input signals
   */
  public function getCapabilities(): array
  {
    return [
      'seo_optimize' => new ActorCapability(
        'seo_optimize',
        0.8,
        'seo',
        'expert',
        ['serp_report', 'current_content']
      ),
    ];
  }

  /**
   * Returns a confidence score for a given action.
   *
   * Currently constant.
   * Can evolve toward context-aware scoring.
   */
  public function evaluateConfidence(Action $action): float
  {
    return 0.8;
  }

  /**
   * Receives feedback from the critic layer.
   *
   * Stores feedback locally.
   * No adaptive learning logic implemented here.
   */
  public function receiveFeedback(Feedback $feedback): void
  {
    $this->feedbackHistory[] = [
      'feedback_id' => $feedback->getFeedbackId(),
      'received_at' => date('Y-m-d H:i:s'),
      'summary' => $feedback->getCategorizedFeedback(),
    ];
  }

  /**
   * Persist execution record so FeedbackManager::getProducerActorId() can resolve
   * which actor produced a given result_id (result_id → actor_id mapping).
   * Non-blocking — failures are logged in debug mode only.
   */
  private function persistExecution(string $actionId, ActionResult $result, float $start): void
  {
    try {
      $db = Registry::get('Db');

      $sql = 'INSERT INTO :table_rag_agent_actor_executions
                (action_id, result_id, actor_id, action_type, status, execution_time_ms,
                 quality_score, output_type, executed_at)
              VALUES
                (:action_id, :result_id, :actor_id, :action_type, :status,
                 :execution_time_ms, :quality_score, :output_type, NOW())';

      $stmt = $db->prepare($sql);
      $stmt->bindValue(':action_id',         $actionId);
      $stmt->bindValue(':result_id',         $result->getResultId());
      $stmt->bindValue(':actor_id',          $this->actorId);
      $stmt->bindValue(':action_type',       'seo_optimize');
      $stmt->bindValue(':status',            $result->getStatus());
      $stmt->bindValue(':execution_time_ms', (int)((microtime(true) - $start) * 1000), \PDO::PARAM_INT);
      $stmt->bindValue(':quality_score',     null, \PDO::PARAM_NULL);
      $stmt->bindValue(':output_type',       $result->getOutputType());
      $stmt->execute();

    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('SeoOptimizationActor: persistExecution failed - ' . $e->getMessage());
      }
    }
  }

  /**
   * Returns the runtime actor identifier.
   */
  public function getActorId(): string
  {
    return $this->actorId;
  }
}
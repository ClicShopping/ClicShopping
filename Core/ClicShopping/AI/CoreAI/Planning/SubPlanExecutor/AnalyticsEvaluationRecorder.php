<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Planning\SubPlanExecutor;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Analytics\Agent\AnalyticsAgent;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ConsensusBuilder;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Critics\AnalyticsCriticWrapper;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Critics\SqlQualityCriticWrapper;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\CriticDataCollector;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\LLMWeightingEngine;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\LLMPromptBuilder;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightNormalizer;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightAuditLogger;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightedConsensusBuilder;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationStore;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationTracker;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\AgentEvaluation;
use ClicShopping\AI\Config\AgentCriticsConfig;
use ClicShopping\AI\Config\AgentSystemConfig;
use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\AI\DomainsAI\Analytics\Validator\SqlPerformanceValidator;
use ClicShopping\AI\DomainsAI\Analytics\Validator\SqlQualityValidator;
use ClicShopping\AI\DomainsAI\Analytics\Validator\SqlSecurityValidator;

/**
 * AnalyticsEvaluationRecorder
 *
 * Evaluation / adaptive-weighting / reputation recording concern extracted
 * verbatim from AnalyticsExecutor (2026-06-20). Owns the side-effect of
 * persisting an analytics run's actor-critic evaluation, weighted consensus,
 * coordination result, reputation outcomes and slow-query objective.
 * Dependencies (logger, debug, userId, languageId) are injected by
 * AnalyticsExecutor; the optional AnalyticsAgent is passed per call to keep
 * its lazy-initialisation semantics in the executor.
 */
class AnalyticsEvaluationRecorder
{
  private SecurityLogger $logger;
  private bool $debug;
  private string $userId;
  private int $languageId;

  public function __construct(SecurityLogger $logger, bool $debug, string $userId, int $languageId)
  {
    $this->logger = $logger;
    $this->debug = $debug;
    $this->userId = $userId;
    $this->languageId = $languageId;
  }

  /**
   * Record analytics execution and critic evaluation based on real output.
   * Uses AnalyticsCritic and records to rag_agent_actor_executions and rag_agent_critic_evaluations.
   */
  public function recordAnalyticsEvaluation(string $query, array $rawResult, int $executionTimeMs): void
  {
    $sql = $rawResult['sql_query'] ?? '';
    if ($sql === '') {
      return;
    }

    try {
      $context = new Context(
        $this->userId,
        $this->languageId,
        ['user_query' => $query, 'intent' => 'analytics'],
        [],
        ['source' => 'AnalyticsExecutor']
      );

      $actionId = 'analytics_query_' . uniqid('', true);
      $producerId = 'analytics_agent';

      $actionResult = new ActionResult(
        $actionId,
        $producerId,
        [
          'sql' => $sql,
          'explanation' => $rawResult['interpretation'] ?? '',
          'tables_used' => $rawResult['tables_used'] ?? [],
          'query_type' => $rawResult['query_type'] ?? 'unknown'
        ],
        'sql_query',
        [
          'execution_time_ms' => $executionTimeMs,
          'timestamp' => date('Y-m-d H:i:s')
        ],
        $context,
        'success'
      );

      $actorRegistry = new ActorRegistry();
      $actorRegistry->recordExecution(
        $producerId,
        $actionId,
        $actionResult->getResultId(),
        'analytics_query',
        'success',
        $executionTimeMs,
        null,
        'sql_query'
      );

      // AC (AgentCriticsConfig) controls the critic-of-results on analytics output.
      // Reliability-first: AC defaults ON, so the critic (quality/SQL evaluation + consensus +
      // adaptive weighting) runs by default and can question every result; an admin may
      // disable it deliberately via the AC config UI. The actor execution above is still
      // recorded regardless.
      if (!AgentCriticsConfig::isEnabled()) {
        return;
      }

      $criticRegistry = new CriticRegistry();

      // Create AnalyticsCriticWrapper with proper dependencies
      $qualityValidator = new SqlQualityValidator();
      $securityValidator = new SqlSecurityValidator(null, null, $this->debug);
      $performanceValidator = new SqlPerformanceValidator(null, $this->debug);
      
      // Get PDO for DatabaseSchemaManager
      $entityManager = \ClicShopping\AI\Infrastructure\Orm\DoctrineOrm::getEntityManager();
      $pdo = $entityManager->getConnection()->getNativeConnection();
      $securityLogger = new \ClicShopping\AI\Security\SecurityLogger();
      $schemaManager = new \ClicShopping\AI\DomainsAI\Analytics\Agent\DatabaseSchemaManager($pdo, $securityLogger, $this->debug);
      $schemaValidator = new \ClicShopping\AI\DomainsAI\Analytics\Validator\SchemaValidator($schemaManager, $this->debug);
      
      $evaluator = new \ClicShopping\AI\DomainsAI\Analytics\Validator\AnalyticsQualityEvaluator(
        $qualityValidator,
        $securityValidator,
        $performanceValidator,
        $schemaValidator,
        $this->debug
      );
      
      $critic = new AnalyticsCriticWrapper($evaluator, $this->debug);

      $evaluationStart = microtime(true);
      $evaluation = $critic->evaluateAction($actionResult);
      
      // Create SqlQualityCriticWrapper with proper dependencies
      $secondCritic = new SqlQualityCriticWrapper($qualityValidator, $this->debug);
      $secondEvaluation = $secondCritic->evaluateAction($actionResult);
      $evaluationTimeMs = (int)round((microtime(true) - $evaluationStart) * 1000);

      $criticRegistry->recordEvaluation(
        $critic->getCriticId(),
        $evaluation->getEvaluationId(),
        $actionResult->getResultId(),
        $actionResult->getOutputType(),
        $actionResult->getProducerAgentId(),
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

      $criticRegistry->recordEvaluation(
        $secondCritic->getCriticId(),
        $secondEvaluation->getEvaluationId(),
        $actionResult->getResultId(),
        $actionResult->getOutputType(),
        $actionResult->getProducerAgentId(),
        [
          'accuracy' => $secondEvaluation->getAccuracyScore(),
          'completeness' => $secondEvaluation->getCompletenessScore(),
          'efficiency' => $secondEvaluation->getEfficiencyScore(),
          'clarity' => $secondEvaluation->getClarityScore()
        ],
        $secondEvaluation->getOverallScore(),
        $secondEvaluation->getFeedback(),
        $secondEvaluation->getStrengths(),
        $secondEvaluation->getImprovements(),
        $evaluationTimeMs
      );

      $consensusBuilder = new ConsensusBuilder();
      $consensus = $consensusBuilder->buildConsensus([$evaluation, $secondEvaluation]);

      $consensusScore = $consensus->getScore();
      if (AgentSystemConfig::isAdaptiveWeightingEnabled()) {
        $adaptiveScore = $this->recordAdaptiveWeightingConsensus(
          $actionResult,
          [$evaluation, $secondEvaluation],
          [$critic, $secondCritic]
        );
        if ($adaptiveScore !== null) {
          $consensusScore = $adaptiveScore;
        }
      }

      $this->storeCoordinationResult(
        $actionResult,
        $consensus,
        $consensusScore,
        2,
        $executionTimeMs,
        $evaluationTimeMs
      );

      if (AgentSystemConfig::isReputationSystemEnabled()) {
        $this->recordReputationOutcomes(
          [$evaluation, $secondEvaluation],
          $consensusScore
        );
      }
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AnalyticsExecutor: Failed to record analytics evaluation - " . $e->getMessage(),
          'warning'
        );
      }
    }
  }

  private function recordAdaptiveWeightingConsensus(
    ActionResult $actionResult,
    array $evaluations,
    array $critics
  ): ?float {
    try {
      $criticRegistry = Registry::exists('CriticRegistry') ? Registry::get('CriticRegistry') : new CriticRegistry();

      $criticDataCollector = new CriticDataCollector(
        new ReputationStore(),
        $criticRegistry
      );

      $weightingEngine = new LLMWeightingEngine(
        $criticDataCollector,
        new LLMPromptBuilder(),
        new WeightNormalizer(),
        new WeightAuditLogger()
      );

      $evaluationContext = [
        'evaluation_id' => 'eval_' . $actionResult->getResultId(),
        'output_type' => $actionResult->getOutputType(),
        'priority' => 'medium',
        'action_type' => 'analytics_query',
        'required_domains' => ['analytics'],
        'execution_metrics' => $actionResult->getExecutionMetrics(),
        'special_requirements' => []
      ];

      $weightResult = $weightingEngine->calculateAdaptiveWeights($critics, $evaluationContext);

      $weightedConsensusBuilder = new WeightedConsensusBuilder();
      $consensusResult = $weightedConsensusBuilder->buildDynamicConsensus($evaluations, $weightResult);

      return $consensusResult->getDynamicConsensus();
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AnalyticsExecutor: Adaptive weighting failed - " . $e->getMessage(),
          'warning'
        );
      }
      return null;
    }
  }

  private function storeCoordinationResult(
    ActionResult $actionResult,
    $consensus,
    float $consensusScore,
    int $numCritics,
    int $executionTimeMs,
    int $evaluationTimeMs
  ): void {
    try {
      if (!$this->tableExists('rag_agent_coordinated_results')) {
        return;
      }

      $db = Registry::get('Db');
      $totalTimeMs = $executionTimeMs + $evaluationTimeMs;

      $sql = "INSERT INTO :table_rag_agent_coordinated_results
              (coordination_id, action_id, result_id, actor_id, consensus_id,
               consensus_score, num_evaluations, num_critics, execution_time_ms,
               evaluation_time_ms, total_time_ms, created_at)
              VALUES (:coordination_id, :action_id, :result_id, :actor_id, :consensus_id,
                      :consensus_score, :num_evaluations, :num_critics, :execution_time_ms,
                      :evaluation_time_ms, :total_time_ms, :created_at)";

      $stmt = $db->prepare($sql);
      $stmt->bindValue(':coordination_id', 'coord_' . uniqid('', true));
      $stmt->bindValue(':action_id', $actionResult->getActionId());
      $stmt->bindValue(':result_id', $actionResult->getResultId());
      $stmt->bindValue(':actor_id', $actionResult->getProducerAgentId());
      $stmt->bindValue(':consensus_id', $consensus->getConsensusId());
      $stmt->bindValue(':consensus_score', $consensusScore);
      $stmt->bindValue(':num_evaluations', $numCritics);
      $stmt->bindValue(':num_critics', $numCritics);
      $stmt->bindValue(':execution_time_ms', $executionTimeMs, \PDO::PARAM_INT);
      $stmt->bindValue(':evaluation_time_ms', $evaluationTimeMs, \PDO::PARAM_INT);
      $stmt->bindValue(':total_time_ms', $totalTimeMs, \PDO::PARAM_INT);
      $stmt->bindValue(':created_at', date('Y-m-d H:i:s'));
      $stmt->execute();
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AnalyticsExecutor: Failed to store coordination result - " . $e->getMessage(),
          'warning'
        );
      }
    }
  }

  private function tableExists(string $tableName): bool
  {
    try {
      $db = Registry::get('Db');
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      $fullTableName = $prefix . $tableName;
      $sql = "SHOW TABLES LIKE ?";
      $stmt = $db->prepare($sql);
      $stmt->execute([$fullTableName]);
      return $stmt->rowCount() > 0;
    } catch (\Exception $e) {
      return false;
    }
  }

  private function recordReputationOutcomes(array $evaluations, float $consensusScore): void
  {
    try {
      $tracker = new ReputationTracker();

      foreach ($evaluations as $evaluation) {
        $agentEvaluation = new AgentEvaluation(
          $evaluation->getEvaluatorAgentId(),
          $evaluation->getOutputId(),
          [
            'accuracy' => $evaluation->getAccuracyScore(),
            'completeness' => $evaluation->getCompletenessScore(),
            'efficiency' => $evaluation->getEfficiencyScore(),
            'clarity' => $evaluation->getClarityScore()
          ],
          $evaluation->getFeedback(),
          $evaluation->getStrengths(),
          $evaluation->getImprovements()
        );

        $tracker->trackEvaluation($agentEvaluation, $consensusScore, false);
      }
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AnalyticsExecutor: Failed to record reputation outcomes - " . $e->getMessage(),
          'warning'
        );
      }
    }
  }

  public function registerSlowQueryObjective(string $query, int $executionTimeMs, ?AnalyticsAgent $analyticsAgent = null): void
  {
    if ($executionTimeMs < 1500 || $analyticsAgent === null) {
      return;
    }

    try {
      $goalStatement = 'Optimize analytics query performance';
      $successCriteria = [
        'query' => $query,
        'max_execution_time_ms' => 1000
      ];
      $priority = $executionTimeMs >= 5000 ? 'high' : 'medium';

      $analyticsAgent->createLocalObjective($goalStatement, $successCriteria, $priority);
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AnalyticsExecutor: Failed to register autonomous objective - " . $e->getMessage(),
          'warning'
        );
      }
    }
  }
}

<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\ServicesAI\ActorCritic;

use ClicShopping\AI\InterfacesAI\ActorCriticInitializerInterface;
use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\AI\Config\AgentActorsConfig;
use ClicShopping\AI\Config\AgentCriticsConfig;
use ClicShopping\AI\Config\AgentActivationConfig;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * Class ActorCriticInitializer
 *
 * Handles initialization of the Actor-Critic system by registering
 * all enabled actors and critics in their respective registries.
 *
 * This class centralizes actor/critic registration logic that was
 * previously embedded in OrchestratorAgent, improving separation
 * of concerns and testability.
 *
 * @package ClicShopping\AI\ServicesAI\ActorCritic
 */
class ActorCriticInitializer implements ActorCriticInitializerInterface
{
  private SecurityLogger $securityLogger;
  private array $stats = [];

  /**
   * Constructor
   *
   * @param SecurityLogger $securityLogger Logger for initialization events
   */
  public function __construct(SecurityLogger $securityLogger)
  {
    $this->securityLogger = $securityLogger;
  }

  /**
   * Initialize Actor-Critic system
   *
   * Creates ActorRegistry and CriticRegistry, then registers all
   * enabled actors and critics based on configuration.
   *
   * @param int $languageId Language ID for actors/critics
   * @param bool $debug Debug mode flag for logging
   * @return array Initialization result with counts and status
   * @throws \Exception If initialization fails
   */
  public function initialize(int $languageId, bool $debug): array
  {
    try {
      // Create registries
      $actorRegistry = new ActorRegistry();
      $criticRegistry = new CriticRegistry();

      $actorsRegistered = 0;
      $criticsRegistered = 0;

      // Register actors
      $actorsRegistered = $this->registerActors($actorRegistry, $languageId, $debug);

      // Register critics
      $criticsRegistered = $this->registerCritics($criticRegistry, $languageId, $debug);

      // Store stats
      $this->stats = [
        'actors_registered' => $actorsRegistered,
        'critics_registered' => $criticsRegistered,
        'actors_config_enabled' => AgentActorsConfig::isEnabled(),
        'critics_config_enabled' => AgentCriticsConfig::isEnabled(),
        'success' => true
      ];

      if ($debug) {
        $this->securityLogger->logStructured('info', 'ActorCriticInitializer', 'initialization_complete', $this->stats);
      }

      return $this->stats;

    } catch (\Exception $e) {
      if ($debug) {
        $this->securityLogger->logStructured('error', 'ActorCriticInitializer', 'initialization_failed', [
          'error' => $e->getMessage(),
          'trace' => $e->getTraceAsString()
        ]);
      }
      throw $e;
    }
  }

  /**
   * Register all enabled actors
   *
   * Registers actors based on AgentActorsConfig and AgentActivationConfig.
   * Only registers actors that are both globally enabled and individually activated.
   *
   * @param ActorRegistry $registry Actor registry instance
   * @param int $languageId Language ID for actors
   * @param bool $debug Debug mode flag
   * @return int Number of actors registered
   */
  private function registerActors(ActorRegistry $registry, int $languageId, bool $debug): int
  {
    $count = 0;

    if (!AgentActorsConfig::isEnabled()) {
      if ($debug) {
        $this->securityLogger->logStructured('warning', 'ActorCriticInitializer', 'actors_disabled', [
          'message' => 'AgentActorsConfig is disabled, no actors registered'
        ]);
      }
      return $count;
    }

    // Register AnalyticsActor
    if (AgentActorsConfig::isAnalyticsEnabled() && AgentActivationConfig::isAgentEnabled('analytics_actor')) {
      $registry->registerActor(new \ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Actors\AnalyticsActor($languageId, $debug));
      $count++;
      if ($debug) {
        $this->securityLogger->logStructured('info', 'ActorCriticInitializer', 'actor_registered', [
          'actor' => 'AnalyticsActor'
        ]);
      }
    }

    // Register ReasoningActor
    if (AgentActorsConfig::isReasoningEnabled() && AgentActivationConfig::isAgentEnabled('reasoning_actor')) {
      $registry->registerActor(new \ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Actors\ReasoningActor($languageId, $debug));
      $count++;
      if ($debug) {
        $this->securityLogger->logStructured('info', 'ActorCriticInitializer', 'actor_registered', [
          'actor' => 'ReasoningActor'
        ]);
      }
    }

    // Register ValidationActor
    if (AgentActorsConfig::isValidationEnabled() && AgentActivationConfig::isAgentEnabled('validation_actor')) {
      $registry->registerActor(new \ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Actors\ValidationActor($languageId, $debug));
      $count++;
      if ($debug) {
        $this->securityLogger->logStructured('info', 'ActorCriticInitializer', 'actor_registered', [
          'actor' => 'ValidationActor'
        ]);
      }
    }

    return $count;
  }

  /**
   * Register all enabled critics
   *
   * Registers critics based on AgentCriticsConfig and AgentActivationConfig.
   * Only registers critics that are both globally enabled and individually activated.
   *
   * @param CriticRegistry $registry Critic registry instance
   * @param int $languageId Language ID for critics
   * @param bool $debug Debug mode flag
   * @return int Number of critics registered
   */
  private function registerCritics(CriticRegistry $registry, int $languageId, bool $debug): int
  {
    $count = 0;

    if (!AgentCriticsConfig::isEnabled()) {
      if ($debug) {
        $this->securityLogger->logStructured('warning', 'ActorCriticInitializer', 'critics_disabled', [
          'message' => 'AgentCriticsConfig is disabled, no critics registered'
        ]);
      }
      return $count;
    }

    // Register AnalyticsCriticWrapper (new refactored version)
    if (AgentCriticsConfig::isAnalyticsExpertEnabled() && AgentActivationConfig::isAgentEnabled('analytics_critic')) {
      try {
        // Create validator dependencies
        $qualityValidator = new \ClicShopping\AI\DomainsAI\Analytics\Validator\SqlQualityValidator();
        $securityValidator = new \ClicShopping\AI\DomainsAI\Analytics\Validator\SqlSecurityValidator(null, null, $debug);
        $performanceValidator = new \ClicShopping\AI\DomainsAI\Analytics\Validator\SqlPerformanceValidator(null, $debug);
        
        // SchemaValidator requires DatabaseSchemaManager which needs PDO and SecurityLogger
        // Use Doctrine ORM as per AGENTS.md rules for AI layer
        $entityManager = \ClicShopping\AI\Infrastructure\Orm\DoctrineOrm::getEntityManager();
        $pdo = $entityManager->getConnection()->getNativeConnection();
        $schemaManager = new \ClicShopping\AI\DomainsAI\Analytics\Agent\DatabaseSchemaManager($pdo, $this->securityLogger, $debug);
        $schemaValidator = new \ClicShopping\AI\DomainsAI\Analytics\Validator\SchemaValidator($schemaManager, $debug);
        
        // Create evaluator with all validators
        $evaluator = new \ClicShopping\AI\DomainsAI\Analytics\Validator\AnalyticsQualityEvaluator(
          $qualityValidator,
          $securityValidator,
          $performanceValidator,
          $schemaValidator,
          $debug
        );
        
        // Create wrapper with evaluator
        $registry->registerCritic(new \ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Critics\AnalyticsCriticWrapper($evaluator, $debug));
        $count++;
        if ($debug) {
          $this->securityLogger->logStructured('info', 'ActorCriticInitializer', 'critic_registered', [
            'critic' => 'AnalyticsCriticWrapper'
          ]);
        }
      } catch (\Exception $e) {
        if ($debug) {
          $this->securityLogger->logStructured('error', 'ActorCriticInitializer', 'analytics_critic_registration_failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
          ]);
        }
        // Continue without AnalyticsCritic if registration fails
      }
    }

    // Register ReasoningCritic (maps to generalist)
    if (AgentCriticsConfig::isGeneralistEnabled() && AgentActivationConfig::isAgentEnabled('reasoning_critic')) {
      $registry->registerCritic(new \ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Critics\ReasoningCritic($languageId, $debug));
      $count++;
      if ($debug) {
        $this->securityLogger->logStructured('info', 'ActorCriticInitializer', 'critic_registered', [
          'critic' => 'ReasoningCritic'
        ]);
      }
    }

    // Register ValidationCritic (maps to specialist)
    if (AgentCriticsConfig::isSpecialistEnabled() && AgentActivationConfig::isAgentEnabled('validation_critic')) {
      $registry->registerCritic(new \ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Critics\ValidationCritic($languageId, $debug));
      $count++;
      if ($debug) {
        $this->securityLogger->logStructured('info', 'ActorCriticInitializer', 'critic_registered', [
          'critic' => 'ValidationCritic'
        ]);
      }
    }

    return $count;
  }

  /**
   * Get initialization statistics
   *
   * @return array Statistics from last initialization
   */
  public function getStats(): array
  {
    return $this->stats;
  }
}

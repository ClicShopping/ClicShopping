<?php
/**
 * SubTaskPlannerSemanticSearch
 * 
 * Planner spécialisé pour la recherche sémantique
 * Responsibility : Createsr des plans pour recherches dans la base de connaissances
 */

namespace ClicShopping\AI\CoreAI\Planning\SubTaskPlanning;


use ClicShopping\AI\CoreAI\Planning\TaskStep;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Rag\MultiDBRAGManager;


class SubTaskPlannerSemanticSearch
{
  private bool $debug;
  private ?SecurityLogger $securityLogger;

  public function __construct(bool $debug = false, ?SecurityLogger $securityLogger = null)
  {
    $this->debug = $debug;
    $this->securityLogger = $securityLogger;
    $this->ragManager = new MultiDBRAGManager();
  }

  /**
   * Detects si une requête concerne la recherche sémantique
   * Note: Ce planificateur est utilisé par défaut pour les requêtes de type 'semantic_search'
   */
  public function canHandle(string $query): bool
  {
    // La recherche sémantique est généralement déterminée par l'intent type
    // plutôt que par des patterns dans la requête
    return true; // Accepte toutes les requêtes pour ce type
  }

  /**
   * Creates le plan de recherche sémantique (1 étape simple)
   */
  public function createPlan(array $intent, string $query): array
  {
    if ($this->debug) {
      $this->logDebug("Creating semantic search plan for query: " . substr($query, 0, 100));
    }

    $steps = [];

    $embeddingTables = $this->ragManager->knownEmbeddingTable();

    // Step unique: Recherche sémantique
    $step1 = new TaskStep(
      'step_1',
      'semantic_search',
      $query,
      [
        'intent' => $intent,
        'search_type' => 'semantic',
        'data_source' => 'knowledge_base',
        'embedding_tables' => $embeddingTables,
        'similarity_threshold' => 0.7,
        'max_results' => 10,
        'depends_on' => [],
        'can_run_parallel' => false,
        'is_final' => true,
      ]
    );
    $steps[] = $step1;

    if ($this->debug) {
      $this->logDebug("Created semantic search plan with " . count($steps) . " step");
    }

    return $steps;
  }

  /**
   * Gets les planner metadata
   */
  public function getMetadata(): array
  {
    $embeddingTables = $this->ragManager->knownEmbeddingTable();

    return [
      'name' => 'Semantic Search Planner',
      'description' => 'Specialized planner for semantic search in knowledge base',
      'steps_count' => 1,
      'step_types' => ['semantic_search'],
      'data_sources' => ['knowledge_base'],
      'embedding_tables' => $embeddingTables,
      'supports_fallback' => false,
      'requires_external_data' => false
    ];
  }

  private function logDebug(string $message): void
  {
    if ($this->securityLogger) {
      $this->securityLogger->logSecurityEvent($message, 'info');
    }

    if ($this->debug) {
      error_log("[SubTaskPlannerSemanticSearch] $message");
    }
  }
}
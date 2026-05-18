<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Rag\MultiDBRAGManager;

/**
 * EntityExtractor Class
 *
 * Responsible for extracting entity IDs and types from execution results.
 * Separated from OrchestratorAgent to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Extract entity_id from various result structures
 * - Extract entity_type from various result structures
 * - Handle multiple extraction strategies (step results, intent, metadata)
 * - Provide fallback values when extraction fails
 */

class EntityExtractor
{
  private SecurityLogger $logger;
  private bool $debug;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;

    if ($this->debug) {
      $this->logger->logSecurityEvent("EntityExtractor initialized", 'info');
    }
  }

  /**
   * Extract entity ID from execution result
   *
   * PHASE 4: Enhanced to check semantic result structures
   *
   * @param array $executionResult Execution result
   * @param array $intent Original intent
   * @param mixed $plan Optional ExecutionPlan object
   * @return int|null Entity ID or null if not found
   */
  public function extractEntityId(array $executionResult, array $intent, $plan = null): ?int
  {
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "PHASE 4: extractEntityId - executionResult keys: " . json_encode(array_keys($executionResult)),
        'info'
      );
    }

    // Priority 0: Direct in executionResult
    if (isset($executionResult['entity_id']) && $executionResult['entity_id'] > 0) {
      if ($this->debug) {
        $this->logger->logSecurityEvent("✓ Found in executionResult['entity_id']: {$executionResult['entity_id']}", 'info');
      }
      return (int)$executionResult['entity_id'];
    }

    // Priority 1: In result wrapper
    if (isset($executionResult['result'])) {
      $result = $executionResult['result'];

      if (isset($result['entity_id']) && $result['entity_id'] > 0) {
        if ($this->debug) {
          $this->logger->logSecurityEvent("✓ Found in result['entity_id']: {$result['entity_id']}", 'info');
        }
        return (int)$result['entity_id'];
      }

      // PHASE 4: Check semantic query results metadata
      // SemanticQueryExecutor stores entity_id in metadata of results array
      if (isset($result['metadata']) && is_array($result['metadata'])) {
        if (isset($result['metadata']['entity_id']) && $result['metadata']['entity_id'] > 0) {
          if ($this->debug) {
            $this->logger->logSecurityEvent("✓ PHASE 4: Found in result['metadata']['entity_id']: {$result['metadata']['entity_id']}", 'info');
          }
          return (int)$result['metadata']['entity_id'];
        }
      }

      // PHASE 4: Check semantic results array (from SemanticQueryExecutor)
      // Results are stored as array of documents with metadata
      if (isset($result['results']) && is_array($result['results']) && !empty($result['results'])) {
        $firstResult = $result['results'][0];
        if (isset($firstResult['metadata']['entity_id']) && $firstResult['metadata']['entity_id'] > 0) {
          if ($this->debug) {
            $this->logger->logSecurityEvent("✓ PHASE 4: Found in result['results'][0]['metadata']['entity_id']: {$firstResult['metadata']['entity_id']}", 'info');
          }
          return (int)$firstResult['metadata']['entity_id'];
        }
      }

      if (isset($result['_step_entity_metadata']['entity_id']) && $result['_step_entity_metadata']['entity_id'] > 0) {
        if ($this->debug) {
          $this->logger->logSecurityEvent("[INFO SUCCESS] Found in result['_step_entity_metadata']['entity_id']: {$result['_step_entity_metadata']['entity_id']}", 'info');
        }
        return (int)$result['_step_entity_metadata']['entity_id'];
      }

      if (isset($result['_entity_metadata']['entity_id']) && $result['_entity_metadata']['entity_id'] > 0) {
        if ($this->debug) {
          $this->logger->logSecurityEvent("✓ Found in result['_entity_metadata']['entity_id']: {$result['_entity_metadata']['entity_id']}", 'info');
        }
        return (int)$result['_entity_metadata']['entity_id'];
      }

      if (isset($result['type']) && $result['type'] === 'analytics_response' && isset($result['entity_id']) && $result['entity_id'] > 0) {
        if ($this->debug) {
          $this->logger->logSecurityEvent("[INFO SUCCESS] Found in analytics_response result['entity_id']: {$result['entity_id']}", 'info');
        }
        return (int)$result['entity_id'];
      }
    }

    // Priority 2: Check plan step results (if plan provided)
    if ($plan !== null) {
      $entityFromPlan = $this->extractFromPlan($plan);
      if ($entityFromPlan !== null) {
        return $entityFromPlan;
      }
    }

    // Priority 3: Check step results in executionResult
    $entityFromSteps = $this->extractFromStepResults($executionResult);
    if ($entityFromSteps !== null) {
      return $entityFromSteps;
    }

    // Priority 4: Check intent metadata
    $entityFromIntent = $this->extractFromIntent($intent);
    if ($entityFromIntent !== null) {
      return $entityFromIntent;
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent("No entity_id found - using default (general query)", 'info');
    }

    return null;
  }

  /**
   * Extract entity type from execution result
   *
   * PHASE 4: Enhanced to check semantic result structures
   *
   * @param array $executionResult Execution result
   * @param array $intent Original intent
   * @param mixed $plan Optional ExecutionPlan object
   * @return string|null Entity type or null if not found
   */
  public function extractEntityType(array $executionResult, array $intent, $plan = null): ?string
  {
    // Priority 1: Direct entity_type in result
    if (isset($executionResult['entity_type'])) {
      if ($this->debug) {
        $this->logger->logSecurityEvent("✓ PHASE 4: Found in executionResult['entity_type']: {$executionResult['entity_type']}", 'info');
      }
      return $executionResult['entity_type'];
    }

    // Priority 2: entity_type in result wrapper
    if (isset($executionResult['result']) && is_array($executionResult['result'])) {
      if (isset($executionResult['result']['entity_type'])) {
        if ($this->debug) {
          $this->logger->logSecurityEvent("✓ PHASE 4: Found in result['entity_type']: {$executionResult['result']['entity_type']}", 'info');
        }
        return $executionResult['result']['entity_type'];
      }

      // PHASE 4: Check semantic query results metadata
      if (isset($executionResult['result']['metadata']) && is_array($executionResult['result']['metadata'])) {
        if (isset($executionResult['result']['metadata']['entity_type'])) {
          if ($this->debug) {
            $this->logger->logSecurityEvent("✓ PHASE 4: Found in result['metadata']['entity_type']: {$executionResult['result']['metadata']['entity_type']}", 'info');
          }
          return $executionResult['result']['metadata']['entity_type'];
        }
      }

      // PHASE 4: Check semantic results array (from SemanticQueryExecutor)
      if (isset($executionResult['result']['results']) && is_array($executionResult['result']['results']) && !empty($executionResult['result']['results'])) {
        $firstResult = $executionResult['result']['results'][0];
        if (isset($firstResult['metadata']['entity_type'])) {
          if ($this->debug) {
            $this->logger->logSecurityEvent("✓ PHASE 4: Found in result['results'][0]['metadata']['entity_type']: {$firstResult['metadata']['entity_type']}", 'info');
          }
          return $firstResult['metadata']['entity_type'];
        }
        // Also check 'type' field as alternative
        if (isset($firstResult['metadata']['type'])) {
          if ($this->debug) {
            $this->logger->logSecurityEvent("✓ PHASE 4: Found in result['results'][0]['metadata']['type']: {$firstResult['metadata']['type']}", 'info');
          }
          return $firstResult['metadata']['type'];
        }
      }

      if (isset($executionResult['result']['_step_entity_metadata']['entity_type'])) {
        if ($this->debug) {
          $this->logger->logSecurityEvent("[INFO SUCCESS] Found in result['_step_entity_metadata']['entity_type']: {$executionResult['result']['_step_entity_metadata']['entity_type']}", 'info');
        }
        return $executionResult['result']['_step_entity_metadata']['entity_type'];
      }

      if (isset($executionResult['result']['_entity_metadata']['entity_type'])) {
        if ($this->debug) {
          $this->logger->logSecurityEvent("✓ Found in result['_entity_metadata']['entity_type']: {$executionResult['result']['_entity_metadata']['entity_type']}", 'info');
        }
        return $executionResult['result']['_entity_metadata']['entity_type'];
      }

      if (isset($executionResult['result']['type']) && $executionResult['result']['type'] === 'analytics_response' && isset($executionResult['result']['entity_type'])) {
        if ($this->debug) {
          $this->logger->logSecurityEvent("[INFO SUCCESS] Found in analytics_response result['entity_type']: {$executionResult['result']['entity_type']}", 'info');
        }
        return $executionResult['result']['entity_type'];
      }
    }

    // Priority 3: Check plan step results (if plan provided)
    if ($plan !== null && is_object($plan) && method_exists($plan, 'getSteps')) {
      foreach ($plan->getSteps() as $step) {
        $stepResult = $plan->getStepResult($step->getId());

        if ($stepResult && isset($stepResult['_step_entity_metadata']['entity_type'])) {
          return $stepResult['_step_entity_metadata']['entity_type'];
        }

        if ($stepResult && isset($stepResult['_entity_metadata']['entity_type'])) {
          return $stepResult['_entity_metadata']['entity_type'];
        }

        if ($stepResult && isset($stepResult['entity_type'])) {
          return $stepResult['entity_type'];
        }
      }
    }

    // Priority 4: Infer from intent metadata
    if (!empty($intent['metadata']['entities'])) {
      $firstEntity = $intent['metadata']['entities'][0];
      if (isset($firstEntity['type'])) {
        return $firstEntity['type'];
      }
    }

    return null;
  }

  /**
   * Extract entity from step results
   *
   * @param array $executionResult Execution result
   * @return int|null Entity ID or null
   */
  private function extractFromStepResults(array $executionResult): ?int
  {
    if (!isset($executionResult['plan']) || !is_array($executionResult['plan'])) {
      return null;
    }

    if (!isset($executionResult['plan']['steps']) || !is_array($executionResult['plan']['steps'])) {
      return null;
    }

    foreach ($executionResult['plan']['steps'] as $step) {
      if (isset($step['result']) && is_array($step['result'])) {
        if (isset($step['result']['entity_id'])) {
          if ($this->debug) {
            $this->logger->logSecurityEvent("Found entity_id in step result: {$step['result']['entity_id']}", 'info');
          }
          return (int)$step['result']['entity_id'];
        }
      }
    }

    return null;
  }

  /**
   * Extract entity from ExecutionPlan step results
   *
   * @param mixed $plan ExecutionPlan object
   * @return int|null Entity ID or null
   */
  private function extractFromPlan($plan): ?int
  {
    if (!is_object($plan) || !method_exists($plan, 'getSteps')) {
      return null;
    }

    foreach ($plan->getSteps() as $step) {
      $stepResult = $plan->getStepResult($step->getId());

      if (!$stepResult) {
        continue;
      }

      // Check 3 possible locations
      if (isset($stepResult['entity_id']) && $stepResult['entity_id'] > 0) {
        if ($this->debug) {
          $this->logger->logSecurityEvent("✓ Found in step[{$step->getId()}]['entity_id']: {$stepResult['entity_id']}", 'info');
        }
        return (int)$stepResult['entity_id'];
      }

      if (isset($stepResult['_step_entity_metadata']['entity_id']) && $stepResult['_step_entity_metadata']['entity_id'] > 0) {
        if ($this->debug) {
          $this->logger->logSecurityEvent("✓ Found in step[{$step->getId()}]['_step_entity_metadata']['entity_id']: {$stepResult['_step_entity_metadata']['entity_id']}", 'info');
        }
        return (int)$stepResult['_step_entity_metadata']['entity_id'];
      }

      if (isset($stepResult['_entity_metadata']['entity_id']) && $stepResult['_entity_metadata']['entity_id'] > 0) {
        if ($this->debug) {
          $this->logger->logSecurityEvent("✓ Found in step[{$step->getId()}]['_entity_metadata']['entity_id']: {$stepResult['_entity_metadata']['entity_id']}", 'info');
        }
        return (int)$stepResult['_entity_metadata']['entity_id'];
      }
    }

    return null;
  }

  /**
   * Extract entity from intent metadata
   *
   * @param array $intent Intent
   * @return int|null Entity ID or null
   */
  private function extractFromIntent(array $intent): ?int
  {
    if (!isset($intent['metadata']) || !is_array($intent['metadata'])) {
      return null;
    }

    if (!isset($intent['metadata']['entities']) || !is_array($intent['metadata']['entities'])) {
      return null;
    }

    if (empty($intent['metadata']['entities'])) {
      return null;
    }

    $firstEntity = $intent['metadata']['entities'][0];
    if (isset($firstEntity['id']) && $firstEntity['id'] > 0) {
      if ($this->debug) {
        $this->logger->logSecurityEvent("✓ Found in intent metadata entities: {$firstEntity['id']}", 'info');
      }
      return (int)$firstEntity['id'];
    }

    return null;
  }


  /**
   * Detect entity from embeddings using semantic search
   *
   * @param string $query Query to search for
   * @param string $entityType Type of entity to search for (product, category, etc.)
   * @return array|null Entity data with entity_id, entity_type, confidence, or null if not found
   */
  public function detectEntityFromEmbeddings(string $query, string $entityType = 'product'): ?array
  {
    try {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Detecting entity from embeddings: Query='{$query}', Type={$entityType}",
          'info'
        );
      }

      // Get language ID
      $languageId = Registry::get('Language')->getId() ?? 1;

      // Get embedding tables dynamically
      $embeddingTables = DoctrineOrm::getEmbeddingTables();
      
      // Filter tables based on entity type if specified
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');

      if (!empty($entityType) && $entityType !== 'unknown') {
        // Pluralize entity type (simple approach - add 's' if not already plural)
        $pluralEntity = $entityType;
        if (!str_ends_with($entityType, 's')) {
          $pluralEntity = $entityType . 's';
        }
        
        $targetTable = $prefix . $pluralEntity . '_embedding';
        
        // Only search in the specific table if it exists
        if (in_array($targetTable, $embeddingTables, true)) {
          $embeddingTables = [$targetTable];
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Filtered to specific entity table: {$targetTable}",
              'info'
            );
          }
        } else {
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Entity table {$targetTable} not found, searching all embedding tables",
              'info'
            );
          }
        }
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Searching in " . count($embeddingTables) . " embedding table(s)",
          'info'
        );
      }

      // Create MultiDBRAGManager for embedding search
      $ragManager = new MultiDBRAGManager(null, $embeddingTables);

      // Search with similarity threshold 0.7
      $minScore = 0.7;
      $limit = 3;

      $results = $ragManager->searchDocuments(
        $query,
        $limit,
        $minScore,
        $languageId
      );

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Embedding search returned " . count($results['documents'] ?? []) . " results",
          'info'
        );
      }

      // Extract best match
      if (!empty($results['documents'])) {
        $bestMatch = $results['documents'][0];
        
        // Extract entity_id and entity_type from metadata
        $entityId = $bestMatch->metadata['entity_id'] ?? null;
        $detectedType = $bestMatch->metadata['entity_type'] ?? $entityType;
        $score = $bestMatch->metadata['score'] ?? 0;

        if ($entityId !== null) {
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Entity found via embeddings: ID={$entityId}, Type={$detectedType}, Score={$score}",
              'info'
            );
          }

          return [
            'entity_id' => $entityId,
            'entity_type' => $detectedType,
            'confidence' => round($score, 2),
            'method' => 'embedding',
            'content' => $bestMatch->content ?? '',
          ];
        }
      }

      // No results from embedding search
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "No entity found via embeddings (score < {$minScore}), returning null",
          'info'
        );
      }

      return null;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error detecting entity from embeddings: " . $e->getMessage(),
        'error'
      );
      
      // Return null on error (caller will handle fallback)
      return null;
    }
  }

  /**
   * Retrieves all unique entity types from embedding tables.
   *
   * @return array List of unique entity types
   * @throws \Exception If there is an error connecting to the database or executing the query
   */
  public function getEntityTypesFromEmbeddings(): array
  {
    $tables = DoctrineOrm::getEmbeddingTables();
    $connection = DoctrineOrm::getEntityManager()->getConnection();
    $entityTypes = [];

    foreach ($tables as $table) {
      try {
        $sql = "SELECT DISTINCT type FROM {$table}";
        $result = $connection->executeQuery($sql);
        $types = $result->fetchFirstColumn();
        $entityTypes = array_merge($entityTypes, $types);
      } catch (\Exception $e) {
        $logger = new SecurityLogger();
        if ($logger) {
          $logger->logSecurityEvent(
            "Error fetching types from table {$table}: " . $e->getMessage(),
            'error'
          );
        }
      }
    }

    $entityTypes = array_unique(array_filter($entityTypes));
    return $entityTypes;
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;


use ClicShopping\AI\DomainsAI\Shared\Entity\EntityRegistry;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\CLICSHOPPING;

/**
 * EntityTracker Class
 *
 * Responsible for tracking entities mentioned in conversations.
 * Separated from ConversationMemory to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Track lastEntityId and lastEntityType
 * - Maintain entity history (stack of N last entities)
 * - Resolve entities by position ("the previous product")
 * - Clear entity tracking when needed
 */

class EntityTracker
{
  private SecurityLogger $logger;
  private bool $debug;
  private string $userId;
  private ?int $languageId;
  private ?int $lastEntityId = null;
  private ?string $lastEntityType = null;
  private ?string $lastEntityName = null;
  private array $entityHistory = []; // Stack of recent entities
  private int $maxHistorySize = 10; // Max entities to keep in history

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   * @param string $userId Owner of the tracked context — scopes the cross-request database fallback
   * @param int|null $languageId Language of the tracked context, null when unknown
   */
  public function __construct(bool $debug = false, string $userId = 'system', ?int $languageId = null)
  {
    $this->debug = $debug;
    $this->userId = $userId;
    $this->languageId = $languageId;
    $this->logger = new SecurityLogger();

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "EntityTracker initialized",
        'info'
      );
    }
  }

  /**
   * Set the last entity
   *
   * The type is coerced to the canonical form first: producers disagree (the analytics path derives
   * it from the table name, a domain subsystem may author its own singular form in the embedding
   * metadata it owns), and the no-clobber guard below compares types.
   *
   * @param int $entityId Entity ID
   * @param string $entityType Entity type, any producer's form
   * @param string|null $entityName Entity name (optional, for context enrichment)
   * @return void
   */
  public function setLastEntity(int $entityId, string $entityType, ?string $entityName = null): void
  {
    $entityType = EntityRegistry::getInstance()->normalizeEntityType($entityType);

    // No-clobber guard: a nameless re-set of the SAME entity must not erase a name we already
    // hold. Several call sites (StoreMemoryStage, MemoryManager::storeOrchestrationResult) re-set
    // the entity with id+type only AFTER the executor captured the name; without this guard the
    // name is lost and contextual follow-ups ("its sku") can no longer be resolved.
    if (($entityName === null || $entityName === '')
        && $entityId === $this->lastEntityId
        && $entityType === $this->lastEntityType
        && ($this->lastEntityName !== null && $this->lastEntityName !== '')) {
      $entityName = $this->lastEntityName;
    }

    $this->lastEntityId = $entityId;
    $this->lastEntityType = $entityType;
    $this->lastEntityName = $entityName;

    // Add to history
    $this->addToHistory($entityId, $entityType, $entityName);

    if ($this->debug) {
      $nameInfo = $entityName ? " (name: {$entityName})" : "";
      $this->logger->logSecurityEvent(
        "Last entity set: {$entityType} #{$entityId}{$nameInfo}",
        'info'
      );
    }
  }

  /**
   * Get the last entity
   *
   * First checks in-memory (fast path), then queries database if needed — the database read is
   * scoped to the owning user and language, so no other conversation's entity can be inherited.
   *
   * @return array|null Array with 'id', 'type', and 'name' keys, or null if no entity
   */
  public function getLastEntity(): ?array
  {
    // -1 is sentinel value meaning "cleared, don't use database fallback"
    if ($this->lastEntityId === -1) {
      return null;
    }
    
    // Fast path: Check in-memory first
    if ($this->lastEntityId !== null && $this->lastEntityId > 0) {
      return [
        'id' => $this->lastEntityId,
        'type' => $this->lastEntityType,
        'name' => $this->lastEntityName,
      ];
    }

    // This enables contextual queries to work across HTTP requests

    // Fail-closed: an unscoped read would hand this admin the entity of whoever wrote last.
    if ($this->userId === '') {
      return null;
    }

    try {
      // Get table prefix from configuration
      $prefix = CLICSHOPPING::getConfig('db_table_prefix', 'DB');
      
      // This table is used by LongTermMemoryManager to store interactions with embeddings
      $tableName = $prefix . 'rag_conversation_memory_embedding';
      
      // Scoped to this user and language, exactly like ConversationTurnReader: the two sources feed
      // the same reference resolution, and an unscoped row here leaks another admin's entity.
      $params = ['user_id' => $this->userId];
      $languageFilter = '';

      if ($this->languageId !== null) {
        $languageFilter = 'AND language_id = :language_id';
        $params['language_id'] = $this->languageId;
      }

      // Query the most recent interaction with a valid entity
      // Order by date_modified (most recent first)
      $sql = "
        SELECT entity_id, metadata
        FROM {$tableName}
        WHERE user_id = :user_id
        {$languageFilter}
        AND entity_id IS NOT NULL
        AND entity_id != 0
        ORDER BY date_modified DESC
        LIMIT 1
      ";

      $result = DoctrineOrm::selectOne($sql, $params);

      if ($result) {
        $entityId = (int)$result['entity_id'];
        $metadataJson = $result['metadata'];
        
        // Extract entity_type and entity_name from metadata JSON
        $entityType = 'unknown';
        $entityName = null;
        if (!empty($metadataJson)) {
          $metadata = json_decode($metadataJson, true);
          if (isset($metadata['entity_type'])) {
            // Rows written before normalization carry the producer's own form; heal them on read
            $entityType = EntityRegistry::getInstance()->normalizeEntityType((string)$metadata['entity_type']);
          }
          // Extract entity_name from metadata
          if (isset($metadata['entity_name'])) {
            $entityName = $metadata['entity_name'];
          }
        }
        
        // Cache in memory for subsequent calls in this request
        $this->lastEntityId = $entityId;
        $this->lastEntityType = $entityType;
        $this->lastEntityName = $entityName;

        if ($this->debug) {
          $nameInfo = $entityName ? " (name: {$entityName})" : "";
          $this->logger->logSecurityEvent(
            "Last entity retrieved from database: {$entityType} #{$entityId}{$nameInfo}",
            'info'
          );
        }
        
        return [
          'id' => $entityId,
          'type' => $entityType,
          'name' => $entityName,
        ];
      }
    } catch (\Exception $e) {
      // Log error but don't fail - graceful degradation
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Error retrieving last entity from database: " . $e->getMessage(),
          'warning'
        );
      }
    }

    return null;
  }

  /**
   * Get the last tracked entity for contextual reference resolution.
   * 
   * This method provides the last entity in a format suitable for ReferenceResolver,
   * eliminating the need for the resolver to determine "last entity" itself.
   *
   * @return array Array with 'id', 'type', and 'reference' keys
   */
  public function getLastTrackedEntity(): array
  {
    $lastEntity = $this->getLastEntity();
    
    if ($lastEntity === null) {
      return [
        'id' => null,
        'type' => null,
        'reference' => null,
      ];
    }
    
    // Format reference as "type id" (e.g., "product 123")
    $reference = $lastEntity['type'] . ' ' . $lastEntity['id'];
    
    return [
      'id' => $lastEntity['id'],
      'type' => $lastEntity['type'],
      'reference' => $reference,
    ];
  }
  /**
   * Add entity to history (FIFO)
   *
   * @param int $entityId Entity ID
   * @param string $entityType Entity type
   * @param string|null $entityName Entity name (optional)
   * @return void
   */
  private function addToHistory(int $entityId, string $entityType, ?string $entityName = null): void
  {
    // Add to beginning of array (most recent first)
    array_unshift($this->entityHistory, [
      'id' => $entityId,
      'type' => $entityType,
      'name' => $entityName,
      'timestamp' => time(),
    ]);

    // Trim to max size (FIFO)
    if (count($this->entityHistory) > $this->maxHistorySize) {
      array_pop($this->entityHistory);
    }

    if ($this->debug) {
      $nameInfo = $entityName ? " (name: {$entityName})" : "";
      $this->logger->logSecurityEvent(
        "Entity added to history: {$entityType} #{$entityId}{$nameInfo} (history size: " . count($this->entityHistory) . ")",
        'info'
      );
    }
  }


  /**
   * Clear the last entity
   *
   * @return void
   */
  public function clearLastEntity(): void
  {
    // Set to explicit null (not just unset)
    // This prevents getLastEntity() from falling back to database
    $this->lastEntityId = -1; // Use -1 as sentinel value for "explicitly cleared"
    $this->lastEntityType = null;
    $this->lastEntityName = null;

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Last entity cleared (context switch)",
        'info'
      );
    }
  }
}

<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;


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
  private ?int $lastEntityId = null;
  private ?string $lastEntityType = null;
  private array $entityHistory = []; // Stack of recent entities
  private int $maxHistorySize = 10; // Max entities to keep in history

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
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
   * @param int $entityId Entity ID
   * @param string $entityType Entity type (product, category, order, etc.)
   * @return void
   */
  public function setLastEntity(int $entityId, string $entityType): void
  {
    $this->lastEntityId = $entityId;
    $this->lastEntityType = $entityType;

    // Add to history
    $this->addToHistory($entityId, $entityType);

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Last entity set: {$entityType} #{$entityId}",
        'info'
      );
    }
  }

  /**
   * Get the last entity
   * 
   * First checks in-memory (fast path), then queries database if needed.
   *
   * @return array|null Array with 'id' and 'type', or null if no entity
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
      ];
    }

    // This enables contextual queries to work across HTTP requests
    
    try {
      // Get table prefix from configuration
      $prefix = CLICSHOPPING::getConfig('db_table_prefix', 'DB');
      
      // This table is used by LongTermMemoryManager to store interactions with embeddings
      $tableName = $prefix . 'rag_conversation_memory_embedding';
      
      // Query the most recent interaction with a valid entity
      // Order by date_modified (most recent first)
      $sql = "
        SELECT entity_id, metadata
        FROM {$tableName}
        WHERE entity_id IS NOT NULL
        AND entity_id != 0
        ORDER BY date_modified DESC
        LIMIT 1
      ";
      
      $result = DoctrineOrm::selectOne($sql);
      
      if ($result) {
        $entityId = (int)$result['entity_id'];
        $metadataJson = $result['metadata'];
        
        // Extract entity_type from metadata JSON
        $entityType = 'unknown';
        if (!empty($metadataJson)) {
          $metadata = json_decode($metadataJson, true);
          if (isset($metadata['entity_type'])) {
            $entityType = $metadata['entity_type'];
          }
        }
        
        // Cache in memory for subsequent calls in this request
        $this->lastEntityId = $entityId;
        $this->lastEntityType = $entityType;
        
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Last entity retrieved from database: {$entityType} #{$entityId}",
            'info'
          );
        }
        
        return [
          'id' => $entityId,
          'type' => $entityType,
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
   * This method provides the last entity in a format suitable for ContextResolver,
   * eliminating the need for ContextResolver to determine "last entity" itself.
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
   * @return void
   */
  private function addToHistory(int $entityId, string $entityType): void
  {
    // Add to beginning of array (most recent first)
    array_unshift($this->entityHistory, [
      'id' => $entityId,
      'type' => $entityType,
      'timestamp' => time(),
    ]);

    // Trim to max size (FIFO)
    if (count($this->entityHistory) > $this->maxHistorySize) {
      array_pop($this->entityHistory);
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Entity added to history: {$entityType} #{$entityId} (history size: " . count($this->entityHistory) . ")",
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

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Last entity cleared (context switch)",
        'info'
      );
    }
  }
}

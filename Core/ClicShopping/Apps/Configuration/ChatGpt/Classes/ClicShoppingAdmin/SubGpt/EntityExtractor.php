<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

/**
 * EntityExtractor
 *
 * Extracts entity information from AI responses.
 * Extracted from chatGpt.php AJAX handler as part of code refactoring (Task 12).
 *
 * Responsibilities:
 * - Extract entity_id from response
 * - Extract entity_type from response
 * - Provide fallback values
 * - Extract metadata
 */
class EntityExtractor
{
  /**
   * Extract all entity metadata from AI response
   *
   * @param array $aiResponse AI response from orchestrator
   * @param int $languageId Language ID
   * @return array Entity metadata with entity_id, entity_type, language_id keys
   */
  public static function extractMetadata(array $aiResponse, int $languageId): array
  {
    $entityId = self::extractEntityId($aiResponse);
    $entityType = self::extractEntityType($aiResponse);

    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log('[INFO]   EXTRACTED METADATA:');
      error_log('   Entity ID: ' . $entityId);
      error_log('   Entity Type: ' . $entityType);
      error_log('   Language ID: ' . $languageId);
    }

    return [
      'entity_id' => $entityId,
      'entity_type' => $entityType,
      'language_id' => $languageId
    ];
  }
  
  /**
   * Extract entity ID from AI response
   *
   * @param array $aiResponse AI response from orchestrator
   * @return int Entity ID (0 if not found)
   */
  public static function extractEntityId(array $aiResponse): int
  {
    $entityId = null;

    // Priority 1: Check root level
    if (isset($aiResponse['entity_id'])) {
      $entityId = $aiResponse['entity_id'];
    }

    // Priority 2: Check data level
    if ($entityId === null && isset($aiResponse['data']['entity_id'])) {
      $entityId = $aiResponse['data']['entity_id'];
    }

    // Priority 3: Check results array
    if ($entityId === null && !empty($aiResponse['data']['results']) && is_array($aiResponse['data']['results'])) {
      foreach ($aiResponse['data']['results'] as $result) {
        if (isset($result['id'])) {
          $entityId = $result['id'];
          break;
        }
      }
    }

    // Default to 0 if not found
    if ($entityId === null || $entityId === '' || $entityId === 'ABSENT') {
      $entityId = 0;
    }

    return (int)$entityId;
  }
  
  /**
   * Extract entity type from AI response
   *
   * @param array $aiResponse AI response from orchestrator
   * @return string Entity type ('unknown' if not found)
   */
  public static function extractEntityType(array $aiResponse): string
  {
    $entityType = null;

    // Priority 1: Check root level
    if (isset($aiResponse['entity_type'])) {
      $entityType = $aiResponse['entity_type'];
    }

    // Priority 2: Check data level
    if ($entityType === null && isset($aiResponse['data']['entity_type'])) {
      $entityType = $aiResponse['data']['entity_type'];
    }

    // Default to 'unknown' if not found
    if ($entityType === null || $entityType === '') {
      $entityType = 'unknown';
    }

    return $entityType;
  }
}

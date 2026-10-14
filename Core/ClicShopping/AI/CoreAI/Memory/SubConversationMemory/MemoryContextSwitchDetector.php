<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

use ClicShopping\AI\Security\SecurityLogger;

/**
 * MemoryContextSwitchDetector Class
 *
 * Decides whether the conversation has switched query type (analytics ↔
 * semantic ↔ web_search) between two turns. Extracted verbatim from
 * ConversationMemory (2026-06-28, memory decomposition step 3) to drain the
 * detection logic out of the façade.
 *
 * Stateless: the last query type lives in ConversationMemory and is passed in,
 * so this collaborator only reads its inputs and logs. Hybrid queries are
 * excluded from switch detection as they can contain both types.
 *
 * NB: distinct from DomainsAI\Shared\Helper\Detection\ContextSwitchDetector
 * (a different, domain-level concern with a different signature).
 *
 * Responsibilities:
 * - Compare the current query type with the previous one and report a switch
 */
class MemoryContextSwitchDetector
{
  private SecurityLogger $securityLogger;
  private bool $debug;

  /**
   * Constructor
   *
   * @param SecurityLogger $securityLogger Structured logger for diagnostics
   * @param bool $debug Enable structured debug logging
   */
  public function __construct(SecurityLogger $securityLogger, bool $debug = false)
  {
    $this->securityLogger = $securityLogger;
    $this->debug = $debug;
  }

  /**
   * Compares the current query type with the last stored query type.
   * Hybrid queries are excluded from context switch detection as they can contain both types.
   *
   * @param string $currentQueryType Current query type (analytics, semantic, hybrid, web_search)
   * @param string|null $lastQueryType Previously stored query type (null = first query)
   * @param string $userId User identifier (for diagnostics)
   * @param int $languageId Language ID (for diagnostics)
   * @return bool True if context switch detected, false otherwise
   */
  public function detectContextSwitch(string $currentQueryType, ?string $lastQueryType, string $userId, int $languageId): bool
  {
    // Captured for the catch-block diagnostic: $lastQueryType gets narrowed to
    // non-null by the early return below, so keep the raw input separately.
    $initialLastQueryType = $lastQueryType;

    try {
      // Check if we have a last query type stored
      if ($lastQueryType === null) {
        // No previous query type, so no switch

        if ($this->debug) {
          $this->securityLogger->logStructured(
            'info',
            'ConversationMemory',
            'first_query_in_conversation',
            [
              'current_query_type' => $currentQueryType,
              'user_id' => $userId,
              'language_id' => $languageId,
              'note' => 'No previous query type - this is the first query in conversation'
            ]
          );
        }
        return false;
      }

      // Context switch if types differ (excluding hybrid)
      // Hybrid queries don't trigger context switches because they can contain both types
      $isSwitch = $lastQueryType !== $currentQueryType &&
                  $lastQueryType !== 'hybrid' &&
                  $currentQueryType !== 'hybrid';


      if ($isSwitch) {
        if ($this->debug) {
          $this->securityLogger->logStructured(
            'info',
            'ConversationMemory',
            'context_switch_detected',
            [
              'previous_query_type' => $lastQueryType,
              'current_query_type' => $currentQueryType,
              'switch_direction' => "{$lastQueryType} → {$currentQueryType}",
              'user_id' => $userId,
              'language_id' => $languageId,
              'entity_will_be_cleared' => true,
              'timestamp' => date('Y-m-d H:i:s'),
              'note' => 'Context switch detected - entity tracking will be cleared'
            ]
          );
        }
      } else {

        if ($this->debug) {
          $this->securityLogger->logStructured(
            'info',
            'ConversationMemory',
            'context_continuation',
            [
              'query_type' => $currentQueryType,
              'previous_query_type' => $lastQueryType,
              'user_id' => $userId,
              'language_id' => $languageId,
              'note' => 'No context switch - continuing with same query type'
            ]
          );
        }
      }

      return $isSwitch;

    } catch (\Exception $e) {

      if ($this->debug) {
        $this->securityLogger->logStructured(
          'error',
          'ConversationMemory',
          'context_switch_detection_error',
          [
            'current_query_type' => $currentQueryType,
            'last_query_type' => $initialLastQueryType ?? 'null',
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'user_id' => $userId,
            'note' => 'Error detecting context switch - defaulting to no switch'
          ]
        );
      }
      return false;
    }
  }
}

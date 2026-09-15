<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

/**
 * The instant a conversation was started over.
 *
 * Both conversational readers scope on user and language only, so they reach back across a
 * "new context": clearing an in-memory object cannot hide rows that outlive the request. This
 * carries one durable instant, and the readers ignore anything older.
 *
 * Long-term memory is NOT bounded — it is retrieved by semantic similarity, not by recency.
 */
class ConversationBoundary
{
  private const SESSION_KEY = 'ai_conversation_boundary';

  /**
   * Start a new conversation for this user and language.
   */
  public static function reset(string $userId, ?int $languageId): void
  {
    // Same clock as the writers of date_modified: PHP runs in UTC and the SQL session is pinned to it.
    $_SESSION[self::SESSION_KEY][self::scope($userId, $languageId)] = date('Y-m-d H:i:s');
  }

  /**
   * @return string|null SQL datetime, or null when the conversation was never reset
   */
  public static function get(string $userId, ?int $languageId): ?string
  {
    $at = $_SESSION[self::SESSION_KEY][self::scope($userId, $languageId)] ?? null;

    return \is_string($at) && $at !== '' ? $at : null;
  }

  /**
   * Add the boundary clause to a query over a table carrying date_modified.
   *
   * @param array<string, mixed> $params Bound parameters, extended in place
   * @return string The clause to splice in, empty when there is no boundary
   */
  public static function clause(string $userId, ?int $languageId, array &$params): string
  {
    $at = self::get($userId, $languageId);

    if ($at === null) {
      return '';
    }

    $params['conversation_boundary'] = $at;

    return 'AND date_modified > :conversation_boundary';
  }

  private static function scope(string $userId, ?int $languageId): string
  {
    return $userId . '|' . ($languageId ?? 0);
  }
}

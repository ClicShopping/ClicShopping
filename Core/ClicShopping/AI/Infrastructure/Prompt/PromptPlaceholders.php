<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Prompt;

/**
 * PromptPlaceholders Class
 * Resolves the placeholders a prompt definition may carry before it reaches the LLM.
 * Prompts are install-agnostic: they never spell the DB table prefix, they declare it.
 */
class PromptPlaceholders
{
  public const TABLE_PREFIX = '{{table_prefix}}';
  public const LANGUAGE_ID = '{{language_id}}';

  /**
   * Resolve every placeholder of an assembled prompt
   *
   * @param string $message Assembled prompt, possibly carrying placeholders
   * @param string $tablePrefix Install DB table prefix (db_table_prefix)
   * @param int $languageId Language ID to inject
   * @return string Prompt ready to be sent to the LLM
   */
  public static function resolve(string $message, string $tablePrefix, int $languageId): string
  {
    return str_replace(
      [self::TABLE_PREFIX, self::LANGUAGE_ID],
      [$tablePrefix, (string)$languageId],
      $message
    );
  }

  /**
   * Check whether a prompt still carries an unresolved placeholder
   *
   * A leak means some reader bypassed the resolution chokepoint and the LLM would receive
   * `{{table_prefix}}products` verbatim, producing SQL against a table that does not exist.
   *
   * @param string $message Prompt about to be sent
   * @return bool True when at least one placeholder survived
   */
  public static function hasUnresolved(string $message): bool
  {
    return str_contains($message, self::TABLE_PREFIX) || str_contains($message, self::LANGUAGE_ID);
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Prompt;

use ClicShopping\AI\RegistryAI\PromptPlaceholderRegistry;

/**
 * PromptPlaceholders Class
 * Resolves the placeholders a prompt definition may carry before it reaches the LLM.
 * Prompts are install-agnostic: they never spell the DB table prefix, they declare it.
 *
 * Two families, one chokepoint: the STATIC tokens below, known to Core, and the
 * DYNAMIC ones registered by a domain App (see {@see PromptPlaceholderRegistry}).
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
    $message = str_replace(
      [self::TABLE_PREFIX, self::LANGUAGE_ID],
      [$tablePrefix, (string)$languageId],
      $message
    );

    return PromptPlaceholderRegistry::getInstance()->resolve($message, $languageId);
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
    if (str_contains($message, self::TABLE_PREFIX) || str_contains($message, self::LANGUAGE_ID)) {
      return true;
    }

    foreach (PromptPlaceholderRegistry::getInstance()->getTokens() as $token) {
      if (str_contains($message, $token)) {
        return true;
      }
    }

    return false;
  }
}

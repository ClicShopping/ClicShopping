<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\InterfacesAI;

/**
 * PromptPlaceholderProviderInterface
 *
 * Contract for a DYNAMIC prompt placeholder: a token a prompt declares and whose
 * value can only be read at runtime (from the database, from configuration), as
 * opposed to the static ones resolved by {@see \ClicShopping\AI\Infrastructure\Prompt\PromptPlaceholders}.
 *
 * Core never knows what a domain token means. A domain App registers its providers
 * via `Apps/AI/{Domain}/Classes/ClicShoppingAdmin/Prompt/Registration/PromptPlaceholderRegistration.php`,
 * exactly as WebSearch engines do.
 *
 * @package ClicShopping\AI\InterfacesAI
 */
interface PromptPlaceholderProviderInterface
{
  /**
   * The token this provider answers for, braces included (e.g. `{{order_status_map}}`).
   *
   * @return string Token as it appears verbatim in a prompt definition
   */
  public function getToken(): string;

  /**
   * Render the replacement text for the token.
   *
   * Called at most once per prompt build, and ONLY when the token is actually
   * present in the assembled message — an unused provider must cost nothing.
   *
   * @param int $languageId Language the prompt is being built for
   * @return string Replacement text; an empty string removes the token
   */
  public function render(int $languageId): string;

  /**
   * Tables whose CONTENT this provider renders into the prompt.
   *
   * A cached answer built before one of them was last written read a meaning that no
   * longer holds — and it is invisible to the usual freshness rule, because the generated
   * SQL never references these tables (it filters an id, it does not join the catalogue).
   * {@see \ClicShopping\AI\Infrastructure\Cache\SubQueryCache\CacheFreshnessValidator} adds
   * them to what it checks, so EVERY writer is covered — admin, cron, import, MCP, direct
   * SQL — without any write path having to announce itself.
   *
   * Logical names, WITHOUT the install prefix: the prefix is one install's setting and the
   * caller applies it.
   *
   * @return array<int, string> Unprefixed table names; empty when the value comes from elsewhere
   */
  public function getSourceTables(): array;
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

/**
 * LlmCallCounter
 *
 * Process-wide counter of real LLM round-trips, reset and read per request. It exists
 * because no single log records the exact number of LLM calls a request makes:
 * rag_statistics writes one row per interaction, and ~15 call sites invoke
 * $chat->generateText() directly, bypassing the Gpt façade. Every LLphant chat object is
 * wrapped by {@see CountingChat} at construction, so a single increment point captures
 * both the façade path and the raw path.
 *
 * Static by design: the chat objects are created deep in the provider layer and read back
 * by the orchestrator / eval harness with no shared instance to thread through. The count
 * is per PHP process (one web request = one process under PHP-FPM); call reset() at the
 * start of a request to scope it.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt
 */
final class LlmCallCounter
{
  private static int $count = 0;

  /**
   * Increment the counter by one LLM round-trip. Called only by {@see CountingChat} on its
   * six generation methods.
   */
  public static function increment(): void
  {
    self::$count++;
  }

  /**
   * Current number of LLM round-trips counted since the last reset.
   */
  public static function count(): int
  {
    return self::$count;
  }

  /**
   * Reset the counter to zero. Call once at the start of a request to scope the count to it.
   */
  public static function reset(): void
  {
    self::$count = 0;
  }
}

<?php
/**
 * PostResponseDeferrer.php
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * @package ClicShopping\AI\Infrastructure\Async
 */

namespace ClicShopping\AI\Infrastructure\Async;

/**
 * PostResponseDeferrer
 *
 * Runs registered tasks AFTER the HTTP response has been flushed to the client, so that
 * post-response observability / learning work (e.g. the actor-critic adaptive-weighting LLM
 * call) never adds to the user-perceived latency of the request that produced it.
 *
 * Mechanism: the first deferred task lazily registers a single shutdown handler. Because that
 * registration happens mid-request (while a task is being deferred), the handler runs LAST
 * among the shutdown handlers — after the normal request body and any earlier shutdown work
 * (logging flush, etc.). At that point it flushes the response with fastcgi_finish_request()
 * (PHP-FPM) and then drains the queue. On SAPIs without fastcgi_finish_request() (CLI, some
 * servers) the tasks still run at shutdown — deferred to the end of the request, just without
 * the early client flush.
 *
 * DEFER, NEVER DROP: every queued task is executed (post-flush), preserving the learning loop
 * it feeds (CriticRegistry / ReputationStore). This is not fire-and-forget — a task that throws
 * is isolated (caught + logged) so it can neither corrupt a sibling task nor surface to the
 * client, but it is still attempted.
 *
 * Trade-off: with fastcgi_finish_request() the client is released immediately, but the PHP-FPM
 * worker stays busy for the duration of the deferred work (it is not a cross-process queue). If
 * that worker occupancy ever hurts throughput, move the heavy task to the DB-backed JobQueue /
 * cron path instead of this in-request deferral.
 */
class PostResponseDeferrer
{
  /** @var array<int, array{task: callable, label: string}> Pending post-response tasks. */
  private static array $queue = [];

  /** @var bool Whether the shutdown handler has been registered for this request. */
  private static bool $registered = false;

  /**
   * Queue a task to run after the response is flushed to the client.
   *
   * The shutdown handler is registered on the first call only. Anything the task needs must be
   * captured in the closure (by value) so it stays valid until shutdown.
   *
   * @param callable $task  The work to run after the response is sent.
   * @param string   $label Short identifier used in the failure log line.
   */
  public static function defer(callable $task, string $label = ''): void
  {
    self::$queue[] = ['task' => $task, 'label' => $label];

    if (!self::$registered) {
      self::$registered = true;
      register_shutdown_function(self::flushAndRun(...));
    }
  }

  /**
   * Shutdown handler: flush the response to the client (PHP-FPM), then drain the queue.
   *
   * Not part of the public API: the shutdown closure is built in class scope, so callers use
   * defer() (or runNow() in CLI/tests).
   */
  private static function flushAndRun(): void
  {
    if (\function_exists('fastcgi_finish_request')) {
      @\fastcgi_finish_request();
    }

    while (self::$queue !== []) {
      $entry = \array_shift(self::$queue);
      try {
        ($entry['task'])();
      } catch (\Throwable $e) {
        error_log('[PostResponseDeferrer] deferred task "' . $entry['label'] . '" failed: ' . $e->getMessage());
      }
    }
  }

  /**
   * Drain the queue immediately instead of waiting for shutdown.
   *
   * Intended for CLI / tests (e.g. the eval harness) that must observe the deferred learning
   * state before the process ends. In a normal FPM request, prefer letting shutdown handle it.
   */
  public static function runNow(): void
  {
    self::flushAndRun();
  }

  /**
   * Discard queued tasks WITHOUT running them. Test hook only — never use to skip real work.
   */
  public static function clear(): void
  {
    self::$queue = [];
  }
}

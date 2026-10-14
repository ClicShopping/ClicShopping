<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Common;

use ClicShopping\OM\Registry;

/**
 * CronLogger
 *
 * Unified writer for clic_cron_log.  Every cron in the AI/Ecommerce app
 * routes its summary through this class so analytics queries can compare
 * SEO / FAQ / CockpitAI / embedding runs without parsing free-text logs.
 *
 * Usage:
 *   $log = new CronLogger('seo', 'productSeoOptimization');
 *   $log->start();
 *   …
 *   $log->finish('completed', [
 *     'targets_found'     => 50,
 *     'targets_processed' => 30,
 *     'success_count'     => 28,
 *     'failure_count'     => 2,
 *     'skipped_count'     => 20,
 *     'metadata'          => ['phase1'=>30,'phase2'=>27,'phase3'=>15],
 *   ]);
 */
class CronLogger
{
  private mixed $db;
  private string $origin;
  private string $cronCode;
  private string $runUuid;
  private ?int $logId = null;
  private float $startedMicro = 0.0;
  private string $startedAt   = '';

  /**
   * @param string $origin   One of: seo | faq | cockpitai | embedding | other
   * @param string $cronCode Matches clic_cron.code
   */
  public function __construct(string $origin, string $cronCode)
  {
    $this->db       = Registry::get('Db');
    $this->origin   = $origin;
    $this->cronCode = $cronCode;
    $this->runUuid  = $this->generateUuid();
  }

  public function getRunUuid(): string
  {
    return $this->runUuid;
  }

  /**
   * Open a row in 'running' state.  Idempotent within the lifetime of the
   * instance — calling start() twice in a row keeps the original logId.
   */
  public function start(?int $languageId = null): int
  {
    if ($this->logId !== null) {
      return $this->logId;
    }

    $this->startedMicro = microtime(true);
    $this->startedAt    = date('Y-m-d H:i:s');

    try {
      $this->db->save('cron_log', [
        'origin'      => $this->origin,
        'cron_code'   => $this->cronCode,
        'run_uuid'    => $this->runUuid,
        'language_id' => $languageId,
        'status'      => 'running',
        'started_at'  => $this->startedAt,
      ]);
      $this->logId = (int)$this->db->lastInsertId();
    } catch (\Throwable $e) {
      // Logging must never break the cron itself.
      error_log('[CronLogger] start() failed: ' . $e->getMessage());
    }

    return (int)$this->logId;
  }

  /**
   * Close the row with the final status and counters.
   *
   * @param string $status One of: completed | failed | partial | skipped
   * @param array  $data   Counter overrides + optional 'metadata' (array, JSON-encoded here)
   *                       Recognised keys: targets_found, targets_processed,
   *                       success_count, failure_count, skipped_count,
   *                       error_messages, metadata.
   */
  public function finish(string $status, array $data = []): void
  {
    if ($this->logId === null) {
      // start() was never called — open a synthetic row first so the run
      // is still visible in analytics.
      $this->start($data['language_id'] ?? null);
    }
    if ($this->logId === null) {
      return;
    }

    $duration = (int)max(0, microtime(true) - $this->startedMicro);
    $memMb    = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

    $payload = [
      'status'                 => $status,
      'finished_at'            => date('Y-m-d H:i:s'),
      'targets_found'          => (int)($data['targets_found']     ?? 0),
      'targets_processed'      => (int)($data['targets_processed'] ?? 0),
      'success_count'          => (int)($data['success_count']     ?? 0),
      'failure_count'          => (int)($data['failure_count']     ?? 0),
      'skipped_count'          => (int)($data['skipped_count']     ?? 0),
      'execution_time_seconds' => $duration,
      'memory_peak_mb'         => $memMb,
      'error_messages'         => isset($data['error_messages'])
        ? (is_array($data['error_messages'])
            ? json_encode($data['error_messages'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string)$data['error_messages'])
        : null,
      'metadata'               => !empty($data['metadata'])
        ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null,
    ];

    try {
      $this->db->save('cron_log', $payload, ['id' => $this->logId]);
    } catch (\Throwable $e) {
      error_log('[CronLogger] finish() failed: ' . $e->getMessage());
    }
  }

  private function generateUuid(): string
  {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s',
      substr($hex, 0, 8), substr($hex, 8, 4),
      substr($hex, 12, 4), substr($hex, 16, 4),
      substr($hex, 20, 12)
    );
  }
}

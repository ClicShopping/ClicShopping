<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Module\Hooks\Shop\Cronjob;

use ClicShopping\OM\Registry;

/**
 * Periodic maintenance for the Api app.
 *
 * Mirror of Apps/Tools/MCP/Module/Hooks/Shop/Cronjob/Process.php (B5):
 * purges sessions, rate-limit counters and failed-attempt counters that
 * have aged past their configured window, so these tables do not grow
 * unbounded.
 */
class Process implements \ClicShopping\OM\Modules\HooksInterface
{
  public function execute(): void
  {
    $this->cleanupExpiredSessions();
    $this->cleanupRateLimits();
    $this->cleanupFailedAttempts();
  }

  /**
   * Purge sessions whose date_modified is older than the configured timeout.
   *
   * Mirrors the expiry rule applied by ApiSecurity::checkToken(). Without
   * this purge, the api_session table grows on every login because Api
   * authentication used to create a new row per call (and silent renewals
   * still produce orphans).
   *
   * @return void
   */
  private function cleanupExpiredSessions(): void
  {
    $timeoutMinutes = \defined('CLICSHOPPING_APP_API_AI_SESSION_TIMEOUT_MINUTES')
      ? max(1, (int)CLICSHOPPING_APP_API_AI_SESSION_TIMEOUT_MINUTES)
      : 30;

    $db = Registry::get('Db');
    $Qdelete = $db->prepare('delete from :table_api_session
                              where date_modified < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
                            ');
    $Qdelete->bindInt(':minutes', $timeoutMinutes);
    $Qdelete->execute();
  }

  /**
   * Purge rate-limit rows older than the configured window — they cannot
   * affect any future decision. ApiSecurity::checkRateLimit() already
   * deletes them on the hot path but a background purge prevents the
   * table from inflating between two checks.
   *
   * @return void
   */
  private function cleanupRateLimits(): void
  {
    $windowSeconds = \defined('CLICSHOPPING_APP_API_AI_RATE_LIMIT_WINDOW')
      ? max(1, (int)CLICSHOPPING_APP_API_AI_RATE_LIMIT_WINDOW)
      : 60;

    $db = Registry::get('Db');
    $Qdelete = $db->prepare('delete from :table_api_rate_limit
                              where timestamp < :threshold
                            ');
    $Qdelete->bindInt(':threshold', time() - $windowSeconds);
    $Qdelete->execute();
  }

  /**
   * Purge failed-login counters once they have aged past the lockout
   * duration — they no longer block any account.
   *
   * @return void
   */
  private function cleanupFailedAttempts(): void
  {
    $lockSeconds = \defined('CLICSHOPPING_APP_API_AI_ACCOUNT_LOCK_DURATION')
      ? max(1, (int)CLICSHOPPING_APP_API_AI_ACCOUNT_LOCK_DURATION)
      : 900;

    $db = Registry::get('Db');
    $Qdelete = $db->prepare('delete from :table_api_failed_attempts
                              where last_attempt < :threshold
                            ');
    $Qdelete->bindInt(':threshold', time() - $lockSeconds);
    $Qdelete->execute();
  }
}

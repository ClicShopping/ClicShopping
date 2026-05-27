<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Module\Hooks\Shop\Cronjob;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\McpMonitor;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpConnectionException;

class Process implements \ClicShopping\OM\Modules\HooksInterface
{
  /**
   * Main cron job execution method.
   * This is the entry point called by the framework.
   *
   * @return void
   */
  public function execute(): void
  {
    $this->cronJob();
  }

  /**
   * Handles the execution of a cron job for MCP monitoring.
   * It checks for a 'cronId' parameter and, if valid, executes the monitoring logic.
   *
   * @return void
   */
  private function cronJob(): void
  {
    $cronIdMcp = Cron::getCronCode('McpHealthCron');

    // Determine the cron ID to use for execution
    // It must either be explicitly set and match the MCP code, or the MCP code must exist to proceed.
    $executeMcp = false;
    if (isset($_GET['cronId'])) {
      $cronId = HTML::sanitize($_GET['cronId']);
      if ((int)$cronId === (int)$cronIdMcp) {
        $executeMcp = true;
      }
    } elseif ($cronIdMcp !== null) {
      $executeMcp = true;
    }

    // If the conditions are met, run the cron job logic
    if ($executeMcp) {
      try {
        Cron::updateCron($cronIdMcp);
        $this->runMcpMonitor();
      } catch (\Exception $e) {
        // Log the error for debugging purposes
        error_log('MCP Cron Job failed: ' . $e->getMessage());
      }
    }
  }

  /**
   * Executes the core MCP monitoring and logging process.
   * This method contains the main logic for checking health and storing alerts.
   *
   * @return void
   */
  private function runMcpMonitor(): void
  {
    try {
      $monitor = McpMonitor::getInstance();
      $alerts = $monitor->monitor();

      // Store monitoring results in the database
      if (!empty($alerts)) {
        $db = Registry::get('Db');
        foreach ($alerts as $alert) {
          $sqlArray = [
            'alert_type' => $alert['type'],
            'message' => $alert['message'],
            'alert_timestamp' => date('Y-m-d H:i:s', $alert['timestamp'])
          ];
          $db->save('mcp_alerts', $sqlArray);
        }
      }

      // Clean up old alerts to manage database size
      $this->cleanupAlerts();

      // Purge expired sessions and stale rate-limit / failed-attempt rows so
      // these tables do not grow unbounded.
      $this->cleanupExpiredSessions();
      $this->cleanupRateLimits();
      $this->cleanupFailedAttempts();

    } catch (McpConnectionException $e) {
      // Log connection-specific errors without halting the entire process
      error_log('MCP Connection Error: ' . $e->getMessage());
    }
  }

  /**
   * Removes alerts older than 30 days from the database.
   *
   * @return void
   */
  private function cleanupAlerts(): void
  {
    $db = Registry::get('Db');
    $Qdelete = $db->prepare('delete from :table_mcp_alerts
                             where alert_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)
                            ');
    $Qdelete->execute();
  }

  /**
   * Purge sessions whose date_modified is older than the configured timeout.
   *
   * Mirrors the expiry rule applied by McpSecurity::checkToken(): a session is
   * considered expired once its last activity is older than
   * CLICSHOPPING_APP_MCP_MC_SESSION_TIMEOUT_MINUTES minutes.
   *
   * Without this purge, the mcp_session table grows on every login because
   * MCP authentication used to create a new row per request before token
   * reuse was wired in. Even with token reuse, orphaned rows accumulate
   * (silent renewals, IP-mismatch rejections, etc.).
   *
   * @return void
   */
  private function cleanupExpiredSessions(): void
  {
    $timeoutMinutes = \defined('CLICSHOPPING_APP_MCP_MC_SESSION_TIMEOUT_MINUTES')
      ? max(1, (int)CLICSHOPPING_APP_MCP_MC_SESSION_TIMEOUT_MINUTES)
      : 30;

    $db = Registry::get('Db');
    $Qdelete = $db->prepare('delete from :table_mcp_session
                              where date_modified < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
                            ');
    $Qdelete->bindInt(':minutes', $timeoutMinutes);
    $Qdelete->execute();
  }

  /**
   * Purge rate-limit rows older than the configured window — they cannot
   * affect any future decision, McpSecurity::checkRateLimit() already
   * deletes them on the hot path but a background purge prevents the
   * table from inflating between two checks.
   *
   * @return void
   */
  private function cleanupRateLimits(): void
  {
    $windowSeconds = \defined('CLICSHOPPING_APP_MCP_MC_RATE_LIMIT_WINDOW')
      ? max(1, (int)CLICSHOPPING_APP_MCP_MC_RATE_LIMIT_WINDOW)
      : 60;

    $db = Registry::get('Db');
    $Qdelete = $db->prepare('delete from :table_mcp_rate_limit
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
    $lockSeconds = \defined('CLICSHOPPING_APP_MCP_MC_ACCOUNT_LOCK_DURATION')
      ? max(1, (int)CLICSHOPPING_APP_MCP_MC_ACCOUNT_LOCK_DURATION)
      : 900;

    $db = Registry::get('Db');
    $Qdelete = $db->prepare('delete from :table_mcp_failed_attempts
                              where last_attempt < :threshold
                            ');
    $Qdelete->bindInt(':threshold', time() - $lockSeconds);
    $Qdelete->execute();
  }
}
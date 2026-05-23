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
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin;


use ClicShopping\OM\Registry;
use ClicShopping\OM\Mail;

use ClicShopping\Apps\Tools\MCP\MCP;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpConnectionException;
use ClicShopping\OM\SimpleLogger;

/**
 * Acts as the brain of the MCP agentic system.
 * Analyzes performance recommendations, makes decisions, and logs alerts.
 */

class McpDecisionAgent
{
  /**
   * @var McpMonitor The monitoring component responsible for gathering performance data.
   */
  private McpMonitor $monitor;

  /**
   * @var McpService The service component for interacting with the MCP system.
   */
  private McpService $service;

  /**
   * @var mixed The database connection object.
   */
  private mixed $db;

  /**
   * @var Mail The email handler for sending alerts.
   */
  private Mail $mailer;

  /**
   * @var mixed The logger instance for recording actions and errors.
   */
  private mixed $logger;

  /**
   * @var MCP The main MCP application instance.
   */
  private mixed $app;

  /**
   * McpDecisionAgent constructor.
   *
   * Initializes the agent by setting up the monitor, service, logger, and database connections.
   * It ensures all necessary dependencies are available for the agent to function correctly.
   */
  public function __construct()
  {
    $config = [];
    $this->monitor = McpMonitor::getInstance($config);
    $this->service = McpService::getInstance();

    if (!Registry::exists('SimpleLogger')) {
      Registry::set('SimpleLogger', new SimpleLogger());
    }
    $this->logger = Registry::get('SimpleLogger');

    if (!Registry::exists('MCP')) {
      Registry::set('MCP', new MCP());
    }

    $this->app = Registry::get('MCP');

    if (!Registry::exists('Db')) {
      $this->logger->error('Database not found in registry. Unable to log alerts.');
    } else {
      $this->db = Registry::get('Db');
    }

    // Initialize the email handler with the correct class name
    $this->mailer = new Mail();
  }

  /**
   * Executes the agent's decision and action cycle.
   * This is the core of the "agentic" functionality.
   */
  public function runDecisionLoop(): void
  {
    // 1. Perception: Obtain performance data and recommendations
    $performanceData = $this->monitor->getPerformanceData();
    $recommendations = $performanceData['recommendations'];

    if (empty($recommendations)) {
      $this->logger->info("MCP status: All good. No actions needed.");
      return;
    }

    $this->logger->warning("MCP status: Recommendations found. Starting decision process.");

    // 2. Reasoning and Decision: Iterate over recommendations and take actions
    foreach ($recommendations as $rec) {
      $this->logger->info("Recommendation found: " . $rec['message'] . " (Priority: " . $rec['priority'] . ")");
      $this->takeActionAndLog($rec);
    }

    // 3. Cleanup: Delete old alerts
    $this->cleanupAlerts();
  }

  /**
   * Takes a concrete action based on a recommendation and logs it.
   *
   * @param array $recommendation The recommendation being analyzed.
   */
  private function takeActionAndLog(array $recommendation): void
  {
    $this->logAlert($recommendation);

    switch ($recommendation['priority']) {
      case 'high':
        $this->handleHighPriorityIssue($recommendation);
        break;
      case 'medium':
        $this->handleMediumPriorityIssue($recommendation);
        break;
      case 'low':
        $this->handleLowPriorityIssue($recommendation);
        break;
      default:
        $this->logger->info("No specific action defined for this priority. Logging only.");
        break;
    }
  }

  /**
   * Logs an alert to the database.
   *
   * @param array $alert The alert to log.
   */
  private function logAlert(array $alert): void
  {
    if (isset($this->db)) {
      $sql_array = [
        'alert_type' => $alert['type'] ?? 'info',
        'message' => $alert['message'],
        'alert_timestamp' => date('Y-m-d H:i:s')
      ];
      $this->db->save('mcp_alerts', $sql_array);
    }
  }

  /**
   * Cleans up old alerts from the database (keeps the last 30 days).
   */
  private function cleanupAlerts(): void
  {
    if (isset($this->db)) {
      $Qdelete = $this->db->prepare('delete from :table_mcp_alerts where alert_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)');
      $Qdelete->execute();
    }
  }

  /**
   * Handles high-priority issues.
   *
   * @param array $recommendation The high-priority recommendation.
   */
  private function handleHighPriorityIssue(array $recommendation): void
  {
    $message = "ALERT: " . $recommendation['message'] . ". Attempting automatic reconnection.";
    $this->logger->critical($message);

    try {
      $this->service->sendMessage($message, ['context' => 'system_alert', 'level' => 'critical']);
    } catch (\Exception $e) {
      $this->logger->error("Failed to send alert message: " . $e->getMessage());
    }

    $subject = $this->app->getDef('text_alert_mcp_critical_subject');
    $body = $this->app->getDef('text_alert_mcp_critical', ['recommendation' => $recommendation['message']]);

    $this->sendEmailAlert($subject, $body);

    // Action 3: Attempt corrective action (reconnection)
    try {
      $this->monitor->disconnect();
      $this->monitor->connect();
      $this->logger->info("Attempted reconnection to MCP service.");
    } catch (McpConnectionException $e) {
      $this->logger->error("Failed to reconnect: " . $e->getMessage());
    }
  }

  /**
   * Handles medium-priority issues.
   *
   * @param array $recommendation The medium-priority recommendation.
   */
  private function handleMediumPriorityIssue(array $recommendation): void
  {
    $message = "WARNING: " . $recommendation['message'] . ". Please investigate.";
    $this->logger->warning($message);

    try {
      $this->service->sendMessage($message, ['context' => 'system_alert', 'level' => 'warning']);
    } catch (\Exception $e) {
      $this->logger->error("Failed to send alert message: " . $e->getMessage());
    }

    $subject = $this->app->getDef('text_alert_mcp_medium_subject');
    $body = $this->app->getDef('text_alert_mcp_medium', ['recommendation' => $recommendation['message']]);

    $this->sendEmailAlert($subject, $body);
  }

  /**
   * Handles low-priority issues.
   *
   * @param array $recommendation The low-priority recommendation.
   */
  private function handleLowPriorityIssue(array $recommendation): void
  {
    $message = "INFO: " . $recommendation['message'] . ". No action required, but monitor.";
    $this->logger->info($message);
  }

  /**
   * Sends an alert email to the admin.
   *
   * @param string $subject Email subject.
   * @param string $body Email message body.
   */
  private function sendEmailAlert(string $subject, string $body): void
  {
    $to = TEXT_STORE_OWNER_EMAIL;
    $from = TEXT_STORE_OWNER_EMAIL;
    $fromName = 'Agent MCP';

    try {
      if (\Defined('CLICSHOPPING_APP_MCP_MC_ALERT_NOTIFICATION_STATUS') && CLICSHOPPING_APP_MCP_MC_ALERT_NOTIFICATION_STATUS == 'True') {
        $this->mailer->clicMail($to, 'Admin', $subject, $body, $fromName, $from);
        $this->logger->info("Email alert sent successfully.");
      }
    } catch (\Exception $e) {
      $this->logger->error("Failed to send email alert: " . $e->getMessage());
    }
  }
}
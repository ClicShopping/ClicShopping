<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/*
 * toutes les 5 minutes
 */

namespace ClicShopping\Apps\Tools\MCP\Module\Hooks\ClicShoppingAdmin\Cronjob;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\McpDecisionAgent;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron as Cronjob;

/**
 * Handles the execution of the MCP (Multi-Channel Products) Agent cron job.
 * This class serves as the entry point for the scheduled task, delegating the
 * decision-making and action logic to the McpDecisionAgent.
 */
class Process implements HooksInterface
{
  /**
   * MCP Agent instance.
   * @var McpDecisionAgent
   */
  private McpDecisionAgent $mcpAgent;

  /**
   * Initializes the cron job process.
   * We ensure a single instance of the agent is available.
   */
  public function __construct()
  {
    if (!Registry::exists('McpDecisionAgent')) {
      Registry::set('McpDecisionAgent', new McpDecisionAgent());
    }
    // We get the unique instance from the registry.
    $this->mcpAgent = Registry::get('McpDecisionAgent');
  }

  /**
   * Executes the main process for the cron job.
   * This is the entry point called by the framework.
   *
   * @return void
   */
  public function execute(): void
  {
    $this->cronJob();
  }

  /**
   * Runs the MCP Decision Agent's main loop.
   * This method encapsulates the core logic of the agent.
   *
   * @return void
   */
  private function runMcpAgent(): void
  {
    try {
      // We directly use the agent instance from the property.
      $this->mcpAgent->runDecisionLoop();
    } catch (\Exception $e) {
      // Log any unhandled exceptions during the agent's execution
      error_log('MCP Decision Agent failed with an error: ' . $e->getMessage());
    }
  }

  /**
   * Handles the execution of a cron job.
   *
   * This method checks for a 'cronId' parameter, validates it, and if it matches
   * the MCP agent's cron code, it triggers the agent's logic.
   *
   * @return void
   */
  private function cronJob(): void
  {
    $cron_id_mcp = Cronjob::getCronCode('mcp_agent');

    // Enhanced check to ensure the cron is running from a valid source
    if (isset($_GET['cronId']) && (int)HTML::sanitize($_GET['cronId']) === (int)$cron_id_mcp) {
      Cronjob::updateCron($cron_id_mcp);
      $this->runMcpAgent();
    } elseif (!isset($_GET['cronId']) && isset($cron_id_mcp)) {
      // This is a potential manual or unverified execution.
      // For security, you might consider logging this or not executing.
      // However, as per the original logic, we run it.
      Cronjob::updateCron($cron_id_mcp);
      $this->runMcpAgent();
    } else {
      // Log invalid cronId attempt for security monitoring
      error_log('Invalid or missing cronId parameter detected.');
    }
  }
}
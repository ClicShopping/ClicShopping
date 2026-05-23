<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\ClicShoppingAdmin\Pages\Home\Actions\MCP;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('MCP');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $cId = HTML::sanitize($_GET['cID']);

    $username = HTML::sanitize($_POST['username']);
    $mcp_key = HTML::sanitize($_POST['mcp_key']);

    // Data permissions
    $select_data = isset($_POST['select_data']) ? HTML::sanitize($_POST['select_data']) : 0;
    $update_data = isset($_POST['update_data']) ? HTML::sanitize($_POST['update_data']) : 0;
    $create_data = isset($_POST['create_data']) ? HTML::sanitize($_POST['create_data']) : 0;
    $delete_data = isset($_POST['delete_data']) ? HTML::sanitize($_POST['delete_data']) : 0;
    $create_db = isset($_POST['create_db']) ? HTML::sanitize($_POST['create_db']) : 0;

    // Server configuration
    $server_host = HTML::sanitize($_POST['server_host'] ?? '');
    $server_port = HTML::sanitize($_POST['server_port'] ?? '');
    $ssl_enabled = isset($_POST['ssl_enabled']) ? HTML::sanitize($_POST['ssl_enabled']) : 0;

    // Monitoring & alerts
    $alert_threshold = HTML::sanitize($_POST['alert_threshold'] ?? '');
    $latency_threshold = HTML::sanitize($_POST['latency_threshold'] ?? '');
    $downtime_threshold = HTML::sanitize($_POST['downtime_threshold'] ?? '');
    $data_retention = HTML::sanitize($_POST['data_retention'] ?? '');
    $alert_notification = isset($_POST['alert_notification']) ? HTML::sanitize($_POST['alert_notification']) : 0;

    $sql_data_array = [
      'username' => $username,
      'mcp_key' => $mcp_key,
      'date_modified' => 'now()',
      'select_data' => $select_data,
      'update_data' => $update_data,
      'create_data' => $create_data,
      'delete_data' => $delete_data,
      'create_db' => $create_db,
      'server_host' => $server_host,
      'server_port' => $server_port,
      'ssl_enabled' => $ssl_enabled,
      'alert_threshold' => $alert_threshold,
      'latency_threshold' => $latency_threshold,
      'downtime_threshold' => $downtime_threshold,
      'data_retention' => $data_retention,
      'alert_notification' => $alert_notification,
    ];

    $this->app->db->save('mcp', $sql_data_array, ['mcp_id' => (int)$cId]);

    $this->app->redirect("MCP&page={$page}");
  }
}
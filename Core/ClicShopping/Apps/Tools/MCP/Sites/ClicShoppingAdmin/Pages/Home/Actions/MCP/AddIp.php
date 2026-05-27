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

class AddIp extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('MCP');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['AddIp'])) {
      $mcp_id = HTML::sanitize($_GET['cID']);
      $ip = HTML::sanitize($_POST['ip']);
      $comment = HTML::sanitize($_POST['comment']);

      $sql_data_array = [
        'mcp_id' => $mcp_id,
        'ip' => $ip,
        'comment' => $comment,
      ];

      $this->app->db->save('mcp_ip', $sql_data_array);
    }

    $this->app->redirect('Edit&cID=' . (int)$_GET['cID'] . '&page=' . $page . '&#tab2');
  }
}
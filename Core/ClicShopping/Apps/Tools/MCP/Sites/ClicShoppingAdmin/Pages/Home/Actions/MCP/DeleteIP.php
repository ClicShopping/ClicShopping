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

class DeleteIP extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('MCP');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['DeleteIP'])) {
      $mcp_ip_id = HTML::sanitize($_GET['dID']);

      $this->app->db->delete('mcp_ip', ['mcp_ip_id' => (int)$mcp_ip_id]);
    }

    $this->app->redirect('Edit&cID=' . (int)$_GET['cID'] . '&page=' . $page . '&#tab2');
  }
}
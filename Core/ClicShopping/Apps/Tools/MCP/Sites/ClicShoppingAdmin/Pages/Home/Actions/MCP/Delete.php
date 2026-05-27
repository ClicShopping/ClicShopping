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

class Delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('MCP');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['Delete'])) {
      $mcp_id = HTML::sanitize($_GET['cID']);

      $this->app->db->delete('mcp', ['mcp_id' => (int)$mcp_id]);
      $this->app->db->delete('mcp_ip', ['mcp_id' => (int)$mcp_id]);
      $this->app->db->delete('mcp_session ', ['mcp_id' => (int)$mcp_id]);
    }

    $this->app->redirect('MCP&page=' . $page);
  }
}
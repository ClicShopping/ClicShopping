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

class DeleteSession extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('MCP');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['DeleteSession'])) {
      $mcp_session_id = HTML::sanitize($_GET['cID']);

      $this->app->db->delete('mcp_session', ['mcp_session_id' => (int)$mcp_session_id]);
    }

    $this->app->redirect('Edit&cID=' . (int)$_GET['cID'] . '&page=' . $page . '&#tab3');
  }
}
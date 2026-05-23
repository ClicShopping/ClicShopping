<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class UpdateAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('MCP');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (!Registry::exists('McpAdmin')) {
      Registry::set('McpAdmin', new McpAdmin());
    }

    $McpAdmin = Registry::get('McpAdmin');

    $McpAdmin->updateAllMcp();

    Cache::clear('mcp');

    $this->app->redirect('Mcp&page=' . $page);
  }
}
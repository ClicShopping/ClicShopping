<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class MCP extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_MCP = Registry::get('MCP');

    $this->page->setFile('mcp.php');

    $CLICSHOPPING_MCP->loadDefinitions('Sites/ClicShoppingAdmin/mcp');
  }
}
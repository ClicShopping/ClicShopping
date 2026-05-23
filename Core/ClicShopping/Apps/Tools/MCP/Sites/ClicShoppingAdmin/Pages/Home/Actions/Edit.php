<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Edit extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_MCP = Registry::get('MCP');
    $this->page->data['action'] = 'MCP';
    $this->page->setFile('edit.php');

    $CLICSHOPPING_MCP->loadDefinitions('Sites/ClicShoppingAdmin/edit');
  }
}
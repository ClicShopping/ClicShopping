<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ModulesHooks\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class ModulesHooks extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ModulesHooks = Registry::get('ModulesHooks');

    $this->page->setFile('modules_hooks.php');

    $CLICSHOPPING_ModulesHooks->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\SecurityCheck\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class SecurityCheck extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {
    $CLICSHOPPING_SecurityCheck = Registry::get('SecurityCheck');

    $this->page->setFile('security_check.php');

    $CLICSHOPPING_SecurityCheck->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ServiceAPP\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class ServiceAPPAdmin extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ServiceAPP = Registry::get('ServiceAPP');

    $this->page->setFile('service_admin.php');

    $CLICSHOPPING_ServiceAPP->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Sites\ClicShoppingAdmin\Pages\Home\Actions\Configure;

use ClicShopping\Apps\Configuration\ChatGpt\Sql\MariaDb\MariaDb;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
    $current_module = $this->page->data['current_module'];

    //add condition to select mariaDb ou postgres
    Registry::set('MariaDb', new MariaDb());
    $CLICSHOPPING_MariaDb = Registry::get('MariaDb');
    $CLICSHOPPING_MariaDb->execute();

    $CLICSHOPPING_MessageStack->add($CLICSHOPPING_ChatGpt->getDef('alert_module_install_update'), 'success', 'ChatGpt');

    $CLICSHOPPING_ChatGpt->redirect('Configure&module=' . $current_module);
  }
}

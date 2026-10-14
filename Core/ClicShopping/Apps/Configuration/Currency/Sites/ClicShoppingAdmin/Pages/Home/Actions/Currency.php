<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Currency\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Currency extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Currency = Registry::get('Currency');

    $this->page->setFile('currency.php');
    $this->page->data['action'] = 'Currency';

    $CLICSHOPPING_Currency->loadDefinitions('Sites/ClicShoppingAdmin/Currency');
  }
}
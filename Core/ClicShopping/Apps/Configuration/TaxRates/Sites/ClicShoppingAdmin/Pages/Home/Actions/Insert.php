<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TaxRates\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_TaxRates = Registry::get('TaxRates');

    $this->page->setFile('insert.php');
    $this->page->data['action'] = 'Insert';

    $CLICSHOPPING_TaxRates->loadDefinitions('Sites/ClicShoppingAdmin/TaxRates');
  }
}
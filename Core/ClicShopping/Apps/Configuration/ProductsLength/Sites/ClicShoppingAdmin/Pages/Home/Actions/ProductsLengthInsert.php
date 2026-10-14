<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsLength\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class ProductsLengthInsert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ProductsLength = Registry::get('ProductsLength');

    $this->page->setFile('products_length_insert.php');
    $this->page->data['action'] = 'ProductsLengthInsert';

    $CLICSHOPPING_ProductsLength->loadDefinitions('Sites/products_length');
  }
}
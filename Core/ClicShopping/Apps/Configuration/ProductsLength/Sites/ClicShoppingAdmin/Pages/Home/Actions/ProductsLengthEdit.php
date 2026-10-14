<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsLength\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class ProductsLengthEdit extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ProductsLength = Registry::get('ProductsLength');

    $this->page->setFile('products_length_edit.php');
    $this->page->data['action'] = 'ProductsLengthUpdate';

    $CLICSHOPPING_ProductsLength->loadDefinitions('Sites/ClicShoppingAdmin/products_length');
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class ProductsAttributes extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');

    $this->page->setFile('products_attributes.php');
    $this->page->data['action'] = 'ProductsAttributes';

    $CLICSHOPPING_ProductsAttributes->loadDefinitions('Sites/ClicShoppingAdmin/products_attributes');
  }
}
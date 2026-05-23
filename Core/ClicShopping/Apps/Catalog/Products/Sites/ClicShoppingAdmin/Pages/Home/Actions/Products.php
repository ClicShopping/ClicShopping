<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

/**
 * Class Products
 *
 * This action class is responsible for setting up the Products page in the admin interface.
 * It defines the page file to be used and loads the necessary language definitions.
 */
class Products extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * Execute the action to set up the Products page.
   *
   * This method sets the page file to 'products.php', defines the action as 'Products',
   * and loads the relevant language definitions for the Products section in the admin interface.
   */
  public function execute()
  {
    $CLICSHOPPING_Products = Registry::get('Products');

    $this->page->setFile('products.php');
    $this->page->data['action'] = 'Products';

    $CLICSHOPPING_Products->loadDefinitions('Sites/ClicShoppingAdmin/Products');
  }
}
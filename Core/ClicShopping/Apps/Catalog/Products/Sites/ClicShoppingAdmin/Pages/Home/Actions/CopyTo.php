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
 * Class CopyTo
 *
 * This action class handles the "Copy To" functionality in the admin interface for products.
 * It sets up the page to allow users to copy a product to another category or location.
 */
class CopyTo extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * Execute the action to set up the "Copy To" page.
   *
   * This method configures the page to display the "Copy To" interface,
   * setting the appropriate file and loading necessary language definitions.
   */
  public function execute()
  {
    $CLICSHOPPING_Products = Registry::get('Products');

    $this->page->setFile('copy_to.php');
    $this->page->data['action'] = 'CopyConfirm';

    $CLICSHOPPING_Products->loadDefinitions('Sites/ClicShoppingAdmin/Products');
  }
}
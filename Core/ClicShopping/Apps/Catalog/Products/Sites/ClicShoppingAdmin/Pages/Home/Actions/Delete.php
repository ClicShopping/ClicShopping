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
 * Class Delete
 *
 * This action class handles the deletion of products in the admin interface.
 * It sets up the page to confirm the deletion of a product.
 */
class Delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * Execute the action to set up the delete confirmation page.
   *
   * This method configures the page to display the delete confirmation interface,
   * setting the appropriate file and loading necessary language definitions.
   */
  public function execute()
  {
    $CLICSHOPPING_Products = Registry::get('Products');

    $this->page->setFile('delete.php');
    $this->page->data['action'] = 'DeleteConfirm';

    $CLICSHOPPING_Products->loadDefinitions('Sites/ClicShoppingAdmin/Products');
  }
}
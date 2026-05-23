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
 * Class Move
 *
 * This action class handles the initiation of the product move process in the admin interface.
 * It sets up the necessary page and action for moving products to a different category.
 */
class Move extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * Execute the action to initiate the product move process.
   *
   * This method sets the page file to 'move.php' and defines the action as 'MoveConfirm'.
   * It also loads the necessary language definitions for the Products app.
   */
  public function execute()
  {
    $CLICSHOPPING_Products = Registry::get('Products');

    $this->page->setFile('move.php');
    $this->page->data['action'] = 'MoveConfirm';

    $CLICSHOPPING_Products->loadDefinitions('Sites/ClicShoppingAdmin/Products');
  }
}
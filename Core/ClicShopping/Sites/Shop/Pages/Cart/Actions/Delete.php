<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Cart\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_GET['products_id'])) {
      $CLICSHOPPING_ShoppingCart->remove($_GET['products_id']);
    }

    $CLICSHOPPING_Hooks->call('Cart', 'Delete');

    CLICSHOPPING::redirect(null, 'Cart');
  }
}

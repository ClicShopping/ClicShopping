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
use function count;
use function in_array;
use function is_array;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_GET['Update'], $_GET['Cart'])) {
      if (isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
        if (isset($_POST['products_id'])) {

          for ($i = 0, $n = count($_POST['products_id']); $i < $n; $i++) {
            $attributes = $_POST['id'][$_POST['products_id'][$i]] ?? '';

            $product_id = $CLICSHOPPING_ShoppingCart->getUprid($_POST['products_id'][$i], $attributes);

            if ($_POST['cart_quantity'][$i] > 0) {
              if ($_POST['cart_quantity'][$i] !== $CLICSHOPPING_ShoppingCart->getQuantity($CLICSHOPPING_ShoppingCart->getUprid($_POST['products_id'][$i], $attributes))) {
                $CLICSHOPPING_ShoppingCart->addCart($_POST['products_id'][$i], $_POST['cart_quantity'][$i], $attributes, false);
              }
            } else {
              if (isset($_POST['cart_delete'])) {
                if (in_array($_POST['products_id'][$i], (is_array($_POST['cart_delete']) ? $_POST['cart_delete'] : array()), true)) {
                  $CLICSHOPPING_ShoppingCart->remove($product_id);
                }
              }
            }
          }
        }

        $CLICSHOPPING_Hooks->call('Cart', 'Update');
      }
    }

    CLICSHOPPING::redirect(null, 'Cart');
  }
}

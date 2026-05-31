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

class Delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    // CSRF protection: the removal must carry the session token (GET or POST).
    $token = $_POST['formid'] ?? $_GET['formid'] ?? '';
    $session_token = $_SESSION['sessiontoken'] ?? '';

    if ($token !== '' && hash_equals((string)$session_token, (string)$token)) {
      if (isset($_GET['products_id'])) {
        $CLICSHOPPING_ShoppingCart->remove($_GET['products_id']);
      }

      // Extension point kept for future cart-delete integrations.
      $CLICSHOPPING_Hooks->call('Cart', 'Delete');
    }

    CLICSHOPPING::redirect(null, 'Cart');
  }
}

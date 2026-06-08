<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Cart\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function defined;

class Add extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Prod = Registry::get('Prod');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $parameters = '';

      if (isset($_POST['products_id']) && is_numeric($_POST['products_id']) && isset($_POST['cart_quantity']) && is_numeric($_POST['cart_quantity'])) {
        if (!empty($_POST['id'])) {
          $attributes = HTML::sanitize($_POST['id']);
        } else {
          $attributes = '';
        }

        $CLICSHOPPING_ShoppingCart->addCart($_POST['products_id'], $CLICSHOPPING_ShoppingCart->getQuantity($CLICSHOPPING_Prod::getProductIDString($_POST['products_id'], $attributes)) + ((int)$_POST['cart_quantity']), $attributes);

        // Defense-in-depth: only keep the return url if it is an internal page reference.
        // Reject anything starting with a scheme (http:, javascript:, …), a protocol-relative
        // "//host" or a backslash, and anything containing CR/LF.
        $safeUrl = '';

        if (isset($_POST['url']) && is_string($_POST['url'])
          && !preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|\\\\)#i', $_POST['url'])
          && !preg_match('#[\r\n]#', $_POST['url'])) {
          $safeUrl = $_POST['url'];
        }

        if (defined('SEARCH_ENGINE_FRIENDLY_URLS_PRO') && SEARCH_ENGINE_FRIENDLY_URLS_PRO == 'true' && !isset($_SESSION['login_customer_id'])) {
          if (DISPLAY_CART == 'true') {
            $goto = null;
            $parameters = 'Cart';
          } else {
            $goto = null;
            $parameters = $safeUrl;
          }
        } else {
          if (DISPLAY_CART == 'true') {
            $goto = CLICSHOPPING::getConfig('bootstrap_file');
            $parameters = 'Cart';
          } else {
            $goto = CLICSHOPPING::getConfig('bootstrap_file');
            $parameters = $safeUrl;
          }
        }

        $CLICSHOPPING_Hooks->call('Cart', 'Add');

        CLICSHOPPING::redirect($goto, $parameters);
      }
    }
  }
}

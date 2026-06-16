<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Checkout\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Payment;
use function count;
use function is_array;
use function is_null;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_OrderTotal = Registry::get('OrderTotal');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Template = Registry::get('Template');

// if the customer is not logged on, redirect them to the login page
    if (!$CLICSHOPPING_Customer->isLoggedOn()) {
      $CLICSHOPPING_NavigationHistory->setSnapshot(array('mode' => null, 'page' => 'Checkout&Billing'));
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

// if there is nothing in the customers cart, redirect them to the shopping cart page
    if ($CLICSHOPPING_ShoppingCart->getCountContents() < 1) {
      CLICSHOPPING::redirect(null, 'Cart');
    }

// if no shipping method has been selected, redirect the customer to the shipping method selection page
    if (!isset($_SESSION['shipping']) || !isset($_SESSION['sendto'])) {
      CLICSHOPPING::redirect(null, 'Checkout&Shipping');
    }

    if ((!is_null(MODULE_PAYMENT_INSTALLED)) && (!isset($_SESSION['payment']))) {
      CLICSHOPPING::redirect(null, 'Checkout&Billing');
    }

// avoid hack attempts during the checkout procedure by checking the internal cartID.
    if (isset($CLICSHOPPING_ShoppingCart->cartID, $_SESSION['cartID'])
      && $CLICSHOPPING_ShoppingCart->cartID != $_SESSION['cartID']) {
      CLICSHOPPING::redirect(null, 'Checkout&Shipping');
    }

// load selected payment module
    Registry::set('Payment', new Payment($_SESSION['payment']));
    $CLICSHOPPING_Payment = Registry::get('Payment');

// Stock Check
    $any_out_of_stock = false;

    if (STOCK_CHECK == 'true') {
      for ($i = 0, $n = count($CLICSHOPPING_Order->products); $i < $n; $i++) {
        if ($CLICSHOPPING_ProductsCommon->getCheckStock($CLICSHOPPING_Order->products[$i]['id'], $CLICSHOPPING_Order->products[$i]['qty'])) {
          $any_out_of_stock = true;
        }
      }
      // Out of Stock
      if ((STOCK_ALLOW_CHECKOUT != 'true') && ($any_out_of_stock === true)) {
        CLICSHOPPING::redirect(null, 'Cart');
      }
    }

    $CLICSHOPPING_Payment->update_status();

    if (str_contains($CLICSHOPPING_Payment->selected_module, '\\')) {
      $code = 'Payment_' . str_replace('\\', '_', $CLICSHOPPING_Payment->selected_module);

      if (Registry::exists($code)) {
        $CLICSHOPPING_PM = Registry::get($code);
      }
    }

    if (!isset($CLICSHOPPING_PM) || ($CLICSHOPPING_Payment->selected_module != $_SESSION['payment']) || ($CLICSHOPPING_PM->enabled === false)) {
      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_no_payment_module_selected'), 'danger', 'header');

      CLICSHOPPING::redirect(null, 'Checkout&Billing');
    }

// order total
    $CLICSHOPPING_OrderTotal->process();

// load the before_process function from the payment modules
    $CLICSHOPPING_Payment->before_process();

// process to order
    $last_order_id = $CLICSHOPPING_Order->Insert();

    $CLICSHOPPING_Order->Process($last_order_id);

// load the after_process function from the payment modules
    $CLICSHOPPING_Payment->after_process();

    $source_folder = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/Hooks/Shop/CheckoutProcess/';

    if (is_dir($source_folder)) {
      $files_get = $CLICSHOPPING_Template->getSpecificFiles($source_folder, 'CheckoutProcess*');

      if (is_array($files_get)) {
        foreach ($files_get as $value) {
          if (!empty($value['name'])) {
            $CLICSHOPPING_Hooks->call('CheckoutProcess', $value['name']);
          }
        }
      }
    }

    $CLICSHOPPING_ShoppingCart->reset(true);

// unregister session variables used during checkout
    unset($_SESSION['sendto']);
    unset($_SESSION['billto']);
    unset($_SESSION['shipping']);
    unset($_SESSION['payment']);
    unset($_SESSION['comments']);
    unset($_SESSION['coupon']);

    $CLICSHOPPING_Language->loadDefinitions('checkout_process');

    CLICSHOPPING::redirect(null, 'Checkout&Success');
  }
}
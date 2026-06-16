<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Checkout\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Payment;
use function count;
use function is_array;
use function is_null;

class Confirmation extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    global $form_action_url;

    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_OrderTotal = Registry::get('OrderTotal');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $source_folder = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/Hooks/Shop/Checkout/';

    if (is_dir($source_folder)) {
      $files_get = $CLICSHOPPING_Template->getSpecificFiles($source_folder, 'CheckoutConfirmation*');

      if (is_array($files_get)) {
        foreach ($files_get as $value) {
          if (!empty($value['name'])) {
            $CLICSHOPPING_Hooks->call('Checkout', $value['name']);
          }
        }
      }
    }

// if there is nothing in the customers cart, redirect them to the shopping cart page
    if ($CLICSHOPPING_ShoppingCart->getCountContents() < 1) {
      CLICSHOPPING::redirect(null, 'Cart');
    }

// if the customer is not logged on, redirect them to the login page
    if (!$CLICSHOPPING_Customer->isLoggedOn()) {
      $CLICSHOPPING_NavigationHistory->setSnapshot(array('mode' => null, 'page' => 'Checkout&Billing'));
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

// avoid hack attempts during the checkout procedure by checking the internal cartID.
    if (isset($CLICSHOPPING_ShoppingCart->cartID, $_SESSION['cartID'])
      && $CLICSHOPPING_ShoppingCart->cartID != $_SESSION['cartID']) {
      CLICSHOPPING::redirect(null, 'Checkout&Shipping');
    }

// if no shipping method has been selected, redirect the customer to the shipping method selection page
    if (!isset($_SESSION['shipping'])) {
      CLICSHOPPING::redirect(null, 'Checkout&Shipping');
    }

    if (isset($_POST['payment'])) {
      $_SESSION['payment'] = $_POST['payment'];
    }

    if (isset($_POST['comments'])) {
      $_SESSION['comments'] = null;

      if (!is_null($_POST['comments'])) {
        $_SESSION['comments'] = HTML::sanitize($_POST['comments']);
      }
    }

// Confirmation des conditions des vente
    if (DISPLAY_CONDITIONS_ON_CHECKOUT == 'true') {
      if (!isset($_POST['conditions']) || HTML::sanitize($_POST['conditions']) != 1) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_conditions_not_accepted'), 'error');

        CLICSHOPPING::redirect(null, 'Checkout&Billing');
      }
    }

// load the selected payment module
    Registry::set('Payment', new Payment($_SESSION['payment']));
    $CLICSHOPPING_Payment = Registry::get('Payment');

// must be always before coupon
    $CLICSHOPPING_Order = Registry::get('Order');

    $CLICSHOPPING_Payment->update_status();

    if (str_contains($CLICSHOPPING_Payment->selected_module, '\\')) {
      $code = 'Payment_' . str_replace('\\', '_', $CLICSHOPPING_Payment->selected_module);

      if (Registry::exists($code)) {
        $CLICSHOPPING_PM = Registry::get($code);
      }
    }

    if (!isset($CLICSHOPPING_PM) || ($CLICSHOPPING_Payment->selected_module != $_SESSION['payment']) || ($CLICSHOPPING_PM->enabled === false)) {
      CLICSHOPPING::redirect(null, 'Checkout&Billing&error_message=' . urlencode(CLICSHOPPING::getDef('error_no_payment_module_selected')));
    }

    if (is_array($CLICSHOPPING_Payment->modules)) {
      $CLICSHOPPING_Payment->pre_confirmation_check();
    }

    $CLICSHOPPING_OrderTotal->process();

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

    $CLICSHOPPING_Language->loadDefinitions('checkout_confirmation');

// Payment Management Url redirection
    if (isset($CLICSHOPPING_PM->form_action_url)) {
      $form_action_url = $CLICSHOPPING_PM->form_action_url;
    } else {
      $form_action_url = CLICSHOPPING::link(null, 'Checkout&Process');
    }

    if (is_array($CLICSHOPPING_Payment->modules)) {
      $CLICSHOPPING_Payment->confirmation();
    }

    $CLICSHOPPING_Hooks->call('CheckoutConfirmation', 'PostAction');
// templates
    $this->page->setFile('checkout_confirmation.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('checkout_confirmation');
//language
    $CLICSHOPPING_Language->loadDefinitions('checkout_confirmation');

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title_1'), CLICSHOPPING::link(null, 'Checkout&Shipping'));
    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title_2'));
  }
}
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
use function count;
use function is_array;
use function is_null;

class Billing extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Language = Registry::get('Language');

// if the customer is not logged on, redirect them to the login page
    if (!$CLICSHOPPING_Customer->isLoggedOn()) {
      $CLICSHOPPING_NavigationHistory->setSnapshot();
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

// if there is nothing in the customers cart, redirect them to the shopping cart page
    if ($CLICSHOPPING_ShoppingCart->getCountContents() < 1) {
      CLICSHOPPING::redirect(null, 'Cart');
    }

// if no shipping method has been selected, redirect the customer to the shipping method selection page
    if ($_SESSION['shipping'] === false) {
      CLICSHOPPING::redirect(null, 'Checkout&Shipping');
    }

// avoid hack attempts during the checkout procedure by checking the internal cartID.
// Fail closed: if either id is missing (or null) or they differ, restart the shipping step.
    if (isset($CLICSHOPPING_ShoppingCart->cartID, $_SESSION['cartID'])
      && $CLICSHOPPING_ShoppingCart->cartID != $_SESSION['cartID']) {
      CLICSHOPPING::redirect(null, 'Checkout&Shipping');
    }

// Stock Check
    if ((STOCK_CHECK == 'true') && (STOCK_ALLOW_CHECKOUT != 'true')) {
      $products = $CLICSHOPPING_ShoppingCart->get_products();

      for ($i = 0, $n = count($products); $i < $n; $i++) {
        if ($CLICSHOPPING_ProductsCommon->getCheckStock($products[$i]['id'], $products[$i]['quantity'])) {
          CLICSHOPPING::redirect(null, 'Cart');
          break;
        }
      }
    }

// if no billing destination address was selected, use the customers own address as default
    if (!isset($_SESSION['billto'])) {
      $_SESSION['billto'] = $CLICSHOPPING_Customer->getDefaultAddressID();
    } else {
// verify the selected billing address
      if ((is_array($_SESSION['billto']) && empty($_SESSION['billto'])) || is_numeric($_SESSION['billto'])) {

        $QcheckAddress = $CLICSHOPPING_Db->prepare('select count(*) as total
                                                      from :table_address_book
                                                      where customers_id = :customers_id
                                                      and address_book_id =  :address_book_id
                                                     ');
        $QcheckAddress->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
        $QcheckAddress->bindInt(':address_book_id', $_SESSION['billto']);
        $QcheckAddress->execute();

        if ($QcheckAddress->fetch() === false) {
          $_SESSION['billto'] = $CLICSHOPPING_Customer->getDefaultAddressID();
          if (isset($_SESSION['payment'])) {
            unset($_SESSION['payment']);
          }
        }
      }
    }

    if (isset($_POST['comments']) && !is_null($_POST['comments'])) {
      $_SESSION['comments'] = HTML::sanitize($_POST['comments']);
    }

// templates
    $this->page->setFile('checkout_payment.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('checkout_payment');
//language
    $CLICSHOPPING_Language->loadDefinitions('checkout_payment');

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title_1'), CLICSHOPPING::link(null, 'Checkout&Shipping'));
    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title_2'), CLICSHOPPING::link(null, 'Checkout&Billing'));
  }
}
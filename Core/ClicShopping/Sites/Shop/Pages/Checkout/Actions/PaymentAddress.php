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
use ClicShopping\Sites\Shop\AddressBook;

class PaymentAddress extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
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

// Controle autorisation au client de modifier son adresse par defaut
    if ((AddressBook::countCustomersModifyAddressDefault() == 0)) {
      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_address_book_no_modify_default'), 'error');

      CLICSHOPPING::redirect(null, 'Checkout&Billing');
    }

// if no billing destination address was selected, use their own address as default
    if (!isset($_SESSION['billto'])) {
      $_SESSION['billto'] = $CLICSHOPPING_Customer->getDefaultAddressID();
    }

// templates
    $this->page->setFile('payment_address.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('checkout_payment_address');
//language
    $CLICSHOPPING_Language->loadDefinitions('checkout_payment_address');

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title_1'), CLICSHOPPING::link(null, 'Checkout&Billing'));
    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title_2'), CLICSHOPPING::link(null, 'Checkout&PaymentAddress'));
  }
}
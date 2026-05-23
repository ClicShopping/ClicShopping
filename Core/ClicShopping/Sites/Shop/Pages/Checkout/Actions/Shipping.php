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
use ClicShopping\Sites\Shop\Shipping as Delivery;
use function defined;
use function is_array;

class Shipping extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Order = Registry::get('Order');
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

// Verify if a street address exist concernant the customer
    $QaddressCustomer = $CLICSHOPPING_Db->prepare('select entry_street_address,
                                                            entry_postcode
                                                      from :table_address_book
                                                      where customers_id = :customers_id
                                                      and address_book_id = :address_book_id
                                                    ');
    $QaddressCustomer->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $QaddressCustomer->bindInt(':address_book_id', $CLICSHOPPING_Customer->getDefaultAddressID());
    $QaddressCustomer->execute();

//check if we need to continue the address creation
    if (empty($QaddressCustomer->value('entry_street_address')) || empty($QaddressCustomer->value('entry_postcode'))) {
      CLICSHOPPING::redirect(null, 'Account&AddressBookProcess&edit=' . $CLICSHOPPING_Customer->getID() . '&newcustomer=1');
    }

//check if address id exist else go shipping_address for new default address
    if (!$CLICSHOPPING_Customer->getDefaultAddressID()) {
      CLICSHOPPING::redirect(null, 'Account&AddressBookProcess&edit=' . $CLICSHOPPING_Customer->getID() . '&newcustomer=1');
    }

// if no shipping destination address was selected, use the customers own address as default
    if (!isset($_SESSION['sendto'])) {
      $_SESSION['sendto'] = $CLICSHOPPING_Customer->getDefaultAddressID();
    } else {
// verify the selected shipping address
      if ((is_array($_SESSION['sendto']) && empty($_SESSION['sendto'])) || is_numeric($_SESSION['sendto'])) {
        $QcheckAddress = $CLICSHOPPING_Db->prepare('select address_book_id
                                                      from :table_address_book
                                                      where customers_id = :customers_id
                                                      and address_book_id =  :address_book_id
                                                     ');
        $QcheckAddress->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
        $QcheckAddress->bindInt(':address_book_id', $_SESSION['sendto']);
        $QcheckAddress->execute();

        if ($QcheckAddress->fetch() === false) {
          $_SESSION['sendto'] = $CLICSHOPPING_Customer->getDefaultAddressID();

          if (isset($_SESSION['shipping'])) {
            unset($_SESSION['shipping']);
          }
        }
      }
    }

// register a random ID in the session to check throughout the checkout procedure
// against alterations in the shopping cart contents
    if (isset($_SESSION['cartID']) && isset($_SESSION['shipping']) && ($_SESSION['cartID'] != $CLICSHOPPING_ShoppingCart->cartID)) {
      unset($_SESSION['shipping']);
    }

    $_SESSION['cartID'] = $CLICSHOPPING_ShoppingCart->cartID = $CLICSHOPPING_ShoppingCart->generate_cart_id();

// if the order contains only virtual products, forward the customer to the billing page as
// a shipping address is not needed
    if ($CLICSHOPPING_Order->content_type == 'virtual') {
      $_SESSION['shipping'] = false;
      $_SESSION['sendto'] = false;
      CLICSHOPPING::redirect(null, 'Checkout&Billing');
    }

    Registry::set('Shipping', new Delivery());
    $CLICSHOPPING_Shipping = Registry::get('Shipping');

    if (defined('MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING') && (MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING == 'true')) {
      $pass = false;

      switch (MODULE_ORDER_TOTAL_SHIPPING_DESTINATION) {
        case 'national':
          if ($CLICSHOPPING_Order->delivery['country_id'] === STORE_COUNTRY) {
            $pass = true;
          }
          break;
        case 'international':
          if ($CLICSHOPPING_Order->delivery['country_id'] !== STORE_COUNTRY) {
            $pass = true;
          }
          break;
        case 'both':
          $pass = true;
          break;
      }

      $_SESSION['free_shipping'] = false;

      if (($pass === true) && ($CLICSHOPPING_Order->info['total'] >= MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)) {
        $_SESSION['free_shipping'] = true;

        $CLICSHOPPING_Language->loadDefinitions('Shop/modules/order_total/ot_shipping');
      }
    } else {

      $_SESSION['free_shipping'] = false;
    }

// if no shipping method has been selected, automatically select the first method.
// if the modules status was changed when none were available, to save on implementing
// a javascript force-selection method, also automatically select the first shipping
// method if more than one module is now enabled
    if (!isset($_SESSION['shipping']) || (isset($_SESSION['shipping']) && ($_SESSION['shipping'] === false) && ($CLICSHOPPING_Shipping->geCountShippingModules() > 1))) $_SESSION['shipping'] = $CLICSHOPPING_Shipping->getCheapest();
    if (defined('SHIPPING_ALLOW_UNDEFINED_ZONES') && (SHIPPING_ALLOW_UNDEFINED_ZONES == 'False') && !$CLICSHOPPING_Customer->isLoggedOn() && ($_SESSION['shipping'] === false)) {
      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_no_shipping_available_to_shipping_address'), 'error');

      CLICSHOPPING::redirect(null, 'Checkout&ShippingAddress');
    }

// templates
    $this->page->setFile('checkout_shipping.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('checkout_shipping');
//language
    $CLICSHOPPING_Language->loadDefinitions('checkout_shipping');

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title_1'), CLICSHOPPING::link(null, 'Cart'));
    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title_2'), CLICSHOPPING::link(null, 'Checkout&Shipping'));
  }
}
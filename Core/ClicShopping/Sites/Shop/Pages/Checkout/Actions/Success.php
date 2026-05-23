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
use ClicShopping\Sites\Shop\Pages\Checkout\Classes\CheckoutSuccess;
use function is_array;

class Success extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Db = Registry::get('Db');

// if the customer is not logged on, redirect them to the shopping cart page
    if (!$CLICSHOPPING_Customer->isLoggedOn()) {
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

//verify the order is make
    CheckoutSuccess::getCheckoutSuccessOrderCheck();

    if (isset($_GET['Checkout']) && isset($_GET['Success']) && (isset($_GET['action']) && $_GET['action'] == 'update')) {
      $QglobalNotifications = $CLICSHOPPING_Db->prepare('select global_product_notifications
                                                           from :table_customers_info
                                                           where customers_info_id = :customers_info_id
                                                          ');
      $QglobalNotifications->bindInt(':customers_info_id', $CLICSHOPPING_Customer->getID());
      $QglobalNotifications->execute();

      if ($QglobalNotifications->valueInt('global_product_notifications') != 1) {
        if (isset($_POST['notify']) && is_array($_POST['notify']) && !empty($_POST['notify'])) {
          $notify = array_unique($_POST['notify']);

          foreach ($notify as $n) {
            if (is_numeric($n) && ($n > 0)) {
              $Qcheck = $CLICSHOPPING_Db->get('products_notifications', 'products_id', ['products_id' => (int)$n,
                'customers_id' => (int)$CLICSHOPPING_Customer->getID()
              ],
                null,
                1
              );

              if ($Qcheck->fetch() === false) {
                $CLICSHOPPING_Db->save('products_notifications', ['products_id' => (int)$n,
                    'customers_id' => (int)$CLICSHOPPING_Customer->getID(),
                    'date_added' => 'now()'
                  ]
                );
              }
            }
          }
        } else {
          $notify = array_unique($_POST['products_id']);
          foreach ($notify as $n) {
            if (is_numeric($n) && ($n > 0)) {
              $Qdelete = $CLICSHOPPING_Db->prepare('delete from :table_products_notifications
                                                       where products_id = :products_id
                                                      ');
              $Qdelete->bindint(':products_id', $n);
              $Qdelete->execute();
            }
          }
        }
      }

      CLICSHOPPING::redirect(null, 'Checkout&Success');
    }

// templates
    $this->page->setFile('checkout_success.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('checkout_success');
//language
    $CLICSHOPPING_Language->loadDefinitions('checkout_success');

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title_1'));
    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title_2'));
  }
}

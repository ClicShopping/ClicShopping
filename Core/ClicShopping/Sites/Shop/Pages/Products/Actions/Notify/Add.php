<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Products\Actions\Notify;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use function count;
use function is_array;

class Add extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if ($CLICSHOPPING_Customer->isLoggedOn()) {
      if ($CLICSHOPPING_ProductsCommon->getID()) {
        $notify = (int)$CLICSHOPPING_ProductsCommon->getID();
      } elseif (isset($_GET['notify'])) {
        $notify = $_GET['notify'];
      } elseif (isset($_POST['notify'])) {
        $notify = $_POST['notify'];
      } else {
        CLICSHOPPING::redirect(null, 'Products&Description&products_id=' . (int)$CLICSHOPPING_ProductsCommon->getID());
      }

      if (!is_array($notify)) $notify = array($notify);

      for ($i = 0, $n = count($notify); $i < $n; $i++) {

        $Qcheck = $CLICSHOPPING_Db->get('products_notifications', 'products_id',
          ['customers_id' => (int)$CLICSHOPPING_Customer->getID(),
           'products_id' => (int)$notify[$i]
          ]
        );

        if ($Qcheck->fetch() === false) {
          $array = [
            'products_id' => (int)$notify[$i],
            'customers_id' => (int)$CLICSHOPPING_Customer->getID(),
            'date_added' => 'now()'
          ];

          $CLICSHOPPING_Db->save('products_notifications', $array);
        }
      }

      $CLICSHOPPING_Hooks->call('Products', 'Add');

      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_notifications_updated'), 'success');

      CLICSHOPPING::redirect(null, 'Products&Description&products_id=' . (int)$CLICSHOPPING_ProductsCommon->getID());

    } else {
      $CLICSHOPPING_NavigationHistory->setSnapshot();
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }
  }
}

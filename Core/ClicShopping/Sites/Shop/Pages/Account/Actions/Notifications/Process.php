<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\Notifications;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use function count;
use function in_array;
use function is_array;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $Qglobal = $CLICSHOPPING_Db->prepare('select global_product_notifications
                                             from :table_customers_info
                                             where customers_info_id = :customers_info_id
                                            ');
    $Qglobal->bindInt(':customers_info_id', $CLICSHOPPING_Customer->getID());
    $Qglobal->execute();

    $global = $Qglobal->fetch();

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      if (isset($_POST['product_global']) && is_numeric($_POST['product_global']) && in_array($_POST['product_global'], ['0', '1'])) {
        $product_global = (int)$_POST['product_global'];
      } else {
        $product_global = 0;
      }

      $products = isset($_POST['products']) && is_array($_POST['products']) ? $_POST['products'] : [];

      if ($product_global != $global['global_product_notifications']) {
        $product_global = (($global['global_product_notifications'] == '1') ? '0' : '1');

        $Qupdate = $CLICSHOPPING_Db->prepare('update :table_customers_info
                                          set global_product_notifications = :global_product_notifications
                                          where customers_info_id = :customers_info_id
                                         ');
        $Qupdate->bindInt(':global_product_notifications', (int)$product_global);
        $Qupdate->bindInt(':customers_info_id', $CLICSHOPPING_Customer->getID());
        $Qupdate->execute();

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_notifications_updated'), 'success', 'notification');

      } elseif (count($products) > 0) {
        $products_parsed = [];

        foreach ($products as $value) {
          if (is_numeric($value) && !in_array($value, $products_parsed)) {
            $products_parsed[] = $value;
          }
        }

        if (count($products_parsed) > 0) {
          $Qcheck = $CLICSHOPPING_Db->prepare('select products_id
                                                 from :table_products_notifications
                                                 where customers_id = :customers_id
                                                 and products_id not in (:products_id)
                                                 limit 1
                                                 ');

          $Qcheck->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());

          foreach ($products_parsed as $k => $v) {
            $Qcheck->bindInt(':products_id', $v);
          }

          $Qcheck->execute();

          if ($Qcheck->fetch() !== false) {
            $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                                    from :table_products_notifications
                                                    where customers_id = :customers_id
                                                    and products_id not in (:products_id)
                                                    ');
            $Qdelete->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());

            foreach ($products_parsed as $k => $v) {
              $Qdelete->bindInt(':products_id_' . $k, $v);
            }

            $Qdelete->execute();
          }

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_notifications_updated'), 'success', 'notification');
        }
      } else {

        $Qcheck = $CLICSHOPPING_Db->prepare('select customers_id
                                               from :table_products_notifications
                                               where customers_id = :customers_id
                                               limit 1
                                              ');
        $Qcheck->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
        $Qcheck->execute();

        if ($Qcheck->fetch() !== false) {
          $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                            from :table_products_notifications
                                            where customers_id = :customers_id
                                          ');
          $Qdelete->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
          $Qdelete->execute();
        }

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_notifications_updated'), 'success', 'notification');
      }

      $CLICSHOPPING_Hooks->call('Notifications', 'Process');

      CLICSHOPPING::redirect(null, 'Account&Notifications');
    }
  }
}
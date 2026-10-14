<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Classes;

use ClicShopping\OM\Registry;
use function is_null;

class Notifications
{
  /**
   * Retrieves the global product notification status for a customer.
   *
   * @return int Returns 1 if the customer has enabled global product notifications, otherwise 0.
   */
  public static function getGlobalNotificationCustomer(): int
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qnotification = $CLICSHOPPING_Db->prepare('select global_product_notifications
                                                  from :table_customers_info
                                                  where customers_info_id = :customers_info_id
                                                ');
    $Qnotification->bindInt(':customers_info_id', $CLICSHOPPING_Customer->getID());
    $Qnotification->execute();

    return $Qnotification->valueInt('global_product_notifications');
  }

  /**
   * Performs a database query to count the rows in the products notifications table
   * for the currently logged-in customer.
   *
   * @return int The number of rows in the products notifications table for the current customer.
   */
  public static function getGlobalProductNotificationsCheckRowCount(): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $QCheck = $CLICSHOPPING_Db->prepare('select count(*)
                                          from :table_products_notifications
                                          where customers_id = :customers_id
                                         ');
    $QCheck->bindInt(':customers_id', (int)$CLICSHOPPING_Customer->getID());
    $QCheck->execute();

    $number = $QCheck->rowCount();

    return $number;
  }

  /**
   * Retrieves the product name for a specified product ID if the customer has subscribed to notifications for that product.
   *
   * @param int|null $products_id The ID of the product to retrieve the notification for. If null, no product name will be retrieved.
   * @return string|null Returns the product name if found, or null if no product is found or $products_id is null.
   */
  public static function getGlobalProductNotificationsProduct($products_id = null)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!is_null($products_id)) {
      $Qproducts = $CLICSHOPPING_Db->prepare('select pd.products_id,
                                                      pd.products_name
                                                from :table_products_description pd,
                                                     :table_products_notifications pn
                                                where pn.customers_id = :customers_id
                                                and pn.products_id = pd.products_id
                                                and pd.language_id = :language_id
                                                and pn.products_id = :products_id
                                                order by pd.products_name
                                                ');
      $Qproducts->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qproducts->bindInt(':products_id', (int)$products_id);
      $Qproducts->execute();

      $products_name = $Qproducts->value('products_name');

      return $products_name;
    }
  }
}
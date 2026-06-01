<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Classes\Shop;

use ClicShopping\OM\Registry;

class HistoryInfo
{

  /**
   * Retrieves the history information listing for return orders based on the specified parameters.
   *
   * @param bool $fetch Determines whether to fetch a single record (true) or all records (false).
   * @param int|null $rId Optional return order ID to filter the results. If null, all return orders are fetched for the customer.
   * @return array The fetched return order information, either a single record or multiple records based on the $fetch parameter.
   */
  public static function getHistoryInfoListing(bool $fetch = true,  int|null $rId = null)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (!is_null($rId)) {
      $sql = 'and return_id = :return_id';
    } else {
      $sql = '';
    }

    $QordersInfo = $CLICSHOPPING_Db->prepare('select return_id,
                                                     return_ref,    
                                                     product_name,
                                                     product_id,
                                                     product_model,
                                                     quantity,
                                                     order_id,
                                                     date_added,
                                                     opened
                                          from :table_return_orders
                                          where customer_id = :customer_id
                                          ' . $sql . '
                                         ');

    $QordersInfo->bindInt(':customer_id', $CLICSHOPPING_Customer->getID());

    if (!is_null($rId)) {
      $QordersInfo->bindInt(':return_id', $rId);
    }

    $QordersInfo->execute();

    if ($fetch === true) {
      $result = $QordersInfo->fetch();
    } else {
      $result = $QordersInfo->fetchAll();
    }

    return $result;
  }

  /**
   * Retrieves the history information for a specific return order, including status name, date added, and comments.
   *
   * @param int $id The unique identifier of the return order.
   * @return array An array containing the history information of the return order.
   */
  public static function getHistoryInfoDisplay(int $id): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qstatuse = $CLICSHOPPING_Db->prepare('select distinct os.name,
                                                           osh.date_added,
                                                           osh.comment                                                           
                                            from :table_return_orders_status os,
                                                 :table_return_orders_history osh
                                            where osh.return_id = :return_id
                                            and osh.return_status_id = os.return_status_id
                                            and os.language_id = :language_id
                                            and osh.notify = 1
                                            order by osh.date_added
                                            ');
    $Qstatuse->bindInt(':return_id', $id);
    $Qstatuse->bindInt(':language_id', $CLICSHOPPING_Language->getId());

    $Qstatuse->execute();

    $result = $Qstatuse->fetchAll();

    return $result;
  }


  /**
   * Retrieves the history information check of a specific order ID.
   *
   * @param int $id The ID of the order to check.
   * @return array|false Returns an associative array containing the order ID and customer ID if found, or false if no records are found.
   */
  public static function getHistoryInfoCheck(int $id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->prepare('select order_id,
                                                customer_id
                                         from :table_return_orders
                                         where order_id = :order_id
                                          ');
    $Qcheck->bindInt(':order_id', $id);

    $Qcheck->execute();

    $check = $Qcheck->fetch();

    return $check;
  }

}
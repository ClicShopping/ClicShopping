<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Classes;

use ClicShopping\OM\Registry;

class History
{
  /**
   * Retrieves the order history based on the customer's ID and the current language settings.
   *
   * This method queries the database to fetch a paginated list of orders associated with the
   * currently logged-in customer. The selection includes the order ID, date of purchase,
   * delivery and billing names, order total, and order status. The results are filtered based on
   * the order status being publicly visible and matching the language ID.
   *
   * The results are ordered by the order ID in descending order, and pagination is applied
   * based on the predefined maximum number of results per page.
   *
   * @return \Statement A database statement containing the retrieved order history results.
   */
  public static function getOrderHistory()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qorders = $CLICSHOPPING_Db->prepare('select SQL_CALC_FOUND_ROWS o.orders_id,
                                                                       o.date_purchased,
                                                                       o.delivery_name,
                                                                       o.billing_name,
                                                                       ot.text as order_total,
                                                                       s.orders_status_name
                                               from :table_orders o,
                                                    :table_orders_total ot,
                                                    :table_orders_status s
                                               where o.customers_id = :customers_id
                                               and s.language_id = :language_id
                                               and (ot.class = :class or ot.class = :class1)
                                               and s.public_flag = 1
                                               and o.orders_id = ot.orders_id
                                               and o.orders_status = s.orders_status_id
                                               order by o.orders_id desc
                                               limit :page_set_offset,
                                                     :page_set_max_results
                                              ');

    $Qorders->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $Qorders->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $Qorders->bindValue(':class', 'ot_total');
    $Qorders->bindValue(':class1', 'TO');
    $Qorders->setPageSet(MAX_DISPLAY_ORDER_HISTORY);
    $Qorders->execute();

    return $Qorders;
  }

  /**
   * Retrieves the total number of rows from the order history.
   *
   * @return int The total number of rows in the order history dataset.
   */
  public static function getOrderTotalRows(): int
  {
    $orders = static::getOrderHistory();

    $ordersTotalRow = $orders->getPageSetTotalRows();

    return $ordersTotalRow;
  }
}
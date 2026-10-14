<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Checkout\Classes;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class CheckoutSuccess
{
  /**
   * Retrieves the most recent order for the currently logged-in customer.
   *
   * @return mixed Returns the query result containing the order information of the most recently purchased order by the customer.
   */
  public static function getCheckoutSuccessOrder(): mixed
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $Qorders = $CLICSHOPPING_Db->prepare('select orders_id
                                            from :table_orders
                                            where customers_id = :customers_id
                                            order by date_purchased desc
                                            limit 1
                                          ');
    $Qorders->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $Qorders->execute();

    return $Qorders;
  }

  /**
   * Checks if the last order exists for the customer after checkout.
   * If no order is found, redirects to the shopping cart page.
   *
   * @return void
   */
  public static function getCheckoutSuccessOrderCheck(): void
  {
    $Qorders = static::getCheckoutSuccessOrder();
    // redirect to shopping cart page if no orders exist
    if ($Qorders->fetch() === false) {
      CLICSHOPPING::redirect(null, 'Cart');
    }
  }

  /**
   * Retrieves the order ID of the most recent checkout success order for the current customer.
   *
   * @return int The ID of the most recent order.
   */
  public static function getCheckoutSuccessOrderId(): int
  {
    $Qorders = static::getCheckoutSuccessOrder();

    $orders = $Qorders->toArray();

    $order_id = (int)$orders['orders_id'];

    return $order_id;
  }
}
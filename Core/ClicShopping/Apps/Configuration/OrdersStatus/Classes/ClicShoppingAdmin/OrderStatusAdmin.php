<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatus\Classes\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class OrderStatusAdmin
{
  /**
   * Retrieves the name of an order status based on the provided order status ID and language ID.
   *
   * @param int $orders_status_id The unique identifier of the order status.
   * @param int $language_id The unique identifier of the language. If not provided, the default language ID will be used.
   * @return string The name of the order status corresponding to the given IDs.
   */
  public static function getOrdersStatusName(int $orders_status_id, int $language_id): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $Qstatus = $CLICSHOPPING_Db->get('orders_status', 'orders_status_name', ['orders_status_id' => (int)$orders_status_id, 'language_id' => $language_id]);

    return $Qstatus->value('orders_status_name');
  }

  /**
   * Generates a dropdown menu for order statuses.
   *
   * @param string $name The name attribute of the dropdown element. Default is 'dropdown_status'.
   * @param mixed $id The selected option ID in the dropdown menu. Default is null.
   * @param string $displays_all_orders_status Determines whether to include an option for all order statuses. Default is 'yes'.
   * @return string The generated HTML string for the dropdown menu.
   */

  public static function getDropDownOrderStatus(string $name = 'dropdown_status', mixed  $id = null, string $displays_all_orders_status = 'yes'): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $orders_statuses = [];

    $QordersStatus = $CLICSHOPPING_Db->prepare('select orders_status_id,
                                                          orders_status_name
                                                  from :table_orders_status
                                                  where language_id = :language_id
                                                  ');
    $QordersStatus->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $QordersStatus->execute();

    if (isset($displays_all_orders_status)) {
      $orders_statuses[] = ['id' => '0', 'text' => CLICSHOPPING::getDef('text_all_orders')];
    } else {
      $orders_statuses[] = ['id' => '0', 'text' => CLICSHOPPING::getDef('text_select')];
    }

    while ($QordersStatus->fetch() !== false) {
      $orders_statuses[] = [
        'id' => $QordersStatus->valueInt('orders_status_id'),
        'text' => $QordersStatus->value('orders_status_name')
      ];

    }

    $status = HTML::selectMenu($name, $orders_statuses, $id);

    return $status;
  }
}

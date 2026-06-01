<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Classes\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ReturnOrderStatus
{
  /**
   * Retrieves the name of the return order status based on a given status ID and language ID.
   *
   * @param int $return_orders_status_id The ID of the return order status.
   * @param int $language_id The ID of the language to retrieve the status name for.
   * @return string The name of the return order status in the specified language.
   */
  public static function getReturnOrdersStatusName(int $return_orders_status_id, int $language_id): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!$language_id) $language_id = $CLICSHOPPING_Language->getId();

    $Qstatus = $CLICSHOPPING_Db->get('return_orders_status', 'name', ['return_orders_status_id' => (int)$return_orders_status_id, 'language_id' => $language_id]);

    return $Qstatus->value('name');
  }

  /**
   * Generates a dropdown menu for return order status options.
   *
   * @param string $name The name attribute for the dropdown HTML element.
   * @param mixed $id The selected option ID for the dropdown (optional).
   * @param string $displays_all_orders_status Whether to display all order statuses. Default is 'yes'.
   * @return string The HTML string for the dropdown menu.
   */
  public static function getDropDownReturnOrderStatus(string $name = 'dropdown_status', ?int $id = null, string $displays_all_orders_status = 'yes'): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $orders_statuses = [];

    $QordersStatus = $CLICSHOPPING_Db->prepare('select return_orders_status_id,
                                                          name
                                                  from :table_return_orders_status
                                                  where language_id = :language_id
                                                  ');
    $QordersStatus->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $QordersStatus->execute();

    if (isset($displays_all_orders_status)) {
      $orders_statuses[] = [
        'id' => '0',
        'text' => CLICSHOPPING::getDef('text_all_orders')
      ];
    } else {
      $orders_statuses[] = [
        'id' => '0',
        'text' => CLICSHOPPING::getDef('text_select')
      ];
    }

    while ($QordersStatus->fetch() !== false) {
      $orders_statuses[] = [
        'id' => $QordersStatus->valueInt('return_orders_status_id'),
        'text' => $QordersStatus->value('name')
      ];

    }

    $status = HTML::selectMenu($name, $orders_statuses, $id);

    return $status;
  }
}

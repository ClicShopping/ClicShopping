<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Classes\ClicShoppingAdmin;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ReturnProduct
{
  /**
   * Generates a dropdown menu for selecting the reason opened for a return order.
   *
   * @param int|null $id The pre-selected option ID for the dropdown. Pass null if no pre-selection is needed.
   * @return string The HTML string for the dropdown menu.
   */
  public static function getDropDownReasonOpened( int|null $id): string
  {
    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');

    $return_opened_array = [
      ['id' => '0', 'text' => $CLICSHOPPING_ReturnOrders->getDef('text_opened')],
      ['id' => '1', 'text' => $CLICSHOPPING_ReturnOrders->getDef('text_unopened')]
    ];

    $dropdown = HTML::selectField('return_reason_opened', $return_opened_array, $id);

    return $dropdown;
  }

  /**
   * Generates a dropdown menu for return actions based on the language ID provided.
   *
   * @param int|null $id The ID of the return action to be pre-selected in the dropdown menu. Pass null for no pre-selection.
   * @return string The HTML string of the dropdown menu.
   */
  public static function getDropDownAction( int|null $id): string
  {
    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');
    $CLICSHOPPING_language = Registry::get('Language');

    $Qaction = $CLICSHOPPING_ReturnOrders->db->prepare('select return_action_id,
                                                                 language_id,
                                                                 name
                                                          from :table_return_orders_action
                                                          where language_id = :language_id
                                                          ');
    $Qaction->bindInt(':language_id', $CLICSHOPPING_language->getId());
    $Qaction->execute();

    $return_action_array = [];

    while ($Qaction->fetch()) {
      $return_action_array[] = [
        'id' => $Qaction->valueInt('return_action_id'),
        'text' => $Qaction->value('name')
      ];
    }

    $dropdown = HTML::selectField('return_action', $return_action_array, $id);

    return $dropdown;
  }

  /**
   * Generates a dropdown selection field for return order statuses.
   *
   * @param int|null $id The ID of the return status to be preselected in the dropdown. If null, no status is preselected.
   * @return string The HTML markup for the dropdown selection field populated with return order statuses.
   */
  public static function getDropDownStatus( int|null $id): string
  {
    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qstatus = $CLICSHOPPING_ReturnOrders->db->prepare('select return_status_id,
                                                                 language_id,
                                                                 name
                                                          from :table_return_orders_status
                                                          where language_id = :language_id
                                                          ');
    $Qstatus->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $Qstatus->execute();

    $return_action_array = [];

    while ($Qstatus->fetch()) {
      $return_action_array[] = [
        'id' => $Qstatus->valueInt('return_status_id'),
        'text' => $Qstatus->value('name')
      ];
    }

    $dropdown = HTML::selectField('return_status', $return_action_array, $id);

    return $dropdown;
  }
}
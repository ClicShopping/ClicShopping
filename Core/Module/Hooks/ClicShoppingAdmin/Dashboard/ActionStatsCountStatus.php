<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use function is_array;
use function is_null;

/**
 * Class ActionStatsCountStatus
 *
 * This class is responsible for querying and displaying the count of orders
 * based on their status in an administrative interface. It retrieves the
 * orders data from the database and dynamically generates the corresponding
 * HTML elements to display this information.
 */
class ActionStatsCountStatus
{

  /**
   * Initializes the class instance and ensures that the current site is ClicShoppingAdmin.
   * Redirects if the condition is not met.
   *
   * @return void
   */
  public function __construct()
  {

    if (CLICSHOPPING::getSite() != 'ClicShoppingAdmin') {
      CLICSHOPPING::redirect();
    }
  }

  /**
   * Executes the process of retrieving and displaying order statuses along with the count of orders
   * associated with each status. This function fetches order status data from the database, counts
   * the pending orders for each status, and generates a formatted output for display.
   *
   * @return void Outputs the result directly, displaying order statuses and counts if applicable.
   */
  public function execute()
  {

    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $QordersStatus = $CLICSHOPPING_Db->prepare('select orders_status_name,
                                                         orders_status_id
                                                  from :table_orders_status
                                                  where language_id = :language_id
                                                  order by orders_status_id
                                                ');
    $QordersStatus->bindint(':language_id', $CLICSHOPPING_Language->getId());
    $QordersStatus->execute();

    $result = null;

    while ($QordersStatus->fetch()) {
      $QordersPending = $CLICSHOPPING_Db->prepare('select count(orders_id) as count
                                                     from :table_orders
                                                     where orders_status = :orders_status
                                                   ');
      $QordersPending->bindInt(':orders_status', $QordersStatus->valueInt('orders_status_id'));
      $QordersPending->execute();

      if ($QordersPending->valueInt('count') > 0) {
        $result[] = '
             <div class="row">
                <div class="col-md-11 mainTable">
                  <div class="form-group row">
                    <label for="' . CLICSHOPPING::getDef($QordersStatus->value('orders_status_name')) . '" class="col-9 col-form-label"><a href="' . CLICSHOPPING::link(null, 'A&Orders\Orders&Orders', $QordersStatus->valueInt('orders_status_id')) . '">' . CLICSHOPPING::getDef($QordersStatus->value('orders_status_name')) . '</a></label>
                    <div class="col-md-3">
                      ' . $QordersPending->valueInt('count') . '
                    </div>
                  </div>
                </div>
              </div>
            ';
      }
    }

    if (!is_null($result) && is_array($result)) {
      foreach ($result as $value) {
        echo $value;
      }
    }
  }
}
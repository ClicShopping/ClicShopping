<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Report\StatsProductsNotification\Module\Hooks\ClicShoppingAdmin\StatsDashboard;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\Report\StatsProductsNotification\StatsProductsNotification as StatsProductsNotificationApp;

class PageTabContent implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the StatsProductsNotification application and loads necessary definitions.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('StatsProductsNotification')) {
      Registry::set('StatsProductsNotification', new StatsProductsNotificationApp());
    }

    $this->app = Registry::get('StatsProductsNotification');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/StatsDashboard/page_tab_content');
  }

  /**
   * Counts the total number of customer product notifications stored in the database.
   *
   * This method queries the database to retrieve the count of product notifications
   * recorded in the `products_notifications` table, with the `products_id` field being
   * used to calculate the count. It limits the result set to a single result.
   *
   * @return int The total number of product notifications.
   */
  private function statsCountCustomersNotifications()
  {
    $QcustomersTotalNotification = $this->app->db->prepare('select count(products_id) as count
                                                              from :table_products_notifications
                                                              limit 1
                                                              ');
    $QcustomersTotalNotification->execute();

    $customers_total_notification = $QcustomersTotalNotification->valueInt('count');

    return $customers_total_notification;
  }

  /**
   * Displays the customer notification statistics if the corresponding app status is enabled
   * and there are notifications available.
   *
   * @return string|false The formatted HTML content with notification details if conditions are met,
   *                      otherwise returns false.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_STATS_PRODUCTS_NOTIFICATION_PN_STATUS') || CLICSHOPPING_APP_STATS_PRODUCTS_NOTIFICATION_PN_STATUS == 'False') {
      return false;
    }

    if ($this->statsCountCustomersNotifications() != 0) {
      $content = '
        <div class="row">
          <div class="col-md-11 mainTable">
            <div class="form-group row">
              <label for="' . $this->app->getDef('box_entry_notification') . '" class="col-9 col-form-label"><a href="' . $this->app->link('StatsProductsNotification') . '">' . $this->app->getDef('box_entry_notification') . '</a></label>
              <div class="col-md-3">
                ' . $this->statsCountCustomersNotifications() . '
              </div>
            </div>
          </div>
        </div>
        ';

      $output = <<<EOD
  <!-- ######################## -->
  <!--  Start Count customer Notification      -->
  <!-- ######################## -->
             {$content}
  <!-- ######################## -->
  <!--  Start Count customer Notification      -->
  <!-- ######################## -->
EOD;
      return $output;
    }
  }
}
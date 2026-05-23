<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Module\Hooks\ClicShoppingAdmin\Cronjob;

use ClicShopping\Apps\Customers\Gdpr\Gdpr as GdprApp;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Gdpr\Classes\ClicShoppingAdmin\Gdpr;

class Process implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Gdpr application component.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Gdpr')) {
      Registry::set('Gdpr', new GdprApp());
    }

    $this->app = Registry::get('Gdpr');
  }

  /**
   * Retrieves a list of customers whose last login date is older than a specified expiration date.
   *
   * @return array|bool An array of customers matching the criteria or false if no results are found.
   */
  public function getExpires(): array|bool
  {
    $date = date('Y-m-d', strtotime('+ ' . CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_DATE . ' days'));

    $Qcustomers = $this->app->db->prepare('select  c.customers_id,
                                                     c.customers_email_address,
                                                     ci.customers_info_date_of_last_logon
                                                from :table_customers c,
                                                     :table_customers_info ci
                                                where  c.customers_id = ci.customers_info_id
                                                and customers_info_date_of_last_logon <= :date
                                              ');

    $Qcustomers->bindValue(':date', $date);
    $Qcustomers->execute();

    return $Qcustomers->fetchAll();
  }

  /**
   * Executes scheduled GDPR-related tasks, processing expired data and invoking cleanup operations.
   *
   * @return void
   */
  private function cronJob(): void
  {
    $results = $this->getExpires();

    foreach ($results as $result) {
      $cron_id_gdpr = Cron::getCronCode('gdpr');

      if (isset($_GET['cronId'])) {
        $cron_id = HTML::sanitize($_GET['cronId']);

        Cron::updateCron($cron_id);

        if (isset($cron_id) && $cron_id_gdpr == $cron_id) {
          $customer_id = $result['customers_id'];
          Gdpr::deleteCustomersData($customer_id);
        }
      } else {
        Cron::updateCron($cron_id_gdpr);

        if (isset($cron_id_gdpr)) {
          $customer_id = $result['customers_id'];
          Gdpr::deleteCustomersData($customer_id);
        }
      }
    }
  }

  /**
   * Executes the main logic by triggering the cron job functionality.
   *
   * @return void
   */
  public function execute()
  {
    $this->cronJob();
  }
}
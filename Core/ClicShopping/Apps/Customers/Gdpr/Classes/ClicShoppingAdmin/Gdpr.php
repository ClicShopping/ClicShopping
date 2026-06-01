<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Classes\ClicShoppingAdmin;

use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
/**
 * Class Gdpr
 *
 * Provides methods for handling GDPR-related operations, including the deletion of customer data from multiple tables.
 */
class Gdpr
{
  /**
   * Returns the customers whose last logon is older than the configured retention
   * period (CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_DATE days), i.e. the ones to purge.
   *
   * @return array The expired customers (id, email, last logon).
   */
  public static function getExpiredCustomers(): array
  {
    $CLICSHOPPING_Gdpr = Registry::get('Gdpr');

    // Retention cutoff in the PAST. A "+" here would be a future date and would
    // match — and delete — every customer.
    $date = date('Y-m-d', strtotime('- ' . CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_DATE . ' days'));

    $Qcustomers = $CLICSHOPPING_Gdpr->db->prepare('select c.customers_id,
                                                          c.customers_email_address,
                                                          ci.customers_info_date_of_last_logon
                                                   from :table_customers c,
                                                        :table_customers_info ci
                                                   where c.customers_id = ci.customers_info_id
                                                   and ci.customers_info_date_of_last_logon <= :date
                                                  ');
    $Qcustomers->bindValue(':date', $date);
    $Qcustomers->execute();

    return $Qcustomers->fetchAll();
  }

  /**
   * Deletes every expired customer's personal data.
   *
   * @return int The number of customers purged.
   */
  public static function purgeExpired(): int
  {
    $count = 0;

    foreach (self::getExpiredCustomers() as $result) {
      self::deleteCustomersData((int)$result['customers_id']);
      $count++;
    }

    return $count;
  }

  /**
   * Cron entry point shared by the admin (manual launch) and the Shop (external URL)
   * cronjob hooks. Validates the cron code, records the run, then purges expired data.
   *
   * @return void
   */
  public static function runCron(): void
  {
    $cron_id_gdpr = Cron::getCronCode('gdpr');

    if (isset($_GET['cronId'])) {
      $cron_id = HTML::sanitize($_GET['cronId']);
      Cron::updateCron($cron_id);

      // Only the GDPR cron is allowed to purge.
      if ($cron_id_gdpr != $cron_id) {
        return;
      }
    } else {
      Cron::updateCron($cron_id_gdpr);
    }

    self::purgeExpired();
  }

  /**
   * Deletes all related data associated with a specific customer from several database tables.
   *
   * @param int $customers_id ID of the customer to delete data for.
   * @return void
   */
  public static function deleteCustomersData(int $customers_id): void
  {
    $CLICSHOPPING_Gdpr = Registry::get('Gdpr');

    $Qreviews = $CLICSHOPPING_Gdpr->db->prepare('select reviews_id
                                                       from :table_reviews
                                                       where customers_id = :customers_id
                                                       ');
    $Qreviews->bindInt(':customers_id', $customers_id);
    $Qreviews->execute();

    while ($Qreviews->fetch()) {
      $Qdelete = $CLICSHOPPING_Gdpr->db->prepare('delete
                                                         from :table_reviews_description
                                                         where reviews_id = :reviews_id
                                                        ');
      $Qdelete->bindInt(':reviews_id', $Qreviews->valueInt('reviews_id'));
      $Qdelete->execute();
    }

    $Qdelete = $CLICSHOPPING_Gdpr->db->prepare('delete
                                                      from :table_reviews
                                                      where customers_id = :customers_id
                                                    ');
    $Qdelete->bindInt(':customers_id', $customers_id);
    $Qdelete->execute();

    $Qdelete = $CLICSHOPPING_Gdpr->db->prepare('delete
                                                      from :table_address_book
                                                      where customers_id = :customers_id
                                                    ');
    $Qdelete->bindInt(':customers_id', $customers_id);
    $Qdelete->execute();

    $Qdelete = $CLICSHOPPING_Gdpr->db->prepare('delete
                                                        from :table_customers
                                                        where customers_id = :customers_id
                                                      ');
    $Qdelete->bindInt(':customers_id', $customers_id);
    $Qdelete->execute();

    $Qdelete = $CLICSHOPPING_Gdpr->db->prepare('delete
                                                          from :table_customers_info
                                                          where customers_info_id = :customers_id
                                                        ');
    $Qdelete->bindInt(':customers_id', $customers_id);
    $Qdelete->execute();

    $Qdelete = $CLICSHOPPING_Gdpr->db->prepare('delete
                                                          from :table_customers_basket
                                                          where customers_id = :customers_id
                                                        ');
    $Qdelete->bindInt(':customers_id', $customers_id);
    $Qdelete->execute();

    $Qdelete = $CLICSHOPPING_Gdpr->db->prepare('delete
                                                          from :table_customers_basket_attributes
                                                          where customers_id = :customers_id
                                                        ');
    $Qdelete->bindInt(':customers_id', $customers_id);
    $Qdelete->execute();

    $Qdelete = $CLICSHOPPING_Gdpr->db->prepare('delete
                                                        from :table_whos_online
                                                        where customer_id = :customers_id
                                                      ');
    $Qdelete->bindInt(':customers_id', $customers_id);
    $Qdelete->execute();
  }
}
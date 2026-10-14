<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Classes\ClicShoppingAdmin;

use ClicShopping\Apps\Customers\Customers\Classes\Shared\CustomerDataEraser;
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
   * A customer who never logged on is dated by the account creation.
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
                                                   and coalesce(ci.customers_info_date_of_last_logon, ci.customers_info_date_account_created) <= :date
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
   * cronjob hooks. Validates the cron code, records the run, then purges expired data
   * and anonymises the personal data still kept on orders past the legal retention.
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
    self::anonymizeExpiredOrders();
  }

  /**
   * Anonymises the personal data snapshots kept on orders once they pass the legal
   * accounting retention period (CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_ORDERS_DATE days,
   * default 10 years). Orders themselves are NOT deleted — only the PII columns are
   * overwritten — so amounts, dates and product lines stay available for accounting.
   *
   * This is a separate, longer clock than the inactivity purge (GD_DATE): an order
   * must be kept intact for the whole fiscal retention, then only the identity is
   * scrubbed. Anonymisation is done per-row (column overwrite), never by touching the
   * encryption key, which is shared and still required for AI and data restitution.
   *
   * @return int The number of orders anonymised.
   */
  public static function anonymizeExpiredOrders(): int
  {
    $CLICSHOPPING_Gdpr = Registry::get('Gdpr');

    $retention_days = (defined('CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_ORDERS_DATE') && (int)CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_ORDERS_DATE > 0)
      ? (int)CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_ORDERS_DATE
      : 3650;

    // Cutoff in the PAST. A "+" here would target future orders and scrub everything.
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . $retention_days . ' days'));

    $Qanonymize = $CLICSHOPPING_Gdpr->db->prepare("update :table_orders
                                                      set customers_name = :label,
                                                          customers_company = '',
                                                          customers_street_address = '',
                                                          customers_suburb = '',
                                                          customers_postcode = '',
                                                          customers_city = '',
                                                          customers_state = '',
                                                          customers_country = '',
                                                          customers_telephone = '',
                                                          customers_email_address = '',
                                                          delivery_name = :label,
                                                          delivery_company = '',
                                                          delivery_street_address = '',
                                                          delivery_suburb = '',
                                                          delivery_postcode = '',
                                                          delivery_city = '',
                                                          delivery_state = '',
                                                          delivery_country = '',
                                                          billing_name = :label,
                                                          billing_company = '',
                                                          billing_street_address = '',
                                                          billing_suburb = '',
                                                          billing_postcode = '',
                                                          billing_city = '',
                                                          billing_state = '',
                                                          billing_country = ''
                                                      where date_purchased <= :cutoff
                                                        and customers_name <> :label
                                                    ");
    $Qanonymize->bindValue(':cutoff', $cutoff);
    $Qanonymize->bindValue(':label', CustomerDataEraser::anonymousName());
    $Qanonymize->execute();

    return $Qanonymize->rowCount();
  }

  /**
   * Erases a customer's personal data through the single erasure point.
   *
   * @param int $customers_id ID of the customer to delete data for.
   * @param bool $delete_reviews True to delete the reviews; anonymised otherwise.
   * @return void
   */
  public static function deleteCustomersData(int $customers_id, bool $delete_reviews = false): void
  {
    CustomerDataEraser::erase($customers_id, $delete_reviews);
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Classes\Shared;

use ClicShopping\Apps\Customers\Customers\Customers as CustomersApp;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Single point that erases a customer's personal data — Shop account deletion, admin deletions
 * and the GDPR cron all go through it, so there is one list.
 *
 * Kept on purpose (accounting): orders and what hangs on an order (`orders_*`, `return_orders`,
 * `order_customer_payment_action`, `orders_pages_manager`, support tickets linked to an order).
 * Reviews are anonymised (row and text kept) unless the customer asked for their deletion.
 */
class CustomerDataEraser
{
  /** Tables whose rows belong to the customer, by id column. */
  private const array BY_CUSTOMER_ID = [
    'customers_basket' => 'customers_id',
    'customers_basket_attributes' => 'customers_id',
    'customers_gdpr' => 'customers_id',
    'customers_notes' => 'customers_id',
    'contact_customers' => 'customer_id',
    'discount_coupons_to_customers' => 'customers_id',
    'feedback_order_reviews' => 'customers_id',
    'products_notifications' => 'customers_id',
    'products_recommendations' => 'customers_id',
    'whos_online' => 'customer_id',
  ];

  /** Tables keyed on the e-mail address; action_recorder.user_id mixes customers and admins, so never by id. */
  private const array BY_EMAIL = [
    'action_recorder' => 'user_name',
    'newsletters_customers_temp' => 'customers_email_address',
    'newsletters_no_account' => 'customers_email_address',
  ];

  /** @var array<string, bool> */
  private static array $tables = [];

  /**
   * Erases every personal record of a customer, then the account itself.
   *
   * @param int $customers_id Customer id
   * @param bool $delete_reviews True when the customer asked to delete the reviews; anonymised otherwise
   * @return void
   */
  public static function erase(int $customers_id, bool $delete_reviews = false): void
  {
    $db = Registry::get('Db');

    $Qcustomer = $db->get('customers', 'customers_email_address', ['customers_id' => $customers_id]);
    $email = $Qcustomer->fetch() !== false ? (string)$Qcustomer->value('customers_email_address') : '';

    if ($delete_reviews) {
      $Qreviews = $db->get('reviews', 'reviews_id', ['customers_id' => $customers_id]);

      while ($Qreviews->fetch()) {
        $db->delete('reviews_description', ['reviews_id' => $Qreviews->valueInt('reviews_id')]);
      }

      $db->delete('reviews', ['customers_id' => $customers_id]);
    } else {
      $db->save('reviews', [
        'customers_id' => 'null',
        'customers_name' => self::anonymousName(),
        'customers_tag' => 'null',
      ], ['customers_id' => $customers_id]);
    }

    // The vote count stays; 0 is "nobody" (customer_id is NOT NULL).
    $db->save('reviews_vote', ['customer_id' => 0], ['customer_id' => $customers_id]);

    foreach (self::BY_CUSTOMER_ID as $table => $column) {
      if (self::exists($table)) {
        $db->delete($table, [$column => $customers_id]);
      }
    }

    if (self::exists('products_cockpit_ai_tracking_impressions')) {
      $db->save('products_cockpit_ai_tracking_impressions', ['customer_id' => 'null'], ['customer_id' => $customers_id]);
    }

    // A ticket linked to an order stays with the order; the others go.
    if (self::exists('customers_support')) {
      $Qsupport = $db->prepare('delete from :table_customers_support
                                where customer_id = :customers_id
                                and (orders_id is null or orders_id = 0)');
      $Qsupport->bindInt(':customers_id', $customers_id);
      $Qsupport->execute();
    }

    if ($email !== '') {
      foreach (self::BY_EMAIL as $table => $column) {
        if (self::exists($table)) {
          $db->delete($table, [$column => $email]);
        }
      }
    }

    $db->delete('address_book', ['customers_id' => $customers_id]);
    $db->delete('customers_info', ['customers_info_id' => $customers_id]);
    $db->delete('customers', ['customers_id' => $customers_id]);
  }

  /**
   * Name written on an anonymised review, in the language of the running site.
   *
   * @return string
   */
  public static function anonymousName(): string
  {
    if (!Registry::exists('Customers')) {
      Registry::set('Customers', new CustomersApp());
    }

    $app = Registry::get('Customers');
    $app->loadDefinitions('Shared/customer_data_eraser');

    return $app->getDef('text_anonymous_customer');
  }

  /**
   * Optional Apps own some of these tables: an absent one is skipped, never an error.
   *
   * @param string $table Table name without prefix
   * @return bool
   */
  private static function exists(string $table): bool
  {
    if (!isset(self::$tables[$table])) {
      $Qcheck = Registry::get('Db')->prepare('show tables like :name');
      $Qcheck->bindValue(':name', CLICSHOPPING::getConfig('db_table_prefix') . $table);
      $Qcheck->execute();

      self::$tables[$table] = $Qcheck->fetch() !== false;
    }

    return self::$tables[$table];
  }
}

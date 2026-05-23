<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Api;

use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiSecurity;

class ApiGetOrder
{
  /**
   * Retrieves order details based on the provided order ID and returns the associated customer, delivery, billing,
   * payment, order, and currency information.
   *
   * @param int|string $id The ID of the order for which details are to be retrieved. It can be numeric or a string.
   *
   * @return array An associative array containing the following:
   * - Customer details (e.g., customer ID, company, name, address, phone, etc.).
   * - Delivery information (e.g., delivery address, delivery company, etc.).
   * - Billing information (e.g., billing address, billing name, etc.).
   * - Order details (e.g., order status, date purchased, etc.).
   * - Currency and payment details (e.g., currency, currency value, payment method, etc.).
   * - Additional metadata (e.g., client IP, cellular phone, etc.).
   */
  private static function getOrder(int|string $id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (is_numeric($id)) {
      $sql_request = ' and o.orders_id = :orders_id';
    } else {
      $sql_request = '';
    }
    $Qapi = $CLICSHOPPING_Db->prepare('select c.*,
                                                o.*,
                                                ot.*
                                        from :table_customers c,
                                             :table_orders o,
                                             :table_orders_total ot
                                        where c.customers_id = o.customers_id
                                        and o.orders_id = ot.orders_id
                                        ' . $sql_request . '
                                        limit 1
                                        ');


    if (is_numeric($id)) {
      $Qapi->bindInt(':orders_id', $id);
    }

    $Qapi->execute();

    $customer_data = [];

    $result = $Qapi->fetchAll();

    foreach ($result as $value) {
      $customer_data[] = [
        'orders_id' => $value['orders_id'],
        'customers_id' => $value['customers_id'],
        'customers_company' => $value['customers_company'],
        'customers_siret' => $value['customers_siret'],
        'customers_ape' => $value['customers_ape'],
        'customers_tva_intracom' => $value['customers_tva_intracom'],
        'customers_gender' => $value['customers_gender'],
        'customers_firstname' => Hash::displayDecryptedDataText( $value['customers_firstname']),
        'customers_lastname' => Hash::displayDecryptedDataText($value['customers_lastname']),
        'customers_email_address' => $value['customers_email_address'],
        'customers_default_address_id' => $value['customers_default_address_id'],
        'customers_telephone' => Hash::displayDecryptedDataText($value['customers_telephone']),
        'customers_street_address' => Hash::displayDecryptedDataText($value['customers_street_address']),
        'customers_suburb' => Hash::displayDecryptedDataText($value['customers_suburb']),
        'customers_city' =>  Hash::displayDecryptedDataText($value['customers_city']),
        'customers_postcode' => Hash::displayDecryptedDataText($value['customers_postcode']),
        'customers_state' => $value['customers_state'],
        'customers_country' => $value['customers_country'],
//delivery
        'delivery_name' => Hash::displayDecryptedDataText($value['delivery_name']),
        'delivery_company' => $value['delivery_company'],
        'delivery_street_address' => Hash::displayDecryptedDataText($value['delivery_street_address']),
        'delivery_suburb' => Hash::displayDecryptedDataText($value['delivery_suburb']),
        'delivery_city' => Hash::displayDecryptedDataText($value['delivery_city']),
        'delivery_postcode' => Hash::displayDecryptedDataText($value['delivery_postcode']),
        'delivery_state' => $value['delivery_state'],
        'delivery_country' => $value['delivery_country'],
//payment
        'billing_name' => Hash::displayDecryptedDataText($value['billing_name']),
        'billing_company' => $value['billing_company'],
        'billing_street_address' => Hash::displayDecryptedDataText($value['billing_street_address']),
        'billing_suburb' => Hash::displayDecryptedDataText($value['billing_suburb']),
        'billing_city' => Hash::displayDecryptedDataText($value['billing_city']),
        'billing_postcode' => Hash::displayDecryptedDataText($value['billing_postcode']),
        'billing_state' => $value['billing_state'],
        'billing_country' => $value['billing_country'],
        'payment_method' => $value['payment_method'],
//order
        'date_modifed' => $value['date_modifed'],
        'date_purchased' => $value['date_purchased'],
        'orders_status' => $value['orders_status'],
        'orders_status_invoice' => $value['orders_status_invoice'],
        'orders_date_finished' => $value['orders_date_finished'],
//currency
        'currency' => $value['currency'],
        'currency_value' => $value['currency_value'],
        'client_computer_ip' => $value['client_computer_ip'],
        'provider_name_client' => $value['provider_name_client'],
        'customers_cellular_phone' => Hash::displayDecryptedDataText($value['customers_cellular_phone']),
//totalOrders
        'title' => $value['title'],
        'text' => str_replace('&nbsp;', '', $value['text']),
        'value' => $value['value'],
      ];
    }

    return $customer_data;
  }

  /**
   * Executes the request to validate and retrieve order details based on provided parameters.
   *
   * @return string|false Returns the order details as a JSON-encoded string if the request is valid,
   *                      returns false if required parameters are not provided, or an error JSON string
   *                      if the ID format is invalid.
   */
  public function execute()
  {
    if (isset($_GET['oId'], $_GET['token'])) {

      if (ApiSecurity::isLocalEnvironment()) {
        ApiSecurity::logSecurityEvent('Local environment detected', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
      }

      if (!isset($_GET['token'])) {
        ApiSecurity::logSecurityEvent('Missing token in order request');
        return false;
      }

      // Check if the token is valid
      $token = ApiSecurity::checkToken($_GET['token']);
      if (!$token) {
        return false;
      }

      // Rate limiting
      $clientIp = HTTP::getIpAddress();
      if (!ApiSecurity::checkRateLimit($clientIp, 'get_order')) {
        return false;
      }

      $id = HTML::sanitize($_GET['oId']);
      ApiSecurity::secureGetId($id);

      return self::getOrder($id);
    } else {
      return false;
    }
  }
}

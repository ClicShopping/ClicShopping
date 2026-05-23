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

class ApiGetCustomer
{
  /**
   * Retrieves customer data based on the provided customer ID.
   *
   * This method fetches customer details from the database, including personal,
   * address, and contact information, by either numeric ID or other identifier.
   *
   * @param int|string $id The customer ID which can be numeric or a string identifier.
   * @return array An array of customer data, including fields such as customers_id,
   *               customers_firstname, customers_lastname, and address details.
   */
  private static function getCustomer(int|string $id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (is_numeric($id)) {
      $sql_request = ' and c.customers_id = :customers_id';
    } else {
      $sql_request = '';
    }

    $Qapi = $CLICSHOPPING_Db->prepare('select c.*,
                                              a.*
                                    from :table_customers c
                                    left join :table_address_book a on c.customers_default_address_id = a.address_book_id
                                    where 1 = 1
                                    ' . $sql_request . '
                                    limit 100
                                    ');
    if (is_numeric($id)) {
      $Qapi->bindInt(':customers_id', $id);
    }

    $Qapi->execute();

    $customer_data = [];

    $result = $Qapi->fetchAll();

    foreach ($result as $value) {
      $customer_data[] = [
        'customers_id' => $value['customers_id'],
        'customers_company' => $value['customers_company'],
        'customers_gender' => $value['customers_gender'],
        'customers_firstname' => Hash::displayDecryptedDataText($value['customers_firstname']),
        'customers_lastname' => Hash::displayDecryptedDataText($value['customers_lastname']),
        'customers_dob' => $value['customers_dob'],
        'customers_email_address' => $value['customers_email_address'],
        'customers_default_address_id' => $value['customers_default_address_id'],
        'customers_telephone' => $value['customers_telephone'],
        'customers_newsletter' => $value['customers_newsletter'],
        'languages_id' => $value['languages_id'],
        'entry_street_address' => $value['entry_street_address'],
        'entry_suburb' => $value['entry_suburb'],
        'entry_postcode' => $value['entry_postcode'],
        'entry_city' => $value['entry_city'],
        'entry_state' => $value['entry_state'],
        'entry_country_id' => $value['entry_country_id'],
        'entry_zone_id' => $value['entry_zone_id'],
      ];
    }

    return $customer_data;
  }

  /**
   * Executes the process of validating and retrieving customer information based on provided parameters.
   *
   * @return string|array|false Returns customer data as an array if successful, a JSON-encoded string with an error if the ID format is invalid, or false if required parameters are missing.
   */
  public function execute()
  {
    if (isset($_GET['cId'], $_GET['token'])) {
      if (ApiSecurity::isLocalEnvironment()) {
        ApiSecurity::logSecurityEvent('Local environment detected', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
      }

      if (!isset($_GET['token'])) {
        ApiSecurity::logSecurityEvent('Missing token in customer request');
        return false;
      }

      // Check if the token is valid
      $token = ApiSecurity::checkToken($_GET['token']);
      if (!$token) {
        return false;
      }

      // Rate limiting
      $clientIp = HTTP::getIpAddress();
      if (!ApiSecurity::checkRateLimit($clientIp, 'get_customer')) {
        return false;
      }

      $id = HTML::sanitize($_GET['cId']);
      ApiSecurity::secureGetId($id);

      return self::getCustomer($id);
    } else {
      return false;
    }
  }
}

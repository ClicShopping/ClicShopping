<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Api;

use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiSecurity;

class ApiGetSupplier
{
  /**
   * Retrieves supplier data based on the provided supplier ID and language ID.
   *
   * @param int|string $id The supplier ID. This can be an integer or a string.
   * @param int|string $language_id The language ID. This can be an integer or a string.
   * @return array An array of supplier information, including supplier details such as name, contact information, and status.
   */
  private static function suppliers(mixed $id = null, mixed $language_id = null): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $sql = 'SELECT s.*, 
                  si.*
          FROM :table_suppliers s
          JOIN :table_suppliers_info si ON s.suppliers_id = si.suppliers_id
          WHERE 1
          limit 100
          ';

    $params = [];

    if ($id !== null && $id !== 'All' && is_numeric($id)) {
      $sql .= ' AND s.suppliers_id = :suppliers_id';
      $params[':suppliers_id'] = (int)$id;
    }

    if ($language_id !== null && is_numeric($language_id)) {
      $sql .= ' AND si.languages_id = :language_id';
      $params[':language_id'] = (int)$language_id;
    }

    $Qapi = $CLICSHOPPING_Db->prepare($sql);

    foreach ($params as $key => $value) {
      $Qapi->bindInt($key, $value);
    }

    $Qapi->execute();

    $suppliers_data = [];

    foreach ($Qapi->fetchAll() as $row) {
      $suppliers_data[] = [
        'suppliers_id' => $row['suppliers_id'],
        'languages_id' => $row['languages_id'],
        'suppliers_name' => $row['suppliers_name'],
        'date_added' => $row['date_added'],
        'last_modified' => $row['last_modified'],
        'suppliers_manager' => $row['suppliers_manager'],
        'suppliers_phone' => $row['suppliers_phone'],
        'suppliers_email_address' => $row['suppliers_email_address'],
        'suppliers_fax' => $row['suppliers_fax'],
        'suppliers_address' => $row['suppliers_address'],
        'suppliers_suburb' => $row['suppliers_suburb'],
        'suppliers_postcode' => $row['suppliers_postcode'],
        'suppliers_city' => $row['suppliers_city'],
        'suppliers_states' => $row['suppliers_states'],
        'suppliers_country_id' => $row['suppliers_country_id'],
        'suppliers_notes' => $row['suppliers_notes'],
        'suppliers_status' => $row['suppliers_status'],
        'suppliers_url' => $row['suppliers_url'],
        'url_clicked' => $row['url_clicked'],
        'date_last_click' => $row['date_last_click'],
      ];
    }

    return $suppliers_data;
  }

  /**
   * Executes the API call to retrieve supplier data.
   *
   * @return array|false An array of supplier data or false if the token is not set.
   */
  public function execute()
  {
    if (!isset($_GET['sId'], $_GET['token'])) {
      return false;
    }

    if (ApiSecurity::isLocalEnvironment()) {
      ApiSecurity::logSecurityEvent('Local environment detected', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    }
    
    if (!isset($_GET['token'])) {
      ApiSecurity::logSecurityEvent('Missing token in supplier request');
      return false;
    }

    // Check if the token is valid
    $token = ApiSecurity::checkToken($_GET['token']);
    if (!$token) {
      return false;
    }

    // Rate limiting
    $clientIp = HTTP::getIpAddress();
    if (!ApiSecurity::checkRateLimit($clientIp, 'get_supplier')) {
      return false;
    }

    $id = HTML::sanitize($_GET['sId']);
    ApiSecurity::secureGetId($id);

    $language_id = isset($_GET['lId']) ? HTML::sanitize($_GET['lId']) : null;

    return self::suppliers($id, $language_id);
  }
}

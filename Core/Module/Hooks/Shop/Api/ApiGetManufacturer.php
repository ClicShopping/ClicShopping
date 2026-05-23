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

class ApiGetManufacturer
{
  /**
   * Retrieves a list of manufacturers and related details based on manufacturer ID and language ID.
   *
   * @param int|string $id The manufacturer ID to filter results. Can be an integer or a string.
   * @param int|string $language_id The language ID to filter results. Can be an integer or a string.
   * @return array An array containing manufacturer details such as ID, name, URL, SEO information, and description.
   */
  private static function manufacturers(mixed $id = null, mixed $language_id = null): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $sql = 'SELECT m.*, mi.*
          FROM :table_manufacturers m
          JOIN :table_manufacturers_info mi ON m.manufacturers_id = mi.manufacturers_id
          WHERE 1
          limit 100
          ';

    $params = [];

    if ($id !== null && $id !== 'All' && is_numeric($id)) {
      $sql .= ' AND m.manufacturers_id = :manufacturers_id';
      $params[':manufacturers_id'] = (int)$id;
    }

    if ($language_id !== null && is_numeric($language_id)) {
      $sql .= ' AND mi.languages_id = :language_id';
      $params[':language_id'] = (int)$language_id;
    }

    $Qapi = $CLICSHOPPING_Db->prepare($sql);

    foreach ($params as $key => $value) {
      $Qapi->bindInt($key, $value);
    }

    $Qapi->execute();

    $manufacturers_data = [];

    foreach ($Qapi->fetchAll() as $row) {
      $manufacturers_data[] = [
        'manufacturers_id' => $row['manufacturers_id'],
        'languages_id' => $row['languages_id'],
        'manufacturers_name' => $row['manufacturers_name'],
        'date_added' => $row['date_added'],
        'last_modified' => $row['last_modified'],
        'suppliers_id' => $row['suppliers_id'],
        'manufacturers_url' => $row['manufacturers_url'],
        'url_clicked' => $row['url_clicked'],
        'date_last_click' => $row['date_last_click'],
        'manufacturer_seo_title' => $row['manufacturer_seo_title'],
        'manufacturer_seo_keyword' => $row['manufacturer_seo_keyword'],
        'manufacturer_seo_description' => $row['manufacturer_seo_description'],
        'manufacturer_description' => $row['manufacturer_description'],
      ];
    }

    return $manufacturers_data;
  }

  /**
   * Executes the API call to retrieve manufacturer data.
   *
   * @return array|false An array of manufacturer data or false if parameters are missing.
   */
  public function execute()
  {
    if (!isset($_GET['mId'], $_GET['token'])) {
      if (ApiSecurity::isLocalEnvironment()) {
        ApiSecurity::logSecurityEvent('Local environment detected', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
      }

      if (!isset($_GET['token'])) {
        ApiSecurity::logSecurityEvent('Missing token in manufacturer request');
        return false;
      }

      // Check if the token is valid
      $token = ApiSecurity::checkToken($_GET['token']);
      if (!$token) {
        return false;
      }

      // Rate limiting
      $clientIp = HTTP::getIpAddress();
      if (!ApiSecurity::checkRateLimit($clientIp, 'get_manufacturer')) {
        return false;
      }

      return false;
    }

    $id = HTML::sanitize($_GET['mId']);
    ApiSecurity::secureGetId($id);

    $language_id = isset($_GET['lId']) ? HTML::sanitize($_GET['lId']) : null;

    return self::manufacturers($id, $language_id);
  }
}

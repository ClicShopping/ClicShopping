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

class ApiPutManufacturer
{
  /**
   * Updates manufacturer data based on the provided manufacturer ID and data.
   *
   * @param int $id The manufacturer ID to update
   * @param array $data The manufacturer data to update
   * @return array An array containing the result of the update operation
   */
  private static function updateManufacturer(int $id, array $data): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    try {
      $CLICSHOPPING_Db->beginTransaction();

      // First, verify that the manufacturer exists
      $checkSql = 'SELECT manufacturers_id FROM :table_manufacturers WHERE manufacturers_id = :manufacturers_id';
      $Qcheck = $CLICSHOPPING_Db->prepare($checkSql);
      $Qcheck->bindInt(':manufacturers_id', $id);
      $Qcheck->execute();

      if ($Qcheck->rowCount() === 0) {
        $CLICSHOPPING_Db->rollback();
        return [
          'success' => false,
          'error' => 'Manufacturer not found',
          'error_code' => 'MANUFACTURER_NOT_FOUND'
        ];
      }

      // Update manufacturers table
      $manufacturerFields = [];
      $manufacturerParams = [':manufacturers_id' => $id];

      if (isset($data['manufacturers_name'])) {
        $manufacturerFields[] = 'manufacturers_name = :manufacturers_name';
        $manufacturerParams[':manufacturers_name'] = HTML::sanitize($data['manufacturers_name']);
      }

      if (isset($data['suppliers_id'])) {
        // Validate that supplier exists if not null
        if ($data['suppliers_id'] !== null && $data['suppliers_id'] !== '') {
          $checkSupplierSql = 'SELECT suppliers_id FROM :table_suppliers WHERE suppliers_id = :suppliers_id';
          $QcheckSupplier = $CLICSHOPPING_Db->prepare($checkSupplierSql);
          $QcheckSupplier->bindInt(':suppliers_id', (int)$data['suppliers_id']);
          $QcheckSupplier->execute();

          if ($QcheckSupplier->rowCount() === 0) {
            $CLICSHOPPING_Db->rollback();
            return [
              'success' => false,
              'error' => 'Supplier not found',
              'error_code' => 'SUPPLIER_NOT_FOUND'
            ];
          }
        }

        $manufacturerFields[] = 'suppliers_id = :suppliers_id';
        $manufacturerParams[':suppliers_id'] = ($data['suppliers_id'] !== null && $data['suppliers_id'] !== '') ? (int)$data['suppliers_id'] : null;
      }

      // Always update last_modified if we have manufacturer fields to update
      if (!empty($manufacturerFields)) {
        $manufacturerFields[] = 'last_modified = NOW()';
        $manufacturerSql = 'UPDATE :table_manufacturers SET ' . implode(', ', $manufacturerFields) . ' WHERE manufacturers_id = :manufacturers_id';
        $QmanufacturerUpdate = $CLICSHOPPING_Db->prepare($manufacturerSql);

        foreach ($manufacturerParams as $key => $value) {
          if (is_int($value)) {
            $QmanufacturerUpdate->bindInt($key, $value);
          } elseif ($value === null) {
            $QmanufacturerUpdate->bindNull($key);
          } else {
            $QmanufacturerUpdate->bindValue($key, $value);
          }
        }

        $QmanufacturerUpdate->execute();
      }

      // Update manufacturers_info table if language-specific data is provided
      if (isset($data['languages_id'])) {
        $infoFields = [];
        $infoParams = [
          ':manufacturers_id' => $id,
          ':languages_id' => (int)$data['languages_id']
        ];

        if (isset($data['manufacturers_url'])) {
          $infoFields[] = 'manufacturers_url = :manufacturers_url';
          $infoParams[':manufacturers_url'] = HTML::sanitize($data['manufacturers_url']);
        }

        if (isset($data['url_clicked'])) {
          $infoFields[] = 'url_clicked = :url_clicked';
          $infoParams[':url_clicked'] = (int)$data['url_clicked'];
        }

        if (isset($data['date_last_click'])) {
          $infoFields[] = 'date_last_click = :date_last_click';
          $infoParams[':date_last_click'] = $data['date_last_click'];
        }

        if (isset($data['manufacturer_seo_title'])) {
          $infoFields[] = 'manufacturer_seo_title = :manufacturer_seo_title';
          $infoParams[':manufacturer_seo_title'] = HTML::sanitize($data['manufacturer_seo_title']);
        }

        if (isset($data['manufacturer_seo_keyword'])) {
          $infoFields[] = 'manufacturer_seo_keyword = :manufacturer_seo_keyword';
          $infoParams[':manufacturer_seo_keyword'] = HTML::sanitize($data['manufacturer_seo_keyword']);
        }

        if (isset($data['manufacturer_seo_description'])) {
          $infoFields[] = 'manufacturer_seo_description = :manufacturer_seo_description';
          $infoParams[':manufacturer_seo_description'] = HTML::sanitize($data['manufacturer_seo_description']);
        }

        if (isset($data['manufacturer_description'])) {
          $infoFields[] = 'manufacturer_description = :manufacturer_description';
          $infoParams[':manufacturer_description'] = HTML::sanitize($data['manufacturer_description']);
        }

        if (!empty($infoFields)) {
          // Check if record exists in manufacturers_info
          $checkInfoSql = 'SELECT manufacturers_id FROM :table_manufacturers_info WHERE manufacturers_id = :manufacturers_id AND languages_id = :languages_id';
          $QcheckInfo = $CLICSHOPPING_Db->prepare($checkInfoSql);
          $QcheckInfo->bindInt(':manufacturers_id', $id);
          $QcheckInfo->bindInt(':languages_id', (int)$data['languages_id']);
          $QcheckInfo->execute();

          if ($QcheckInfo->rowCount() > 0) {
            // Update existing record
            $infoSql = 'UPDATE :table_manufacturers_info SET ' . implode(', ', $infoFields) . ' WHERE manufacturers_id = :manufacturers_id AND languages_id = :languages_id';
          } else {
            // Insert new record
            $infoFields[] = 'manufacturers_id = :manufacturers_id';
            $infoFields[] = 'languages_id = :languages_id';
            $infoSql = 'INSERT INTO :table_manufacturers_info SET ' . implode(', ', $infoFields);
          }

          $QinfoUpdate = $CLICSHOPPING_Db->prepare($infoSql);

          foreach ($infoParams as $key => $value) {
            if (is_int($value)) {
              $QinfoUpdate->bindInt($key, $value);
            } else {
              $QinfoUpdate->bindValue($key, $value);
            }
          }

          $QinfoUpdate->execute();
        }
      }

      $CLICSHOPPING_Db->commit();

      return [
        'success' => true,
        'message' => 'Manufacturer updated successfully',
        'manufacturers_id' => $id,
        'updated_fields' => array_keys($data)
      ];

    } catch (\Exception $e) {
      $CLICSHOPPING_Db->rollback();

      // Log the error
      ApiSecurity::logSecurityEvent('Database error during manufacturer update', [
        'error' => $e->getMessage(),
        'manufacturers_id' => $id
      ]);

      return [
        'success' => false,
        'error' => 'Database error occurred',
        'error_code' => 'DATABASE_ERROR'
      ];
    }
  }

  /**
   * Validates the manufacturer data before updating.
   *
   * @param array $data The manufacturer data to validate
   * @return array An array containing validation result and errors if any
   */
  private static function validateManufacturerData(array $data): array
  {
    $errors = [];

    // Validate required fields if provided
    if (isset($data['manufacturers_name']) && empty(trim($data['manufacturers_name']))) {
      $errors[] = 'Manufacturer name cannot be empty';
    }

    // Validate numeric fields
    if (isset($data['suppliers_id']) && $data['suppliers_id'] !== null && $data['suppliers_id'] !== '' && !is_numeric($data['suppliers_id'])) {
      $errors[] = 'Supplier ID must be numeric or null';
    }

    if (isset($data['languages_id']) && !is_numeric($data['languages_id'])) {
      $errors[] = 'Language ID must be numeric';
    }

    if (isset($data['url_clicked']) && !is_numeric($data['url_clicked'])) {
      $errors[] = 'URL clicked count must be numeric';
    }

    // Validate URL format if provided
    if (isset($data['manufacturers_url']) && !empty($data['manufacturers_url'])) {
      if (!filter_var($data['manufacturers_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid manufacturer URL format';
      }
    }

    // Validate date format if provided
    if (isset($data['date_last_click']) && !empty($data['date_last_click'])) {
      if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['date_last_click'])) {
        $errors[] = 'Invalid date format for date_last_click (expected YYYY-MM-DD HH:MM:SS)';
      }
    }

    // Validate string lengths
    if (isset($data['manufacturers_name']) && strlen($data['manufacturers_name']) > 255) {
      $errors[] = 'Manufacturer name is too long (maximum 255 characters)';
    }

    if (isset($data['manufacturer_seo_title']) && strlen($data['manufacturer_seo_title']) > 255) {
      $errors[] = 'SEO title is too long (maximum 255 characters)';
    }

    if (isset($data['manufacturer_seo_keyword']) && strlen($data['manufacturer_seo_keyword']) > 500) {
      $errors[] = 'SEO keywords are too long (maximum 500 characters)';
    }

    if (isset($data['manufacturer_seo_description']) && strlen($data['manufacturer_seo_description']) > 500) {
      $errors[] = 'SEO description is too long (maximum 500 characters)';
    }

    // Validate suppliers_id is not negative (if provided and not null)
    if (isset($data['suppliers_id']) && $data['suppliers_id'] !== null && $data['suppliers_id'] !== '' && (int)$data['suppliers_id'] < 0) {
      $errors[] = 'Supplier ID cannot be negative';
    }

    // Validate url_clicked is not negative
    if (isset($data['url_clicked']) && (int)$data['url_clicked'] < 0) {
      $errors[] = 'URL clicked count cannot be negative';
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors
    ];
  }

  /**
   * Executes the API call to update manufacturer data.
   *
   * @return array An array containing the result of the update operation or false if validation fails
   */
  public function execute()
  {
    // Check if required parameters are present
    if (!isset($_PUT['mId'], $_PUT['token'])) {
      return [
        'success' => false,
        'error' => 'Missing required parameters (mId, token)',
        'error_code' => 'MISSING_PARAMETERS'
      ];
    }

    // Security checks
    if (ApiSecurity::isLocalEnvironment()) {
      ApiSecurity::logSecurityEvent('Local environment detected', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    }

    // Check if the token is valid
    $token = ApiSecurity::checkToken($_PUT['token']);
    if (!$token) {
      return [
        'success' => false,
        'error' => 'Invalid or expired token',
        'error_code' => 'INVALID_TOKEN'
      ];
    }

    // Rate limiting
    $clientIp = HTTP::getIpAddress();
    if (!ApiSecurity::checkRateLimit($clientIp, 'put_manufacturer')) {
      return [
        'success' => false,
        'error' => 'Rate limit exceeded',
        'error_code' => 'RATE_LIMIT_EXCEEDED'
      ];
    }

    // Sanitize and validate manufacturer ID
    $id = HTML::sanitize($_PUT['mId']);
    if (!is_numeric($id) || $id <= 0) {
      return [
        'success' => false,
        'error' => 'Invalid manufacturer ID',
        'error_code' => 'INVALID_MANUFACTURER_ID'
      ];
    }

    ApiSecurity::secureGetId($id);

    // Get the manufacturer data from PUT request
    $manufacturerData = [];
    $allowedFields = [
      'manufacturers_name', 'suppliers_id', 'manufacturers_url', 'url_clicked',
      'date_last_click', 'manufacturer_seo_title', 'manufacturer_seo_keyword',
      'manufacturer_seo_description', 'manufacturer_description', 'languages_id'
    ];

    foreach ($allowedFields as $field) {
      if (isset($_PUT[$field])) {
        $manufacturerData[$field] = $_PUT[$field];
      }
    }

    // Validate the data
    $validation = self::validateManufacturerData($manufacturerData);
    if (!$validation['valid']) {
      return [
        'success' => false,
        'error' => 'Validation failed',
        'error_code' => 'VALIDATION_ERROR',
        'validation_errors' => $validation['errors']
      ];
    }

    // Check if there's actually data to update
    if (empty($manufacturerData)) {
      return [
        'success' => false,
        'error' => 'No valid data provided for update',
        'error_code' => 'NO_UPDATE_DATA'
      ];
    }

    // Log the update attempt
    ApiSecurity::logSecurityEvent('Manufacturer update attempt', [
      'manufacturers_id' => $id,
      'fields' => array_keys($manufacturerData),
      'ip' => $clientIp
    ]);

    // Perform the update
    return self::updateManufacturer((int)$id, $manufacturerData);
  }
}
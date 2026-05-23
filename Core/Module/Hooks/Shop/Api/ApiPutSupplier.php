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

class ApiPutSupplier
{
  /**
   * Updates supplier data based on the provided supplier ID and data.
   *
   * @param int $id The supplier ID to update
   * @param array $data The supplier data to update
   * @return array An array containing the result of the update operation
   */
  private static function updateSupplier(int $id, array $data): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    try {
      $CLICSHOPPING_Db->beginTransaction();

      // First, verify that the supplier exists
      $checkSql = 'SELECT suppliers_id FROM :table_suppliers WHERE suppliers_id = :suppliers_id';
      $Qcheck = $CLICSHOPPING_Db->prepare($checkSql);
      $Qcheck->bindInt(':suppliers_id', $id);
      $Qcheck->execute();

      if ($Qcheck->rowCount() === 0) {
        $CLICSHOPPING_Db->rollback();
        return [
          'success' => false,
          'error' => 'Supplier not found',
          'error_code' => 'SUPPLIER_NOT_FOUND'
        ];
      }

      // Update suppliers table
      $supplierFields = [];
      $supplierParams = [':suppliers_id' => $id];

      if (isset($data['suppliers_name'])) {
        $supplierFields[] = 'suppliers_name = :suppliers_name';
        $supplierParams[':suppliers_name'] = HTML::sanitize($data['suppliers_name']);
      }

      if (isset($data['suppliers_manager'])) {
        $supplierFields[] = 'suppliers_manager = :suppliers_manager';
        $supplierParams[':suppliers_manager'] = HTML::sanitize($data['suppliers_manager']);
      }

      if (isset($data['suppliers_phone'])) {
        $supplierFields[] = 'suppliers_phone = :suppliers_phone';
        $supplierParams[':suppliers_phone'] = HTML::sanitize($data['suppliers_phone']);
      }

      if (isset($data['suppliers_email_address'])) {
        $supplierFields[] = 'suppliers_email_address = :suppliers_email_address';
        $supplierParams[':suppliers_email_address'] = HTML::sanitize($data['suppliers_email_address']);
      }

      if (isset($data['suppliers_fax'])) {
        $supplierFields[] = 'suppliers_fax = :suppliers_fax';
        $supplierParams[':suppliers_fax'] = HTML::sanitize($data['suppliers_fax']);
      }

      if (isset($data['suppliers_address'])) {
        $supplierFields[] = 'suppliers_address = :suppliers_address';
        $supplierParams[':suppliers_address'] = HTML::sanitize($data['suppliers_address']);
      }

      if (isset($data['suppliers_suburb'])) {
        $supplierFields[] = 'suppliers_suburb = :suppliers_suburb';
        $supplierParams[':suppliers_suburb'] = HTML::sanitize($data['suppliers_suburb']);
      }

      if (isset($data['suppliers_postcode'])) {
        $supplierFields[] = 'suppliers_postcode = :suppliers_postcode';
        $supplierParams[':suppliers_postcode'] = HTML::sanitize($data['suppliers_postcode']);
      }

      if (isset($data['suppliers_city'])) {
        $supplierFields[] = 'suppliers_city = :suppliers_city';
        $supplierParams[':suppliers_city'] = HTML::sanitize($data['suppliers_city']);
      }

      if (isset($data['suppliers_states'])) {
        $supplierFields[] = 'suppliers_states = :suppliers_states';
        $supplierParams[':suppliers_states'] = HTML::sanitize($data['suppliers_states']);
      }

      if (isset($data['suppliers_country_id'])) {
        $supplierFields[] = 'suppliers_country_id = :suppliers_country_id';
        $supplierParams[':suppliers_country_id'] = (int)$data['suppliers_country_id'];
      }

      if (isset($data['suppliers_notes'])) {
        $supplierFields[] = 'suppliers_notes = :suppliers_notes';
        $supplierParams[':suppliers_notes'] = HTML::sanitize($data['suppliers_notes']);
      }

      if (isset($data['suppliers_status'])) {
        $supplierFields[] = 'suppliers_status = :suppliers_status';
        $supplierParams[':suppliers_status'] = (int)$data['suppliers_status'];
      }

      // Always update last_modified
      $supplierFields[] = 'last_modified = NOW()';

      if (!empty($supplierFields)) {
        $supplierSql = 'UPDATE :table_suppliers SET ' . implode(', ', $supplierFields) . ' WHERE suppliers_id = :suppliers_id';
        $QsupplierUpdate = $CLICSHOPPING_Db->prepare($supplierSql);

        foreach ($supplierParams as $key => $value) {
          if (is_int($value)) {
            $QsupplierUpdate->bindInt($key, $value);
          } else {
            $QsupplierUpdate->bindValue($key, $value);
          }
        }

        $QsupplierUpdate->execute();
      }

      // Update suppliers_info table if language-specific data is provided
      if (isset($data['languages_id'])) {
        $infoFields = [];
        $infoParams = [
          ':suppliers_id' => $id,
          ':languages_id' => (int)$data['languages_id']
        ];

        if (isset($data['suppliers_url'])) {
          $infoFields[] = 'suppliers_url = :suppliers_url';
          $infoParams[':suppliers_url'] = HTML::sanitize($data['suppliers_url']);
        }

        if (isset($data['url_clicked'])) {
          $infoFields[] = 'url_clicked = :url_clicked';
          $infoParams[':url_clicked'] = (int)$data['url_clicked'];
        }

        if (isset($data['date_last_click'])) {
          $infoFields[] = 'date_last_click = :date_last_click';
          $infoParams[':date_last_click'] = $data['date_last_click'];
        }

        if (!empty($infoFields)) {
          // Check if record exists in suppliers_info
          $checkInfoSql = 'SELECT suppliers_id FROM :table_suppliers_info WHERE suppliers_id = :suppliers_id AND languages_id = :languages_id';
          $QcheckInfo = $CLICSHOPPING_Db->prepare($checkInfoSql);
          $QcheckInfo->bindInt(':suppliers_id', $id);
          $QcheckInfo->bindInt(':languages_id', (int)$data['languages_id']);
          $QcheckInfo->execute();

          if ($QcheckInfo->rowCount() > 0) {
            // Update existing record
            $infoSql = 'UPDATE :table_suppliers_info SET ' . implode(', ', $infoFields) . ' WHERE suppliers_id = :suppliers_id AND languages_id = :languages_id';
          } else {
            // Insert new record
            $infoFields[] = 'suppliers_id = :suppliers_id';
            $infoFields[] = 'languages_id = :languages_id';
            $infoSql = 'INSERT INTO :table_suppliers_info SET ' . implode(', ', $infoFields);
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
        'message' => 'Supplier updated successfully',
        'suppliers_id' => $id,
        'updated_fields' => array_keys($data)
      ];

    } catch (\Exception $e) {
      $CLICSHOPPING_Db->rollback();

      // Log the error
      ApiSecurity::logSecurityEvent('Database error during supplier update', [
        'error' => $e->getMessage(),
        'suppliers_id' => $id
      ]);

      return [
        'success' => false,
        'error' => 'Database error occurred',
        'error_code' => 'DATABASE_ERROR'
      ];
    }
  }

  /**
   * Validates the supplier data before updating.
   *
   * @param array $data The supplier data to validate
   * @return array An array containing validation result and errors if any
   */
  private static function validateSupplierData(array $data): array
  {
    $errors = [];

    // Validate email format if provided
    if (isset($data['suppliers_email_address']) && !empty($data['suppliers_email_address'])) {
      if (!filter_var($data['suppliers_email_address'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address format';
      }
    }

    // Validate required fields if provided
    if (isset($data['suppliers_name']) && empty(trim($data['suppliers_name']))) {
      $errors[] = 'Supplier name cannot be empty';
    }

    // Validate numeric fields
    if (isset($data['suppliers_country_id']) && !is_numeric($data['suppliers_country_id'])) {
      $errors[] = 'Country ID must be numeric';
    }

    if (isset($data['suppliers_status']) && !in_array($data['suppliers_status'], [0, 1])) {
      $errors[] = 'Supplier status must be 0 or 1';
    }

    if (isset($data['languages_id']) && !is_numeric($data['languages_id'])) {
      $errors[] = 'Language ID must be numeric';
    }

    // Validate URL format if provided
    if (isset($data['suppliers_url']) && !empty($data['suppliers_url'])) {
      if (!filter_var($data['suppliers_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid URL format';
      }
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors
    ];
  }

  /**
   * Executes the API call to update supplier data.
   *
   * @return array An array containing the result of the update operation or false if validation fails
   */
  public function execute()
  {
    // Check if required parameters are present
    if (!isset($_PUT['sId'], $_PUT['token'])) {
      return [
        'success' => false,
        'error' => 'Missing required parameters (sId, token)',
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
    if (!ApiSecurity::checkRateLimit($clientIp, 'put_supplier')) {
      return [
        'success' => false,
        'error' => 'Rate limit exceeded',
        'error_code' => 'RATE_LIMIT_EXCEEDED'
      ];
    }

    // Sanitize and validate supplier ID
    $id = HTML::sanitize($_PUT['sId']);
    if (!is_numeric($id) || $id <= 0) {
      return [
        'success' => false,
        'error' => 'Invalid supplier ID',
        'error_code' => 'INVALID_SUPPLIER_ID'
      ];
    }

    ApiSecurity::secureGetId($id);

    // Get the supplier data from PUT request
    $supplierData = [];
    $allowedFields = [
      'suppliers_name', 'suppliers_manager', 'suppliers_phone', 'suppliers_email_address',
      'suppliers_fax', 'suppliers_address', 'suppliers_suburb', 'suppliers_postcode',
      'suppliers_city', 'suppliers_states', 'suppliers_country_id', 'suppliers_notes',
      'suppliers_status', 'suppliers_url', 'url_clicked', 'date_last_click', 'languages_id'
    ];

    foreach ($allowedFields as $field) {
      if (isset($_PUT[$field])) {
        $supplierData[$field] = $_PUT[$field];
      }
    }

    // Validate the data
    $validation = self::validateSupplierData($supplierData);
    if (!$validation['valid']) {
      return [
        'success' => false,
        'error' => 'Validation failed',
        'error_code' => 'VALIDATION_ERROR',
        'validation_errors' => $validation['errors']
      ];
    }

    // Check if there's actually data to update
    if (empty($supplierData)) {
      return [
        'success' => false,
        'error' => 'No valid data provided for update',
        'error_code' => 'NO_UPDATE_DATA'
      ];
    }

    // Log the update attempt
    ApiSecurity::logSecurityEvent('Supplier update attempt', [
      'suppliers_id' => $id,
      'fields' => array_keys($supplierData),
      'ip' => $clientIp
    ]);

    // Perform the update
    return self::updateSupplier((int)$id, $supplierData);
  }
}
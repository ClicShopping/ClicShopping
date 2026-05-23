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

class ApiPutCategories
{
  /**
   * Updates category data based on the provided category ID and data.
   *
   * @param int $id The category ID to update
   * @param array $data The category data to update
   * @return array An array containing the result of the update operation
   */
  private static function updateCategory(int $id, array $data): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    try {
      $CLICSHOPPING_Db->beginTransaction();

      // First, verify that the category exists
      $checkSql = 'SELECT categories_id FROM :table_categories WHERE categories_id = :categories_id';
      $Qcheck = $CLICSHOPPING_Db->prepare($checkSql);
      $Qcheck->bindInt(':categories_id', $id);
      $Qcheck->execute();

      if ($Qcheck->rowCount() === 0) {
        $CLICSHOPPING_Db->rollback();
        return [
          'success' => false,
          'error' => 'Category not found',
          'error_code' => 'CATEGORY_NOT_FOUND'
        ];
      }

      // Update categories table
      $categoryFields = [];
      $categoryParams = [':categories_id' => $id];

      if (isset($data['parent_id'])) {
        // Validate that parent category exists (if not 0)
        if ($data['parent_id'] != 0) {
          $checkParentSql = 'SELECT categories_id FROM :table_categories WHERE categories_id = :parent_id';
          $QcheckParent = $CLICSHOPPING_Db->prepare($checkParentSql);
          $QcheckParent->bindInt(':parent_id', (int)$data['parent_id']);
          $QcheckParent->execute();

          if ($QcheckParent->rowCount() === 0) {
            $CLICSHOPPING_Db->rollback();
            return [
              'success' => false,
              'error' => 'Parent category not found',
              'error_code' => 'PARENT_CATEGORY_NOT_FOUND'
            ];
          }

          // Check for circular reference (category cannot be its own parent or descendant)
          if (self::wouldCreateCircularReference($id, (int)$data['parent_id'])) {
            $CLICSHOPPING_Db->rollback();
            return [
              'success' => false,
              'error' => 'Cannot create circular reference in category hierarchy',
              'error_code' => 'CIRCULAR_REFERENCE'
            ];
          }
        }

        $categoryFields[] = 'parent_id = :parent_id';
        $categoryParams[':parent_id'] = (int)$data['parent_id'];
      }

      // Always update last_modified if we have category fields to update
      if (!empty($categoryFields)) {
        $categoryFields[] = 'last_modified = NOW()';
        $categorySql = 'UPDATE :table_categories SET ' . implode(', ', $categoryFields) . ' WHERE categories_id = :categories_id';
        $QcategoryUpdate = $CLICSHOPPING_Db->prepare($categorySql);

        foreach ($categoryParams as $key => $value) {
          $QcategoryUpdate->bindInt($key, $value);
        }

        $QcategoryUpdate->execute();
      }

      // Update categories_description table if language-specific data is provided
      if (isset($data['language_id'])) {
        $descFields = [];
        $descParams = [
          ':categories_id' => $id,
          ':language_id' => (int)$data['language_id']
        ];

        if (isset($data['categories_name'])) {
          $descFields[] = 'categories_name = :categories_name';
          $descParams[':categories_name'] = HTML::sanitize($data['categories_name']);
        }

        if (isset($data['categories_description'])) {
          $descFields[] = 'categories_description = :categories_description';
          $descParams[':categories_description'] = HTML::sanitize($data['categories_description']);
        }

        if (isset($data['categories_seo_url'])) {
          $descFields[] = 'categories_seo_url = :categories_seo_url';
          $descParams[':categories_seo_url'] = HTML::sanitize($data['categories_seo_url']);
        }

        if (isset($data['categories_head_title_tag'])) {
          $descFields[] = 'categories_head_title_tag = :categories_head_title_tag';
          $descParams[':categories_head_title_tag'] = HTML::sanitize($data['categories_head_title_tag']);
        }

        if (isset($data['categories_head_desc_tag'])) {
          $descFields[] = 'categories_head_desc_tag = :categories_head_desc_tag';
          $descParams[':categories_head_desc_tag'] = HTML::sanitize($data['categories_head_desc_tag']);
        }

        if (isset($data['categories_head_keywords_tag'])) {
          $descFields[] = 'categories_head_keywords_tag = :categories_head_keywords_tag';
          $descParams[':categories_head_keywords_tag'] = HTML::sanitize($data['categories_head_keywords_tag']);
        }

        if (!empty($descFields)) {
          // Check if record exists in categories_description
          $checkDescSql = 'SELECT categories_id FROM :table_categories_description WHERE categories_id = :categories_id AND language_id = :language_id';
          $QcheckDesc = $CLICSHOPPING_Db->prepare($checkDescSql);
          $QcheckDesc->bindInt(':categories_id', $id);
          $QcheckDesc->bindInt(':language_id', (int)$data['language_id']);
          $QcheckDesc->execute();

          if ($QcheckDesc->rowCount() > 0) {
            // Update existing record
            $descSql = 'UPDATE :table_categories_description SET ' . implode(', ', $descFields) . ' WHERE categories_id = :categories_id AND language_id = :language_id';
          } else {
            // Insert new record
            $descFields[] = 'categories_id = :categories_id';
            $descFields[] = 'language_id = :language_id';
            $descSql = 'INSERT INTO :table_categories_description SET ' . implode(', ', $descFields);
          }

          $QdescUpdate = $CLICSHOPPING_Db->prepare($descSql);

          foreach ($descParams as $key => $value) {
            if (is_int($value)) {
              $QdescUpdate->bindInt($key, $value);
            } else {
              $QdescUpdate->bindValue($key, $value);
            }
          }

          $QdescUpdate->execute();
        }
      }

      $CLICSHOPPING_Db->commit();

      return [
        'success' => true,
        'message' => 'Category updated successfully',
        'categories_id' => $id,
        'updated_fields' => array_keys($data)
      ];

    } catch (\Exception $e) {
      $CLICSHOPPING_Db->rollback();

      // Log the error
      ApiSecurity::logSecurityEvent('Database error during category update', [
        'error' => $e->getMessage(),
        'categories_id' => $id
      ]);

      return [
        'success' => false,
        'error' => 'Database error occurred',
        'error_code' => 'DATABASE_ERROR'
      ];
    }
  }

  /**
   * Checks if setting a new parent would create a circular reference.
   *
   * @param int $categoryId The category ID being updated
   * @param int $newParentId The proposed new parent ID
   * @return bool True if it would create a circular reference, false otherwise
   */
  private static function wouldCreateCircularReference(int $categoryId, int $newParentId): bool
  {
    if ($categoryId === $newParentId) {
      return true; // Category cannot be its own parent
    }

    $CLICSHOPPING_Db = Registry::get('Db');
    $currentParent = $newParentId;
    $visitedCategories = [];

    // Traverse up the parent chain to check for circular reference
    while ($currentParent !== 0) {
      if (in_array($currentParent, $visitedCategories)) {
        return true; // Already visited, indicates circular reference
      }

      if ($currentParent === $categoryId) {
        return true; // Found the original category in the parent chain
      }

      $visitedCategories[] = $currentParent;

      // Get the parent of the current category
      $sql = 'SELECT parent_id FROM :table_categories WHERE categories_id = :categories_id';
      $Q = $CLICSHOPPING_Db->prepare($sql);
      $Q->bindInt(':categories_id', $currentParent);
      $Q->execute();

      if ($Q->rowCount() === 0) {
        break; // Parent doesn't exist
      }

      $currentParent = $Q->valueInt('parent_id');
    }

    return false;
  }

  /**
   * Validates the category data before updating.
   *
   * @param array $data The category data to validate
   * @return array An array containing validation result and errors if any
   */
  private static function validateCategoryData(array $data): array
  {
    $errors = [];

    // Validate required fields if provided
    if (isset($data['categories_name']) && empty(trim($data['categories_name']))) {
      $errors[] = 'Category name cannot be empty';
    }

    // Validate numeric fields
    if (isset($data['parent_id']) && !is_numeric($data['parent_id'])) {
      $errors[] = 'Parent ID must be numeric';
    }

    if (isset($data['language_id']) && !is_numeric($data['language_id'])) {
      $errors[] = 'Language ID must be numeric';
    }

    // Validate parent_id is not negative
    if (isset($data['parent_id']) && (int)$data['parent_id'] < 0) {
      $errors[] = 'Parent ID cannot be negative';
    }

    // Validate SEO URL format if provided
    if (isset($data['categories_seo_url']) && !empty($data['categories_seo_url'])) {
      if (!preg_match('/^[a-z0-9\-_]+$/i', $data['categories_seo_url'])) {
        $errors[] = 'SEO URL can only contain letters, numbers, hyphens and underscores';
      }
    }

    // Validate string lengths
    if (isset($data['categories_name']) && strlen($data['categories_name']) > 255) {
      $errors[] = 'Category name is too long (maximum 255 characters)';
    }

    if (isset($data['categories_head_title_tag']) && strlen($data['categories_head_title_tag']) > 255) {
      $errors[] = 'Head title tag is too long (maximum 255 characters)';
    }

    if (isset($data['categories_head_desc_tag']) && strlen($data['categories_head_desc_tag']) > 500) {
      $errors[] = 'Head description tag is too long (maximum 500 characters)';
    }

    if (isset($data['categories_head_keywords_tag']) && strlen($data['categories_head_keywords_tag']) > 500) {
      $errors[] = 'Head keywords tag is too long (maximum 500 characters)';
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors
    ];
  }

  /**
   * Executes the API call to update category data.
   *
   * @return array An array containing the result of the update operation or false if validation fails
   */
  public function execute()
  {
    // Check if required parameters are present
    if (!isset($_PUT['cId'], $_PUT['token'])) {
      return [
        'success' => false,
        'error' => 'Missing required parameters (cId, token)',
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
    if (!ApiSecurity::checkRateLimit($clientIp, 'put_categories')) {
      return [
        'success' => false,
        'error' => 'Rate limit exceeded',
        'error_code' => 'RATE_LIMIT_EXCEEDED'
      ];
    }

    // Sanitize and validate category ID
    $id = HTML::sanitize($_PUT['cId']);
    if (!is_numeric($id) || $id <= 0) {
      return [
        'success' => false,
        'error' => 'Invalid category ID',
        'error_code' => 'INVALID_CATEGORY_ID'
      ];
    }

    ApiSecurity::secureGetId($id);

    // Get the category data from PUT request
    $categoryData = [];
    $allowedFields = [
      'parent_id', 'categories_name', 'categories_description', 'categories_seo_url',
      'categories_head_title_tag', 'categories_head_desc_tag', 'categories_head_keywords_tag',
      'language_id'
    ];

    foreach ($allowedFields as $field) {
      if (isset($_PUT[$field])) {
        $categoryData[$field] = $_PUT[$field];
      }
    }

    // Validate the data
    $validation = self::validateCategoryData($categoryData);
    if (!$validation['valid']) {
      return [
        'success' => false,
        'error' => 'Validation failed',
        'error_code' => 'VALIDATION_ERROR',
        'validation_errors' => $validation['errors']
      ];
    }

    // Check if there's actually data to update
    if (empty($categoryData)) {
      return [
        'success' => false,
        'error' => 'No valid data provided for update',
        'error_code' => 'NO_UPDATE_DATA'
      ];
    }

    // Log the update attempt
    ApiSecurity::logSecurityEvent('Category update attempt', [
      'categories_id' => $id,
      'fields' => array_keys($categoryData),
      'ip' => $clientIp
    ]);

    // Perform the update
    return self::updateCategory((int)$id, $categoryData);
  }
}

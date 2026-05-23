<?php
/**
 * ProductHelper - Backward Compatibility Wrapper
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use ClicShopping\AI\InterfacesAI\EntityHelperInterface;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\EntityHelpers\DynamicEntityHelper;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\ProductsAdmin;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce;

/**
 * ProductHelper Class - Backward Compatibility Wrapper
 *
 * This class provides backward compatibility for existing code.
 * It delegates all calls to DynamicEntityHelper for product entity handling.
 * 
 * DEPRECATED: Use EcommerceEntityHelperRegistry instead
 * 
 * MIGRATION PATH:
 * - Old: $helper = new ProductHelper(true); $product = $helper->getProductById(123);
 * - New: $registry = EcommerceEntityHelperRegistry::getInstance(true); $helper = $registry->getProductHelper();
 * 
 * TASK 2.18: Created to support context memory for follow-up queries
 * TASK 2.19: Moved to Ecommerce domain (Phase 1 refactoring - 2026-01-20)
 * TASK 7.1: Adapted to use ProductsAdmin class methods (2026-01-21)
 * TASK 7.1: Refactored to use constructor instead of initialize() (2026-01-21)
 * TASK 7.2: Refactored to wrapper for backward compatibility (2026-01-21)
 * TASK 7.3: Removed ProductEntityHelper, now uses DynamicEntityHelper (2026-01-21)
 * 
 * @since 2026-01-21
 * @version 3.0.0 (Dynamic Entity Helper)
 */
class ProductHelper implements EntityHelperInterface
{
  private DynamicEntityHelper $dynamicHelper;
  private ?SecurityLogger $logger = null;
  private bool $debug = false;
  private ?ProductsAdmin $productsAdmin = null;
  private ?Ecommerce $ecommerceApp = null;

  /**
   * Constructor - Initialize wrapper with DynamicEntityHelper
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
    $this->logger = new SecurityLogger();
    // Use 'products' (plural) as the entity type since the table is 'products'
    $this->dynamicHelper = new DynamicEntityHelper('products', $debug);
    $this->productsAdmin = new ProductsAdmin();
    $this->ecommerceApp = Registry::get('Ecommerce');
  }

  /**
   * Get product details by ID (EntityHelperInterface implementation)
   * 
   * @param int $id Product ID
   * @param int|null $languageId Language ID (defaults to current language)
   * @return array|null Product data or null if not found
   */
  public static function getEntityById(int $id, ?int $languageId = null): ?array
  {
    $helper = new self();
    return $helper->getProductById($id, $languageId);
  }

  /**
   * Get product name by ID (EntityHelperInterface implementation)
   * 
   * @param int $id Product ID
   * @param int|null $languageId Language ID (defaults to current language)
   * @return string|null Product name or null if not found
   */
  public static function getEntityName(int $id, ?int $languageId = null): ?string
  {
    $helper = new self();
    return $helper->getProductName($id, $languageId);
  }

  /**
   * Get multiple products by IDs (EntityHelperInterface implementation)
   * 
   * @param array $ids Array of product IDs
   * @param int|null $languageId Language ID (defaults to current language)
   * @return array Array of product data indexed by product_id
   */
  public static function getEntitiesByIds(array $ids, ?int $languageId = null): array
  {
    $helper = new self();
    return $helper->getProductsByIds($ids, $languageId);
  }

  /**
   * Check if product exists (EntityHelperInterface implementation)
   * 
   * @param int $id Product ID
   * @return bool True if product exists
   */
  public static function entityExists(int $id): bool
  {
    $helper = new self();
    return $helper->productExists($id);
  }

  /**
   * Get product details by ID (instance method)
   * 
   * Retrieves product information from database including:
   * - Product ID
   * - Product name (localized)
   * - Price
   * - Model/SKU
   * - EAN/GTIN
   * - Stock quantity
   *
   * @param int $productId Product ID
   * @param int|null $languageId Language ID (defaults to current language)
   * @return array|null Product data or null if not found
   */
  public function getProductById(int $productId, ?int $languageId = null): ?array
  {
    try {
      // Use DynamicEntityHelper for basic product data
      $product = $this->dynamicHelper->getById($productId, $languageId);
      
      if ($product === null) {
        if ($this->debug && $this->logger !== null) {
          $this->logger->logSecurityEvent(
            "ProductHelper: Product not found with ID {$productId}",
            'warning'
          );
        }
        return null;
      }
      
      // Enhance with ProductsAdmin data for additional fields
      try {
        $productsAdmin = $this->productsAdmin;
        $productData = $productsAdmin->get($productId);
        
        if (!empty($productData)) {
          $data = $productData[0] ?? $productData;
          
          // Merge DynamicEntityHelper data with ProductsAdmin data
          $product = array_merge($product, [
            'price' => (float)($data['products_price'] ?? 0),
            'model' => $data['products_model'] ?? '',
            'ean' => $data['products_ean'] ?? '',
            'stock' => (int)($data['products_quantity'] ?? 0),
            'description' => $data['products_description'] ?? '',
            'image' => $data['products_image'] ?? '',
          ]);
        }
      } catch (\Exception $e) {
        // If ProductsAdmin fails, just use DynamicEntityHelper data
        if ($this->debug && $this->logger !== null) {
          $this->logger->logSecurityEvent(
            "ProductHelper: Could not enhance product data from ProductsAdmin: " . $e->getMessage(),
            'warning'
          );
        }
      }
      
      if ($this->debug && $this->logger !== null) {
        $this->logger->logSecurityEvent(
          "ProductHelper: Retrieved product by ID {$productId}",
          'info'
        );
      }
      
      return $product;
      
    } catch (\Exception $e) {
      if ($this->logger !== null) {
        $this->logger->logSecurityEvent(
          "ProductHelper: Error getting product by ID {$productId}: " . $e->getMessage(),
          'error'
        );
      }
      return null;
    }
  }

  /**
   * Get product name by ID (instance method)
   * 
   * Quick method to retrieve just the product name
   *
   * @param int $productId Product ID
   * @param int|null $languageId Language ID (defaults to current language)
   * @return string|null Product name or null if not found
   */
  public function getProductName(int $productId, ?int $languageId = null): ?string
  {
    try {
      // Use DynamicEntityHelper to get name
      $name = $this->dynamicHelper->getNameById($productId, $languageId);
      
      return $name ?: null;
      
    } catch (\Exception $e) {
      if ($this->logger !== null) {
        $this->logger->logSecurityEvent(
          "ProductHelper: Error getting product name for ID {$productId}: " . $e->getMessage(),
          'error'
        );
      }
      return null;
    }
  }

  /**
   * Get multiple products by IDs (instance method)
   * 
   * Batch retrieval for multiple products
   *
   * @param array $productIds Array of product IDs
   * @param int|null $languageId Language ID (defaults to current language)
   * @return array Array of product data indexed by product_id
   */
  public function getProductsByIds(array $productIds, ?int $languageId = null): array
  {
    if (empty($productIds)) {
      return [];
    }
    
    try {
      // Use DynamicEntityHelper for batch retrieval
      $products = $this->dynamicHelper->getByIds($productIds, $languageId);
      
      if ($this->debug && $this->logger !== null) {
        $this->logger->logSecurityEvent(
          "ProductHelper: Retrieved " . count($products) . " products by IDs",
          'info'
        );
      }
      
      return $products;
      
    } catch (\Exception $e) {
      if ($this->logger !== null) {
        $this->logger->logSecurityEvent(
          "ProductHelper: Error getting products by IDs: " . $e->getMessage(),
          'error'
        );
      }
      return [];
    }
  }

  /**
   * Check if product exists (instance method)
   *
   * @param int $productId Product ID
   * @return bool True if product exists
   */
  public function productExists(int $productId): bool
  {
    try {
      // Use DynamicEntityHelper to check existence
      return $this->dynamicHelper->exists($productId);
      
    } catch (\Exception $e) {
      if ($this->logger !== null) {
        $this->logger->logSecurityEvent(
          "ProductHelper: Error checking product existence: " . $e->getMessage(),
          'error'
        );
      }
      return false;
    }
  }

  /**
   * Get product by model/SKU
   *
   * @param string $model Product model/SKU
   * @param int|null $languageId Language ID (defaults to current language)
   * @return array|null Product data or null if not found
   */
  public function getProductByModel(string $model, ?int $languageId = null): ?array
  {
    try {
      if ($languageId === null) {
        $languageId = Registry::get('Language')->getId();
      }
      
      $ecommerceApp = $this->ecommerceApp;
      $ClicShopping_Db = $ecommerceApp->db;
      
      // Query using Ecommerce app database
      $Qproduct = $ClicShopping_Db->prepare('SELECT 
                                  p.products_id as product_id,
                                  pd.products_name as name,
                                  p.products_price as price,
                                  p.products_model as model,
                                  p.products_ean as ean,
                                  p.products_quantity as stock
                                FROM :table_products p
                                INNER JOIN :table_products_description pd 
                                  ON p.products_id = pd.products_id
                                WHERE p.products_model = :model
                                AND pd.language_id = :language_id
                                LIMIT 1');
      
      $Qproduct->bindValue(':model', $model);
      $Qproduct->bindInt(':language_id', $languageId);
      $Qproduct->execute();
      
      $result = $Qproduct->fetch();
      
      return $result ?: null;
      
    } catch (\Exception $e) {
      if ($this->logger !== null) {
        $this->logger->logSecurityEvent(
          "ProductHelper: Error getting product by model {$model}: " . $e->getMessage(),
          'error'
        );
      }
      return null;
    }
  }

  /**
   * Get product by EAN/GTIN
   *
   * @param string $ean Product EAN/GTIN
   * @param int|null $languageId Language ID (defaults to current language)
   * @return array|null Product data or null if not found
   */
  public function getProductByEan(string $ean, ?int $languageId = null): ?array
  {
    try {
      if ($languageId === null) {
        $languageId = Registry::get('Language')->getId();
      }
      
      $ecommerceApp = $this->ecommerceApp;
      $ClicShopping_Db = $ecommerceApp->db;
      
      // Query using Ecommerce app database
      $Qproduct = $ClicShopping_Db->prepare('SELECT 
                                  p.products_id as product_id,
                                  pd.products_name as name,
                                  p.products_price as price,
                                  p.products_model as model,
                                  p.products_ean as ean,
                                  p.products_quantity as stock
                                FROM :table_products p
                                INNER JOIN :table_products_description pd 
                                  ON p.products_id = pd.products_id
                                WHERE p.products_ean = :ean
                                AND pd.language_id = :language_id
                                LIMIT 1');
      
      $Qproduct->bindValue(':ean', $ean);
      $Qproduct->bindInt(':language_id', $languageId);
      $Qproduct->execute();
      
      $result = $Qproduct->fetch();
      
      return $result ?: null;
      
    } catch (\Exception $e) {
      if ($this->logger !== null) {
        $this->logger->logSecurityEvent(
          "ProductHelper: Error getting product by EAN {$ean}: " . $e->getMessage(),
          'error'
        );
      }
      return null;
    }
  }

  /**
   * Search product by name using SQL LIKE
   *
   * Searches for products matching the given query string.
   * Removes common words and uses fuzzy matching with LIKE.
   * Returns the best match based on exact match, starts with, or contains.
   *
   * @param string $query Search query
   * @param int|null $languageId Language ID (defaults to current language)
   * @return array|null Product data or null if not found
   */
  public function searchProductByName(string $query, ?int $languageId = null): ?array
  {
    try {
      if ($languageId === null) {
        $languageId = Registry::get('Language')->getId();
      }
      
      // Extract potential product name from query
      // Remove common words like "stock", "price", "compare", etc.
      $cleanQuery = preg_replace('/\b(stock|price|compare|competitors?|show|give|display|of|the|a|an)\b/i', '', $query);
      $cleanQuery = trim($cleanQuery);

      if (empty($cleanQuery)) {
        return null;
      }

      $ecommerceApp = $this->ecommerceApp;
      $ClicShopping_Db = $ecommerceApp->db;
      
      // Query using Ecommerce app database
      $Qproduct = $ClicShopping_Db->prepare('
        SELECT p.products_id as product_id,
               pd.products_name as name,
               p.products_price as price,
               p.products_model as model,
               p.products_ean as ean,
               p.products_quantity as stock
        FROM :table_products p
        INNER JOIN :table_products_description pd ON p.products_id = pd.products_id
        WHERE (pd.products_name LIKE :search_term
           OR p.products_model LIKE :search_term)
          AND pd.language_id = :language_id
          AND p.products_status = 1
        ORDER BY 
          CASE 
            WHEN pd.products_name = :exact_term THEN 1
            WHEN pd.products_name LIKE :starts_with THEN 2
            ELSE 3
          END
        LIMIT 1
      ');

      $searchTerm = '%' . $cleanQuery . '%';
      $startsWith = $cleanQuery . '%';

      $Qproduct->bindValue(':search_term', $searchTerm);
      $Qproduct->bindValue(':exact_term', $cleanQuery);
      $Qproduct->bindValue(':starts_with', $startsWith);
      $Qproduct->bindInt(':language_id', $languageId);
      $Qproduct->execute();

      if ($Qproduct->fetch()) {
        $result = [
          'product_id' => $Qproduct->valueInt('product_id'),
          'name' => $Qproduct->value('name'),
          'price' => $Qproduct->valueDecimal('price'),
          'model' => $Qproduct->value('model'),
          'ean' => $Qproduct->value('ean'),
          'stock' => $Qproduct->valueInt('stock'),
        ];
        
        if ($this->debug && $this->logger !== null) {
          $this->logger->logSecurityEvent(
            "ProductHelper: Found product by name search '{$query}': {$result['name']} (ID: {$result['product_id']})",
            'info'
          );
        }
        
        return $result;
      }

      if ($this->debug && $this->logger !== null) {
        $this->logger->logSecurityEvent(
          "ProductHelper: No product found for name search '{$query}'",
          'info'
        );
      }

      return null;

    } catch (\Exception $e) {
      if ($this->logger !== null) {
        $this->logger->logSecurityEvent(
          "ProductHelper: Error searching product by name '{$query}': " . $e->getMessage(),
          'error'
        );
      }
      return null;
    }
  }
}

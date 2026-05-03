<?php
/**
 * FaqRepository
 *
 * Data access layer for FAQ operations.
 * Handles CRUD operations on products_description_faq table.
 *
 * @package ClicShopping
 * @subpackage AI\Ecommerce\FAQ
 * @version 1.0
 * @date 2026-05-03
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * FaqRepository Class
 *
 * Provides data access methods for FAQ content stored in the
 * products_description_faq table. Handles all CRUD operations
 * with proper error handling and prepared statements.
 *
 * Usage:
 * ```php
 * $repository = new FaqRepository();
 * $faq = $repository->getFaq($productId, $languageId);
 * if ($faq) {
 *   echo $faq['faq_content'];
 * }
 * ```
 */
class FaqRepository
{
  /**
   * Database connection instance
   *
   * @var mixed
   */
  private mixed $db;

  /**
   * Constructor
   *
   * Initializes database connection.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Get FAQ content for a product in a specific language
   *
   * Retrieves all FAQ data including content, description, and timestamps
   * for a specific product and language combination.
   *
   * @param int $productId Product ID
   * @param int $languageId Language ID
   * @return array|null FAQ data array or null if not found
   *
   * @example
   * ```php
   * $repository = new FaqRepository();
   * $faq = $repository->getFaq(123, 1);
   * if ($faq) {
   *   echo "FAQ Content: " . $faq['faq_content'];
   *   echo "Last Modified: " . $faq['date_modified'];
   * }
   * ```
   */
  public function getFaq(int $productId, int $languageId): ?array
  {
    try {
      $Qfaq = $this->db->prepare('SELECT * 
                                   FROM :table_products_description_faq 
                                   WHERE products_id = :products_id 
                                   AND language_id = :language_id 
                                   LIMIT 1');
      $Qfaq->bindInt(':products_id', $productId);
      $Qfaq->bindInt(':language_id', $languageId);
      $Qfaq->execute();

      $result = $Qfaq->fetch();

      return $result ?: null;

    } catch (\Exception $e) {
      error_log('[FaqRepository] Error in getFaq: ' . $e->getMessage());
      return null;
    }
  }

  /**
   * Save FAQ content
   *
   * Inserts new FAQ or updates existing FAQ using upsert pattern.
   * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic operation.
   *
   * @param int $productId Product ID
   * @param int $languageId Language ID
   * @param string $faqContent FAQ JSON content
   * @param string $faqDescription Plain text description
   * @return bool True on success, false on failure
   *
   * @example
   * ```php
   * $repository = new FaqRepository();
   * $faqContent = '[{"q":"Question?","a":"Answer."}]';
   * $faqDescription = 'Question? Answer.';
   * $success = $repository->saveFaq(123, 1, $faqContent, $faqDescription);
   * ```
   */
  public function saveFaq(int $productId, int $languageId, string $faqContent, string $faqDescription): bool
  {
    try {
      // Check if FAQ already exists
      $Qcheck = $this->db->prepare('SELECT id 
                                     FROM :table_products_description_faq 
                                     WHERE products_id = :products_id 
                                     AND language_id = :language_id 
                                     LIMIT 1');
      $Qcheck->bindInt(':products_id', $productId);
      $Qcheck->bindInt(':language_id', $languageId);
      $Qcheck->execute();
      
      $existing = $Qcheck->fetch();

      // Prepare data array
      $sql_data_array = [
        'products_id' => $productId,
        'language_id' => $languageId,
        'faq_content' => $faqContent,
        'faq_description' => $faqDescription
      ];

      if ($existing) {
        // Update existing record
        $sql_data_array['date_modified'] = 'now()';
        
        $update_where = [
          'id' => (int)$existing['id']
        ];
        
        $this->db->save('products_description_faq', $sql_data_array, $update_where);
      } else {
        // Insert new record
        $sql_data_array['date_added'] = 'now()';
        $sql_data_array['date_modified'] = 'now()';
        
        $this->db->save('products_description_faq', $sql_data_array);
      }

      return true;

    } catch (\Exception $e) {
      error_log('[FaqRepository] Error in saveFaq: ' . $e->getMessage());
      error_log('[FaqRepository] Product ID: ' . $productId . ', Language ID: ' . $languageId);
      return false;
    }
  }

  /**
   * Delete FAQ content for a product
   *
   * Deletes FAQ for a specific language or all languages if languageId is null.
   * This allows bulk deletion when a product is removed.
   *
   * @param int $productId Product ID
   * @param int|null $languageId Language ID (null = delete all languages)
   * @return bool True on success, false on failure
   *
   * @example
   * ```php
   * $repository = new FaqRepository();
   * 
   * // Delete FAQ for specific language
   * $repository->deleteFaq(123, 1);
   * 
   * // Delete FAQ for all languages
   * $repository->deleteFaq(123, null);
   * ```
   */
  public function deleteFaq(int $productId, ?int $languageId = null): bool
  {
    try {
      // Prepare WHERE clause
      $where_array = ['products_id' => $productId];
      
      if ($languageId !== null) {
        // Delete FAQ for specific language
        $where_array['language_id'] = $languageId;
      }
      // If languageId is null, delete for all languages (only products_id in WHERE)

      $this->db->delete('products_description_faq', $where_array);

      return true;

    } catch (\Exception $e) {
      error_log('[FaqRepository] Error in deleteFaq: ' . $e->getMessage());
      error_log('[FaqRepository] Product ID: ' . $productId . ', Language ID: ' . ($languageId ?? 'all'));
      return false;
    }
  }

  /**
   * Check if FAQ exists for a product
   *
   * Quick check to determine if FAQ content exists for a specific
   * product and language combination without fetching the full data.
   *
   * @param int $productId Product ID
   * @param int $languageId Language ID
   * @return bool True if FAQ exists, false otherwise
   *
   * @example
   * ```php
   * $repository = new FaqRepository();
   * if ($repository->hasFaq(123, 1)) {
   *   echo "FAQ exists for this product";
   * }
   * ```
   */
  public function hasFaq(int $productId, int $languageId): bool
  {
    try {
      $Qcheck = $this->db->prepare('SELECT COUNT(*) as count 
                                     FROM :table_products_description_faq 
                                     WHERE products_id = :products_id 
                                     AND language_id = :language_id');
      $Qcheck->bindInt(':products_id', $productId);
      $Qcheck->bindInt(':language_id', $languageId);
      $Qcheck->execute();

      $result = $Qcheck->fetch();

      return ($result && $result['count'] > 0);

    } catch (\Exception $e) {
      error_log('[FaqRepository] Error in hasFaq: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Get all products without FAQ in a specific language
   *
   * Retrieves a list of active product IDs that don't have FAQ content
   * for the specified language. Useful for batch processing and cron jobs.
   *
   * @param int $languageId Language ID
   * @param int $limit Maximum number of products to return (default 10)
   * @param int $offset Offset for pagination (default 0)
   * @return array Array of product IDs without FAQ
   *
   * @example
   * ```php
   * $repository = new FaqRepository();
   * 
   * // Get first 10 products without FAQ
   * $productIds = $repository->getProductsWithoutFaq(1, 10, 0);
   * 
   * // Get next 10 products (pagination)
   * $productIds = $repository->getProductsWithoutFaq(1, 10, 10);
   * 
   * foreach ($productIds as $productId) {
   *   echo "Product {$productId} needs FAQ\n";
   * }
   * ```
   */
  public function getProductsWithoutFaq(int $languageId, int $limit = 10, int $offset = 0): array
  {
    try {
      $sql = 'SELECT p.products_id
              FROM :table_products p
              LEFT JOIN :table_products_description_faq f 
                ON p.products_id = f.products_id 
                AND f.language_id = :language_id
              WHERE f.id IS NULL
                AND p.products_status = 1
              ORDER BY p.products_id ASC
              LIMIT :limit OFFSET :offset';

      $Qproducts = $this->db->prepare($sql);
      $Qproducts->bindInt(':language_id', $languageId);
      $Qproducts->bindInt(':limit', $limit);
      $Qproducts->bindInt(':offset', $offset);
      $Qproducts->execute();

      $productIds = [];
      while ($product = $Qproducts->fetch()) {
        $productIds[] = (int)$product['products_id'];
      }

      return $productIds;

    } catch (\Exception $e) {
      error_log('[FaqRepository] Error in getProductsWithoutFaq: ' . $e->getMessage());
      error_log('[FaqRepository] Language ID: ' . $languageId . ', Limit: ' . $limit . ', Offset: ' . $offset);
      return [];
    }
  }

  /**
   * Get total count of products without FAQ
   *
   * Returns the total number of active products that don't have FAQ
   * for the specified language. Useful for pagination and progress tracking.
   *
   * @param int $languageId Language ID
   * @return int Total count of products without FAQ
   *
   * @example
   * ```php
   * $repository = new FaqRepository();
   * $total = $repository->getProductsWithoutFaqCount(1);
   * echo "Total products without FAQ: {$total}";
   * ```
   */
  public function getProductsWithoutFaqCount(int $languageId): int
  {
    try {
      $sql = 'SELECT COUNT(*) as count
              FROM :table_products p
              LEFT JOIN :table_products_description_faq f 
                ON p.products_id = f.products_id 
                AND f.language_id = :language_id
              WHERE f.id IS NULL
                AND p.products_status = 1';

      $Qcount = $this->db->prepare($sql);
      $Qcount->bindInt(':language_id', $languageId);
      $Qcount->execute();

      $result = $Qcount->fetch();

      return $result ? (int)$result['count'] : 0;

    } catch (\Exception $e) {
      error_log('[FaqRepository] Error in getProductsWithoutFaqCount: ' . $e->getMessage());
      return 0;
    }
  }

  /**
   * Get all FAQ entries for a product (all languages)
   *
   * Retrieves FAQ content for all languages for a specific product.
   * Useful for multilingual management and synchronization.
   *
   * @param int $productId Product ID
   * @return array Array of FAQ data indexed by language ID
   *
   * @example
   * ```php
   * $repository = new FaqRepository();
   * $allFaqs = $repository->getAllFaqsForProduct(123);
   * foreach ($allFaqs as $languageId => $faq) {
   *   echo "Language {$languageId}: {$faq['faq_content']}\n";
   * }
   * ```
   */
  public function getAllFaqsForProduct(int $productId): array
  {
    try {
      $Qfaqs = $this->db->prepare('SELECT * 
                                    FROM :table_products_description_faq 
                                    WHERE products_id = :products_id 
                                    ORDER BY language_id ASC');
      $Qfaqs->bindInt(':products_id', $productId);
      $Qfaqs->execute();

      $faqs = [];
      while ($faq = $Qfaqs->fetch()) {
        $faqs[$faq['language_id']] = $faq;
      }

      return $faqs;

    } catch (\Exception $e) {
      error_log('[FaqRepository] Error in getAllFaqsForProduct: ' . $e->getMessage());
      return [];
    }
  }
}

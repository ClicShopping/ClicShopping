<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ\FaqParser;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ\FaqPrettyPrinter;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ\FaqRepository;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Marketing\SEO\Classes\ClicShoppingAdmin\SeoAdmin;

/**
 * SeoEntityAdapter
 *
 * Dynamic adapter to read/apply SEO fields for supported entity types
 * without duplicating logic. Extend the map to add new entities.
 */
class SeoEntityAdapter
{
  private const ENTITY_MAP = [
    'category' => [
      'table' => 'categories_description',
      'id_field' => 'categories_id',
      'language_field' => 'language_id',
      'fields' => [
        'name' => 'categories_name',
        'description' => 'categories_description',
        'meta_title' => 'categories_head_title_tag',
        'meta_description' => 'categories_head_desc_tag',
        'meta_keywords' => 'categories_head_keywords_tag',
        'seo_url' => 'categories_seo_url',
      ],
    ],
    'product' => [
      'table' => 'products_description',
      'id_field' => 'products_id',
      'language_field' => 'language_id',
      'fields' => [
        'name' => 'products_name',
        'description' => 'products_description',
        'summary' => 'products_description_summary',
        'meta_title' => 'products_head_title_tag',
        'meta_description' => 'products_head_desc_tag',
        'meta_keywords' => 'products_head_keywords_tag',
        'seo_url' => 'products_seo_url',
      ],
    ],
  ];
  private mixed $db;
  private string $entityType;
  private array $config;
  private mixed $apps;
  private ?array $cachedFieldMap = null;

  public function __construct(string $entityType)
  {
    $this->db = Registry::get('Db');
    $this->entityType = strtolower(trim($entityType));
    $this->config = self::ENTITY_MAP[$this->entityType] ?? [];
    
    // Initialize Ecommerce app for multilingual support
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new \ClicShopping\Apps\AI\Ecommerce\Ecommerce());
    }
    $this->apps = Registry::get('Ecommerce');
    
    // Load language definitions for structured content
    $this->apps->loadDefinitions('Sites/ClicShoppingAdmin/seo_structured_content');
  }

  public function getEntityType(): string
  {
    return $this->entityType;
  }

  public function getCurrentData(int $entityId, int $languageId): ?array
  {
    if (!$this->isSupported()) {
      return null;
    }

    $table = $this->config['table'];
    $idField = $this->config['id_field'];
    $langField = $this->config['language_field'];
    $fieldMap = $this->getAvailableFieldMap();

    if (empty($fieldMap)) {
      return null;
    }

    $columns = array_values($fieldMap);
    $sql = 'SELECT ' . implode(', ', $columns) . '
            FROM :table_' . $table . '
            WHERE ' . $idField . ' = :entity_id
              AND ' . $langField . ' = :language_id
            LIMIT 1';

    $stmt = $this->db->prepare($sql);
    $stmt->bindInt(':entity_id', $entityId);
    $stmt->bindInt(':language_id', $languageId);
    $stmt->execute();

    $row = $stmt->fetch();

    if (!$row) {
      return null;
    }

    $data = [];
    foreach ($fieldMap as $key => $column) {
      $data[$key] = $row[$column] ?? null;
    }

    return $data;
  }

  public function isSupported(): bool
  {
    return !empty($this->config);
  }

  public function getAvailableFieldMap(): array
  {
    if ($this->cachedFieldMap !== null) {
      return $this->cachedFieldMap;
    }

    if (!$this->isSupported()) {
      $this->cachedFieldMap = [];
      return [];
    }

    $table = $this->config['table'] ?? '';
    $fields = $this->config['fields'] ?? [];
    $available = [];

    foreach ($fields as $key => $column) {
      $Qcheck = $this->db->prepare('SHOW COLUMNS FROM :table_' . $table . ' LIKE :column');
      $Qcheck->bindValue(':column', $column);
      $Qcheck->execute();

      if ($Qcheck->fetch()) {
        $available[$key] = $column;
      }
    }

    $this->cachedFieldMap = $available;
    return $available;
  }

  public function applySeoChanges(int $entityId, int $languageId, array $changes, bool $normalize = true): bool
  {
    if (!$this->isSupported()) {
      return false;
    }

    $table    = $this->config['table'];
    $idField  = $this->config['id_field'];
    $langField = $this->config['language_field'];
    $fieldMap = $this->getAvailableFieldMap();

    if (empty($fieldMap)) {
      return false;
    }

    $sqlData = [];

    foreach ($changes as $key => $value) {
      if (!isset($fieldMap[$key])) {
        continue;
      }

      $column = $fieldMap[$key];

      if ($normalize) {
        switch ($key) {
          case 'meta_title':
            $value = SeoAdmin::normalizeSeoTitle((string)$value);
            break;
          case 'meta_description':
            $value = SeoAdmin::normalizeSeoDescription((string)$value);
            break;
          case 'meta_keywords':
            $value = SeoAdmin::normalizeSeoKeywords((string)$value);
            break;
          default:
            $value = (string)$value;
        }
      } else {
        $value = (string)$value;
      }

      $sqlData[$column] = $value;
    }

    if (empty($sqlData)) {
      return false;
    }

    // Build an explicit UPDATE with BOTH the entity id AND the language_id in the
    // WHERE clause.  Using db->save() is intentionally avoided here: ClicShopping's
    // save() helper may generate a WHERE that only uses the primary key without the
    // language column, which would overwrite every language row for this entity
    // (e.g. erasing the EN row when saving FR content).
    $prefix    = CLICSHOPPING::getConfig('db_table_prefix');
    $fullTable = $prefix . $table;

    $setClauses   = [];
    $boundValues  = [];

    foreach ($sqlData as $column => $value) {
      $placeholder    = ':set_' . $column;
      $setClauses[]   = $column . ' = ' . $placeholder;
      $boundValues[$placeholder] = $value;
    }

    $sql = 'UPDATE ' . $fullTable
         . ' SET ' . implode(', ', $setClauses)
         . ' WHERE ' . $idField  . ' = :where_entity_id'
         . '   AND ' . $langField . ' = :where_language_id';

    $stmt = $this->db->prepare($sql);

    foreach ($boundValues as $placeholder => $value) {
      $stmt->bindValue($placeholder, $value);
    }

    $stmt->bindInt(':where_entity_id',   $entityId);
    $stmt->bindInt(':where_language_id', $languageId);

    $stmt->execute();

    // Handle FAQ storage separately (if FAQ data is provided)
    if (isset($changes['faq']) && is_array($changes['faq']) && !empty($changes['faq'])) {
      $faqSuccess = $this->saveFaqContent($entityId, $languageId, $changes['faq']);
      
      if ($faqSuccess) {
        error_log('[SeoEntityAdapter] FAQ stored successfully for entity ' . $entityId . ', language ' . $languageId);
      } else {
        error_log('[SeoEntityAdapter] FAQ storage failed for entity ' . $entityId . ', language ' . $languageId);
        // Note: We don't fail the entire operation if FAQ storage fails
        // SEO fields were already saved successfully
      }
    }

    return true;
  }

  /**
   * Save FAQ content to products_description_faq table
   *
   * Validates FAQ structure, formats content, and stores it in the
   * dedicated FAQ table. Uses FaqParser for validation, FaqPrettyPrinter
   * for formatting, and FaqRepository for database operations.
   *
   * @param int $entityId Product ID
   * @param int $languageId Language ID
   * @param array $faqData FAQ array with 'q' and 'a' keys
   * @return bool True on success, false on failure
   *
   * @example
   * ```php
   * $adapter = new SeoEntityAdapter('product');
   * $faqData = [
   *   ['q' => 'What is this product?', 'a' => 'This is a great product.'],
   *   ['q' => 'How to use it?', 'a' => 'Follow the instructions.']
   * ];
   * $success = $adapter->saveFaqContent(123, 1, $faqData);
   * ```
   */
  private function saveFaqContent(int $entityId, int $languageId, array $faqData): bool
  {
    try {
      // Initialize FAQ classes
      $parser = new FaqParser();
      $printer = new FaqPrettyPrinter();
      $repository = new FaqRepository();

      // Validate FAQ structure
      $parseResult = $parser->parse(json_encode($faqData));
      if (!$parseResult['success']) {
        error_log('[SeoEntityAdapter] FAQ validation failed for product ' . $entityId . ', language ' . $languageId . ': ' . $parseResult['error']);
        return false;
      }

      // Format FAQ content as pretty-printed JSON
      $faqContent = $printer->print($parseResult['data']);

      // Generate plain text description from FAQ
      $faqDescription = $this->generateFaqDescription($parseResult['data']);

      // Save to database using FaqRepository
      $success = $repository->saveFaq($entityId, $languageId, $faqContent, $faqDescription);

      if ($success) {
        error_log('[SeoEntityAdapter] FAQ saved successfully for product ' . $entityId . ', language ' . $languageId);
      } else {
        error_log('[SeoEntityAdapter] FAQ save failed for product ' . $entityId . ', language ' . $languageId);
      }

      return $success;

    } catch (\Exception $e) {
      error_log('[SeoEntityAdapter] Exception in saveFaqContent: ' . $e->getMessage());
      error_log('[SeoEntityAdapter] Product ID: ' . $entityId . ', Language ID: ' . $languageId);
      return false;
    }
  }

  /**
   * Generate plain text description from FAQ data
   *
   * Creates a searchable plain text summary by concatenating all
   * questions and answers. Used for search indexing and quick previews.
   *
   * @param array $faqData FAQ array with 'q' and 'a' keys
   * @return string Plain text description
   *
   * @example
   * ```php
   * $faqData = [
   *   ['q' => 'What is this?', 'a' => 'A product.'],
   *   ['q' => 'How to use?', 'a' => 'Read manual.']
   * ];
   * $description = $this->generateFaqDescription($faqData);
   * // Returns: "What is this? A product. How to use? Read manual."
   * ```
   */
  private function generateFaqDescription(array $faqData): string
  {
    $description = '';
    foreach ($faqData as $item) {
      if (isset($item['q']) && isset($item['a'])) {
        $description .= $item['q'] . ' ' . $item['a'] . ' ';
      }
    }
    return trim($description);
  }

  public function normalizeChanges(array $proposal): array
  {
    $out = [];

    if (isset($proposal['meta_title'])) {
      $out['meta_title'] = (string)$proposal['meta_title'];
    }
    if (isset($proposal['meta_description'])) {
      $out['meta_description'] = (string)$proposal['meta_description'];
    }
    if (isset($proposal['meta_keywords'])) {
      $out['meta_keywords'] = (string)$proposal['meta_keywords'];
    }
    if (isset($proposal['summary'])) {
      $out['summary'] = (string)$proposal['summary'];
    }
    if (isset($proposal['description'])) {
      $out['description'] = (string)$proposal['description'];
    }
    if (isset($proposal['name'])) {
      $out['name'] = (string)$proposal['name'];
    }
    if (isset($proposal['seo_url'])) {
      $out['seo_url'] = (string)$proposal['seo_url'];
    }

    // Pass primary_keyword through for quality validation context.
    // SeoOptimizationAgent stores it under $output['notes']['primary_keyword'].
    // We surface it at the top level so the validator can tell the quality LLM
    // what the actual target keyword is, preventing false "placeholder" complaints.
    $primaryKeyword = $proposal['primary_keyword'] ?? $proposal['notes']['primary_keyword'] ?? '';
    if ($primaryKeyword !== '') {
      $out['primary_keyword'] = (string)$primaryKeyword;
    }

    // Pass schema_org_json through so SeoCodeValidationAgent can validate it.
    // Without this key the validator always reports "schema.org JSON-LD block is missing"
    // even when SeoOptimizationAgent has successfully generated it.
    if (isset($proposal['schema_org_json'])) {
      $out['schema_org_json'] = (string)$proposal['schema_org_json'];
    }

    // Pass structured content through for coherence/quality checks.
    if (isset($proposal['faq']) && is_array($proposal['faq'])) {
      $out['faq'] = $proposal['faq'];
    }
    if (isset($proposal['h2']) && is_array($proposal['h2'])) {
      $out['h2'] = $proposal['h2'];
    }

    return $out;
  }

  /**
   * Get language code from language_id using OM/Language
   *
   * @param int $languageId Language ID from database
   * @return string Language code (e.g., 'fr', 'en', 'es')
   */
  public function getLanguage(int $languageId): string
  {
    try {
      $language = Registry::get('Language');
      return $language->getLanguageCodeById($languageId);
    } catch (\Exception $e) {
      error_log("[SeoEntityAdapter] Failed to get language code for ID {$languageId}: " . $e->getMessage());
      return 'en'; // Fallback to English
    }
  }

  /**
   * Get language_id from entity data
   *
   * For entities with multilingual data, this returns the language_id
   * from the entity's description table.
   *
   * @param int $entityId Entity ID
   * @param int|null $defaultLanguageId Default language ID (uses current if null)
   * @return int Language ID
   */
  public function getLanguageId(int $entityId, ?int $defaultLanguageId = null): int
  {
    // If default provided, use it
    if ($defaultLanguageId !== null) {
      return $defaultLanguageId;
    }

    // Otherwise, get current language from Registry
    try {
      $language = Registry::get('Language');
      return $language->getId();
    } catch (\Exception $e) {
      error_log("[SeoEntityAdapter] Failed to get current language ID: " . $e->getMessage());
      return 1; // Fallback to English (id 1)
    }
  }

  /**
   * Get additional context for entity-specific data
   *
   * Returns entity-specific information that can be used for SEO generation.
   * This includes data beyond the standard SEO fields.
   *
   * @param int $entityId Entity ID
   * @param int $languageId Language ID
   * @return array Entity-specific context data
   */
  public function getAdditionalContext(int $entityId, int $languageId): array
  {
    if (!$this->isSupported()) {
      return [];
    }

    $context = [
      'entity_type' => $this->entityType,
      'entity_id' => $entityId,
      'language_id' => $languageId,
      'language_code' => $this->getLanguage($languageId),
    ];

    // Get current SEO data
    $currentData = $this->getCurrentData($entityId, $languageId);
    if ($currentData) {
      $context['current_data'] = $currentData;
    }

    // Add entity-specific context
    switch ($this->entityType) {
      case 'category':
        $context = array_merge($context, $this->getCategoryContext($entityId, $languageId));
        break;

      case 'product':
        $context = array_merge($context, $this->getProductContext($entityId, $languageId));
        break;
    }

    return $context;
  }

  /**
   * Get category-specific context
   *
   * @param int $categoryId Category ID
   * @param int $languageId Language ID
   * @return array Category context
   */
  private function getCategoryContext(int $categoryId, int $languageId): array
  {
    $context = [];

    try {
      // Get category hierarchy
      $sql = 'SELECT parent_id, sort_order
              FROM :table_categories
              WHERE categories_id = :category_id
              LIMIT 1';

      $stmt = $this->db->prepare($sql);
      $stmt->bindInt(':category_id', $categoryId);
      $stmt->execute();

      $row = $stmt->fetch();

      if ($row) {
        $context['parent_id'] = $row['parent_id'] ?? null;
        $context['sort_order'] = $row['sort_order'] ?? null;
      }

      // Get product count in category
      $sql = 'SELECT COUNT(*) as product_count
              FROM :table_products_to_categories
              WHERE categories_id = :category_id';

      $stmt = $this->db->prepare($sql);
      $stmt->bindInt(':category_id', $categoryId);
      $stmt->execute();

      $row = $stmt->fetch();

      if ($row) {
        $context['product_count'] = (int)($row['product_count'] ?? 0);
      }

    } catch (\Exception $e) {
      error_log("[SeoEntityAdapter] Failed to get category context: " . $e->getMessage());
    }

    return $context;
  }

  /**
   * Get product-specific context
   *
   * @param int $productId Product ID
   * @param int $languageId Language ID
   * @return array Product context
   */
  private function getProductContext(int $productId, int $languageId): array
  {
    $context = [];

    try {
      // Get product details
      $sql = 'SELECT p.products_model, p.products_price, p.products_quantity,
                     p.products_status, p.manufacturers_id
              FROM :table_products p
              WHERE p.products_id = :product_id
              LIMIT 1';

      $stmt = $this->db->prepare($sql);
      $stmt->bindInt(':product_id', $productId);
      $stmt->execute();

      $row = $stmt->fetch();

      if ($row) {
        $context['model'] = $row['products_model'] ?? null;
        $context['price'] = $row['products_price'] ?? null;
        $context['quantity'] = $row['products_quantity'] ?? null;
        $context['status'] = $row['products_status'] ?? null;
        $context['manufacturer_id'] = $row['manufacturers_id'] ?? null;
      }

      // Get manufacturer name if available
      if (!empty($context['manufacturer_id'])) {
        $sql = 'SELECT manufacturers_name
                FROM :table_manufacturers
                WHERE manufacturers_id = :manufacturer_id
                LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->bindInt(':manufacturer_id', $context['manufacturer_id']);
        $stmt->execute();

        $row = $stmt->fetch();

        if ($row) {
          $context['manufacturer_name'] = $row['manufacturers_name'] ?? null;
        }
      }

    } catch (\Exception $e) {
      error_log("[SeoEntityAdapter] Failed to get product context: " . $e->getMessage());
    }

    return $context;
  }
}

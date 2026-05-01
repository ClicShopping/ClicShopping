<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
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

  public function __construct(string $entityType)
  {
    $this->db = Registry::get('Db');
    $this->entityType = strtolower(trim($entityType));
    $this->config = self::ENTITY_MAP[$this->entityType] ?? [];
  }

  public function getEntityType(): string
  {
    return $this->entityType;
  }

  public function getTable(): ?string
  {
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');

    return $prefix . $this->config['table'] ?? null;
  }

  public function getIdField(): ?string
  {
    return $this->config['id_field'] ?? null;
  }

  public function getLanguageField(): ?string
  {
    return $this->config['language_field'] ?? null;
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
    if (!$this->isSupported()) {
      return [];
    }

    $table = $this->resolveTableName();
    $fields = $this->config['fields'] ?? [];
    $available = [];

    foreach ($fields as $key => $column) {
      if (DoctrineOrm::columnExists($table, $column)) {
        $available[$key] = $column;
      }
    }

    return $available;
  }

  private function resolveTableName(): string
  {
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    return $prefix . $this->config['table'];
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

    // Handle FAQ: append to description as structured HTML
    if (isset($changes['faq']) && is_array($changes['faq']) && !empty($changes['faq'])) {
      $faqHtml = $this->buildFaqHtml($changes['faq']);
      
      // If description is being updated, append FAQ to it
      if (isset($sqlData[$fieldMap['description']])) {
        $sqlData[$fieldMap['description']] .= "\n\n" . $faqHtml;
      } else {
        // Otherwise, load current description and append FAQ
        $currentData = $this->getCurrentData($entityId, $languageId);
        if ($currentData && isset($currentData['description'])) {
          $sqlData[$fieldMap['description']] = $currentData['description'] . "\n\n" . $faqHtml;
        }
      }
    }

    // Handle H2 headings: append to description as structured HTML
    if (isset($changes['h2']) && is_array($changes['h2']) && !empty($changes['h2'])) {
      $h2Html = $this->buildH2Html($changes['h2']);
      
      // If description is being updated, append H2 structure to it
      if (isset($sqlData[$fieldMap['description']])) {
        $sqlData[$fieldMap['description']] .= "\n\n" . $h2Html;
      } else {
        // Otherwise, load current description and append H2
        $currentData = $this->getCurrentData($entityId, $languageId);
        if ($currentData && isset($currentData['description'])) {
          $sqlData[$fieldMap['description']] = $currentData['description'] . "\n\n" . $h2Html;
        }
      }
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

    return true;
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

  /**
   * Build FAQ HTML from FAQ array
   *
   * Converts FAQ array structure to semantic HTML with Schema.org FAQPage markup
   *
   * @param array $faq FAQ array with 'q' and 'a' keys
   * @return string HTML markup for FAQ section
   */
  private function buildFaqHtml(array $faq): string
  {
    if (empty($faq)) {
      return '';
    }

    $html = '<div class="seo-faq" itemscope itemtype="https://schema.org/FAQPage">' . "\n";
    $html .= '<h2>Questions Fréquentes (FAQ)</h2>' . "\n";

    foreach ($faq as $item) {
      if (!isset($item['q']) || !isset($item['a'])) {
        continue;
      }

      $question = htmlspecialchars($item['q'], ENT_QUOTES, 'UTF-8');
      $answer = htmlspecialchars($item['a'], ENT_QUOTES, 'UTF-8');

      $html .= '<div class="faq-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">' . "\n";
      $html .= '  <h3 itemprop="name">' . $question . '</h3>' . "\n";
      $html .= '  <div itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">' . "\n";
      $html .= '    <p itemprop="text">' . $answer . '</p>' . "\n";
      $html .= '  </div>' . "\n";
      $html .= '</div>' . "\n";
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * Build H2 structure HTML from H2 array
   *
   * Converts H2 heading array to semantic HTML with proper heading hierarchy
   *
   * @param array $h2 H2 array with 'level', 'text', and 'anchor' keys
   * @return string HTML markup for heading structure
   */
  private function buildH2Html(array $h2): string
  {
    if (empty($h2)) {
      return '';
    }

    $html = '<div class="seo-content-structure">' . "\n";

    foreach ($h2 as $heading) {
      if (!isset($heading['level']) || !isset($heading['text'])) {
        continue;
      }

      $level = (int)$heading['level'];
      $text = htmlspecialchars($heading['text'], ENT_QUOTES, 'UTF-8');
      $anchor = isset($heading['anchor']) ? htmlspecialchars($heading['anchor'], ENT_QUOTES, 'UTF-8') : '';

      // Ensure level is between 2 and 6
      $level = max(2, min(6, $level));

      if ($anchor) {
        $html .= '<h' . $level . ' id="' . $anchor . '">' . $text . '</h' . $level . '>' . "\n";
      } else {
        $html .= '<h' . $level . '>' . $text . '</h' . $level . '>' . "\n";
      }
    }

    $html .= '</div>';

    return $html;
  }
}

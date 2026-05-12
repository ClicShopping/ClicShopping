<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\SubResultFormatters;


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Hash;
use ClicShopping\OM\Language;

/**
 * AbstractFormatter - Base class for all result formatters
 */

abstract class AbstractFormatter
{
  private static int $tableCounter = 0;
  protected bool $debug;
  protected bool $displaySql;
  private mixed $language;

  public function __construct(bool $debug = false, bool $displaySql = false)
  {
    $this->debug = $debug;
    $this->displaySql = $displaySql;

    If(!Registry::exists('Language')) {
      Registry::set('Language', new Language());
    }

    $this->language = Registry::get('Language');
  }

  /**
   * Format the results
   * @param array $results Results to format
   * @return array ['type' => 'formatted_results', 'content' => 'HTML']
   */
  abstract public function format(array $results): array;

  /**
   * Check if this formatter can handle the given results
   * @param array $results Results to check
   * @return bool
   */
  abstract public function canHandle(array $results): bool;

  /**
   * Pretty format SQL query
   */
  protected function prettySql(string $sql): string
  {
    $sql = preg_replace('/\s+/', ' ', trim($sql));
    $keywords = ['SELECT', 'FROM', 'JOIN', 'WHERE', 'GROUP BY', 'ORDER BY', 'LIMIT', 'ON', 'AND', 'OR'];

    foreach ($keywords as $kw) {
      $sql = preg_replace("/\b$kw\b/i", "\n$kw", $sql);
    }

    $sql = str_replace(',', ",\n    ", $sql);
    $sql = preg_replace("/\n{2,}/", "\n", $sql);

    return trim($sql);
  }

  /**
   * Generate complete HTML table from data array
   */
  protected function generateTable(array $data, string $cssClass = 'table table-bordered table-striped'): string
  {
    if (empty($data) || !is_array($data)) {
      return '';
    }

    $tableParts = $this->buildTableOpenTag($cssClass);
    $output = $tableParts['toolbar'] . $tableParts['table'];

    $firstRow = !empty($data) ? array_values($data)[0] : null;
    if (is_array($firstRow)) {
      $output .= $this->generateTableHeaders($firstRow);
      $output .= $this->generateTableRows($data);
    }

    $output .= "</table>";

    return $output;
  }

  /**
   * Build table attributes for bootstrap-table with a unique id.
   */
  protected function buildTableOpenTag(string $cssClass): array
  {
    $id = 'ai-table-' . (++self::$tableCounter);
    $toolbarId = $id . '-toolbar';
    $toolbar = "<div id=\"{$toolbarId}\" class=\"table-toolbar\"></div>";

    $table = "<table id=\"{$id}\""
      . " class=\"{$cssClass}\""
      . " data-toggle=\"table\""
      . " data-icons-prefix=\"bi\""
      . " data-icons=\"icons\""
      . " data-toolbar=\"#{$toolbarId}\""
      . " data-toolbar=\"#toolbar\""
      . " data-buttons-class=\"primary\""
      . " data-show-columns=\"true\""
      . " data-check-on-init=\"true\""
      . " data-show-export=\"true\">";
    return [
      'toolbar' => $toolbar,
      'table' => $table,
    ];
  }

  /**
   * Generate table headers from first row
   */
  protected function generateTableHeaders(array $firstRow): string
  {
    $filteredRow = $this->filterSystemMetadata($firstRow);

    $headers = "<thead><tr>";
    foreach (array_keys($filteredRow) as $key) {
      if (is_numeric($key)) {
        $displayKey = "Colonne " . ($key + 1);
        $dataField = "col_" . ($key + 1);
      } else {
        $displayKey = $this->mapColumnName($key);
        $dataField = (string)$key;
      }
      $headers .= "<th data-field=\"" . htmlspecialchars($dataField) . "\" data-sortable=\"true\">" . htmlspecialchars($displayKey) . "</th>";
    }
    $headers .= "</tr></thead>";
    return $headers;
  }

  /**
   * Filter out system metadata fields from data rows
   */
  protected function filterSystemMetadata(array $row): array
  {
    $systemFields = [
      'entity_id',
      'entity_type',
      'language_id',
      'timestamp',
      'user_id',
      'created_at',
      'updated_at',
      'metadata',
      '_entity_metadata',
      'internal_id',
      'system_id'
    ];

    // 🚨 CRITICAL: Filter out ID columns in global aggregations
    // When result has aggregation columns (AVG, SUM, COUNT, etc.) without GROUP BY,
    // we should NOT display ID columns (products_id, orders_id, etc.)
    $hasAggregation = false;
    $aggregationColumns = [];

    foreach (array_keys($row) as $key) {
      // Check if column name suggests it's an aggregation result
      if (preg_match('/(total|count|sum|avg|average|prix_moyen|moyenne|somme)/i', $key)) {
        $hasAggregation = true;
        $aggregationColumns[] = $key;
      }
    }

    // If this looks like a global aggregation (has aggregation column + only 1-2 columns total),
    // filter out ID columns
    $isGlobalAggregation = $hasAggregation && count($row) <= 3;

    if ($isGlobalAggregation) {
      // Add ID columns to system fields for global aggregations
      $systemFields = array_merge($systemFields, [
        'products_id',
        'orders_id',
        'customers_id',
        'categories_id',
        'manufacturers_id',
        'suppliers_id',
        'id'
      ]);
    }

    $filteredRow = [];

    foreach ($row as $key => $value) {
      if (!in_array($key, $systemFields, true)) {
        $filteredRow[$key] = $value;
      }
    }

    return $filteredRow;
  }

  /**
   * Map column names to user-friendly display names
   */
  protected function mapColumnName(string $columnName): string
  {
    $langKey = 'column_' . $columnName;

    $displayName = CLICSHOPPING::getDef($langKey);

    if ($this->debug) {
      error_log("[DEBUG] ================================================================================");
      error_log("[DEBUG] mapColumnName");
      error_log("[DEBUG] ================================================================================");
      error_log("[DEBUG] Final message length: " . $displayName);
    }

    if ($displayName && $displayName !== $langKey) {
      return $displayName;
    }

    return ucwords(str_replace('_', ' ', $columnName));
  }

  /**
   * Generate table rows from data
   */
  protected function generateTableRows(array $data): string
  {
    $rows = "<tbody>";

    foreach ($data as $row) {
      $rows .= "<tr>";
      $filteredRow = $this->filterSystemMetadata($row);

      foreach ($filteredRow as $key => $value) {
        $formattedValue = $this->formatCellValue($key, $value);
        $rows .= "<td>" . $formattedValue . "</td>";
      }
      $rows .= "</tr>";
    }
    $rows .= "</tbody>";

    return $rows;
  }

  /**
   * Format cell value based on column name
   */
  protected function formatCellValue(string $columnName, mixed $value): string
  {
    if ($value === null) return '-';
    if ($value === '') return '-';

    // Decrypt sensitive fields FIRST (before any other formatting)
    if (is_string($value)) {
      $value = Hash::displayDecryptedDataText($value);
    }

    // Quantities (must be tested FIRST before prices)
    if (preg_match('/(quantity|stock|sold|count|total_products|total_quantity|number|items)/i', $columnName)) {
      if (is_numeric($value)) {
        return number_format((int)$value, 0, ',', ' ');
      }
    }

    // Prices and amounts
    if (preg_match('/(price|amount|revenue|total_amount|subtotal|cost)/i', $columnName)) {
      if (is_numeric($value)) {
        $currency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '€';
        return number_format((float)$value, 2, ',', ' ') . ' ' . $currency;
      }
    }

    // Dates
    if (preg_match('/(date|datetime)/i', $columnName)) {
      if (is_numeric($value) && $value > 1000000000) {
        return date('d/m/Y H:i', (int)$value);
      }
      if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
        return date('d/m/Y H:i', strtotime($value));
      }
    }

    return htmlspecialchars((string)$value);
  }

  /**
   * Formats source attribution for display
   *
   * @param array $sourceAttribution Source attribution data
   * @return string Formatted HTML output with source information
   */
  protected function formatSourceAttribution(array $sourceAttribution): string
  {
    if (empty($sourceAttribution)) {
      return '';
    }

    $output = '<div class="source-attribution alert alert-info" style="margin-top: 10px; padding: 10px; border-left: 4px solid #17a2b8;">';
    $output .= '<h6 style="margin-top: 0;"><strong>📍 Source d\'Information</strong></h6>';
    
    // Main source type with icon
    $icon = $sourceAttribution['source_icon'] ?? '📄';
    $sourceType = $sourceAttribution['source_type'] ?? 'Unknown';
    $sourceDetails = $sourceAttribution['source_details'] ?? '';
    
    $output .= '<div style="margin-bottom: 5px;">';
    $output .= '<span style="font-size: 1.2em;">' . $icon . '</span> ';
    $output .= '<strong>' . htmlspecialchars($sourceType) . '</strong>';
    $output .= '</div>';
    
    if (!empty($sourceDetails)) {
      $output .= '<div style="font-size: 0.9em; color: #666; margin-bottom: 8px;">';
      $output .= htmlspecialchars($sourceDetails);
      $output .= '</div>';
    }
    
    // Additional details based on source type
    if (isset($sourceAttribution['table_name']) && $sourceAttribution['table_name'] !== 'database') {
      $output .= '<div style="font-size: 0.85em; color: #555;">';
      $output .= '📋 Table: <code>' . htmlspecialchars($sourceAttribution['table_name']) . '</code>';
      $output .= '</div>';
    }
    
    if (isset($sourceAttribution['document_count']) && $sourceAttribution['document_count'] > 0) {
      $output .= '<div style="font-size: 0.85em; color: #555;">';
      $output .= '📚 Documents: ' . $sourceAttribution['document_count'];
      $output .= '</div>';
    }
    
    if (isset($sourceAttribution['urls']) && is_array($sourceAttribution['urls']) && !empty($sourceAttribution['urls'])) {
      $output .= '<div style="font-size: 0.85em; color: #555; margin-top: 5px;">';
      $output .= '🔗 URLs: ';
      $urlCount = count($sourceAttribution['urls']);
      $output .= '<span class="badge badge-secondary">' . $urlCount . ' source(s)</span>';
      $output .= '</div>';
    }
    
    if (isset($sourceAttribution['sources']) && is_array($sourceAttribution['sources'])) {
      $output .= '<div style="font-size: 0.85em; color: #555; margin-top: 5px;">';
      $output .= '🔀 Sources multiples: ';
      $output .= '<ul style="margin: 5px 0; padding-left: 20px;">';
      foreach ($sourceAttribution['sources'] as $source) {
        $output .= '<li>' . htmlspecialchars($source) . '</li>';
      }
      $output .= '</ul>';
      $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Prompt\Providers;

use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\InterfacesAI\PromptPlaceholderProviderInterface;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\DashboardData;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\AI\Ecommerce\Config\EcommerceDefaults;

/**
 * DomainActionsProvider
 *
 * Renders `{{domain_actions}}`: the actions a deterministic engine of this domain already
 * decided, so the restitution proposes THEM instead of inventing prose. The measure comes from
 * the analytical path; the action comes from this store.
 *
 * READ ONLY by design — the analysis that fills the store also runs an executor able to change
 * a price, so a prompt build never triggers it. The block always carries the date of the last
 * analysis and the coverage: an action list without them reads as current when it is two
 * months old.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Prompt\Providers
 */
class DomainActionsProvider implements PromptPlaceholderProviderInterface
{
  public const TOKEN = '{{domain_actions}}';

  private mixed $language;
  private ?DashboardData $dashboardData;
  private bool $definitionsLoaded = false;

  /**
   * @param mixed $language Language service; defaults to the platform one (injectable for tests)
   * @param DashboardData|null $dashboardData Read-only store reader (injectable for tests)
   */
  public function __construct(mixed $language = null, ?DashboardData $dashboardData = null)
  {
    $this->language = $language;
    $this->dashboardData = $dashboardData;
  }

  /**
   * @return string The token this provider answers for
   */
  public function getToken(): string
  {
    return self::TOKEN;
  }

  /**
   * @return array<int, string> The store whose freshness this block depends on
   */
  public function getSourceTables(): array
  {
    return ['products_cockpit_ai_embedding'];
  }

  /**
   * @param int $languageId Language of the analyses to read - labels are stored per language
   * @param array|null $resultRows Rows the answer interprets; only their products keep their actions
   * @return string The action block, coverage included; the coverage alone when no action exists
   */
  public function render(int $languageId, ?array $resultRows = null): string
  {
    $reader = $this->dashboardData ??= new DashboardData();
    $products = $reader->getRecommendedActions($languageId, EcommerceDefaults::int('CLICSHOPPING_APP_ECOMMERCE_EC_PROMPT_MAX_PRODUCTS'));
    $kpis = $reader->getKpis($languageId);

    $coverage = $this->getDef('text_domain_actions_coverage', [
      'analysed' => (string)($kpis['total_products'] ?? 0),
      'catalogue' => (string)($kpis['catalogue_total'] ?? 0),
      'last' => (string)($kpis['last_analysis'] ?? ''),
    ]);

    if ($products === []) {
      return $this->getDef('text_domain_actions_empty') . "\n" . $coverage;
    }

    // ponytail: filters the store's first N products; query by the rows' ids once the analysed catalogue outgrows N.
    if ($resultRows !== null) {
      $products = self::productsInRows($products, $resultRows);

      if ($products === []) {
        return $this->getDef('text_domain_actions_none_in_rows') . "\n" . $coverage;
      }
    }

    $rows = [];

    foreach ($products as $product) {
      foreach ($product['actions'] as $action) {
        $rows[] = $this->getDef('text_domain_actions_row', [
          'product' => $product['product_name'],
          'id' => (string)$product['product_id'],
          'priority' => $action['priority'],
          'label' => $action['label'],
          'description' => $action['description'],
        ]);
      }
    }

    return $this->getDef('text_domain_actions_intro') . "\n" . implode("\n", $rows) . "\n" . $coverage;
  }

  /**
   * Keep the products the result rows carry, by id column or by a cell holding the product name.
   * An action for a product absent from the rows reads as a finding about it.
   *
   * @param array $products Products with their actions, from the store
   * @param array $rows Result rows
   * @return array Products present in the rows
   */
  private static function productsInRows(array $products, array $rows): array
  {
    $ids = [];
    $values = [];

    foreach ($rows as $row) {
      foreach ((array)$row as $column => $value) {
        if (!\is_scalar($value)) {
          continue;
        }

        if ($column === 'products_id' || $column === 'product_id') {
          $ids[(string)$value] = true;
        }

        $values[trim((string)$value)] = true;
      }
    }

    return array_values(array_filter($products, static fn(array $product): bool =>
      isset($ids[(string)$product['product_id']]) || isset($values[trim((string)$product['product_name'])])));
  }

  /**
   * @param string $key Language definition key
   * @param array $vars Interpolated variables
   * @return string Definition text
   */
  private function getDef(string $key, array $vars = []): string
  {
    if ($this->language === null) {
      $this->loadDefinitions();
      $this->language = Registry::get('Language');
    }

    return (string)$this->language->getDef($key, $vars);
  }

  /**
   * Load this provider's definitions once per instance.
   *
   * @return void
   */
  private function loadDefinitions(): void
  {
    if ($this->definitionsLoaded) {
      return;
    }

    $this->definitionsLoaded = true;
    DomainConfig::loadLanguageFile('rag_domain_actions');
  }
}

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
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\MetricCatalog;
use ClicShopping\OM\Registry;

/**
 * MetricCatalogProvider
 *
 * Renders `{{metric_catalog}}`: the name, grain, type and meaning of every metric the
 * analysis plan may name. The same catalogue is read as an array by the plan validator,
 * so the model and the engine can never disagree on what a metric is.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Prompt\Providers
 */
class MetricCatalogProvider implements PromptPlaceholderProviderInterface
{
  public const TOKEN = '{{metric_catalog}}';

  private mixed $language;
  private bool $definitionsLoaded = false;

  /**
   * @param mixed $language Language service; defaults to the platform one (injectable for tests)
   */
  public function __construct(mixed $language = null)
  {
    $this->language = $language;
  }

  /**
   * @return string The token this provider answers for
   */
  public function getToken(): string
  {
    return self::TOKEN;
  }

  /**
   * The catalogue is code, not data: no table content is rendered, so no freshness rule applies.
   *
   * @return array<int, string> Always empty
   */
  public function getSourceTables(): array
  {
    return [];
  }

  /**
   * @param int $languageId Language of the prompt being built
   * @return string One line per metric, empty when the catalogue is empty
   */
  public function render(int $languageId): string
  {
    $catalog = MetricCatalog::all();

    if ($catalog === []) {
      return '';
    }

    $rows = [];

    foreach ($catalog as $name => $entry) {
      $rows[] = $this->getDef('text_metric_catalog_row', [
        'name' => $name,
        'grain' => $entry['grain'],
        'type' => $entry['type'],
        'definition' => $this->getDef($entry['definition']),
      ]);
    }

    return $this->getDef('text_metric_catalog_intro') . "\n" . implode("\n", $rows);
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
    DomainConfig::loadLanguageFile('rag_metric_catalog');
  }
}

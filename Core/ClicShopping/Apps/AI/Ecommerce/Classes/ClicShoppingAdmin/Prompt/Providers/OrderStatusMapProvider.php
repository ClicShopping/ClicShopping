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
use ClicShopping\OM\Registry;

/**
 * OrderStatusMapProvider
 *
 * Renders `{{order_status_map}}`: the merchant-written meaning of every status
 * row of this install, read from the database at prompt-build time.
 *
 * Why it cannot be written in the prompt: the set of statuses is install-specific
 * and changes over time. A prompt that spells "3 = delivered" is wrong on the next
 * shop and on this one the day a status is added. The definition lives on the ROW
 * (see SQL-2) and this provider is what carries it to the model.
 *
 * Domain-specific by nature, hence its home in Apps/AI/Ecommerce/ — Core only
 * knows the token through {@see \ClicShopping\AI\RegistryAI\PromptPlaceholderRegistry}.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Prompt\Providers
 */
class OrderStatusMapProvider implements PromptPlaceholderProviderInterface
{
  public const TOKEN = '{{order_status_map}}';

  /**
   * The four status tables of the order family, in the order they are rendered.
   * Each entry: the table, its id column, its name column and its section label key.
   */
  private const TABLES = [
    ['table' => 'orders_status', 'section' => 'orders'],
    ['table' => 'orders_status_invoice', 'section' => 'invoice'],
    ['table' => 'orders_status_support', 'section' => 'support'],
    ['table' => 'orders_status_tracking', 'section' => 'tracking'],
  ];

  private bool $definitionsLoaded = false;

  /**
   * @return string The token this provider answers for
   */
  public function getToken(): string
  {
    return self::TOKEN;
  }

  /**
   * The status tables are never joined by the generated SQL — it filters `orders_status = 3`,
   * a column of `orders`. Declaring them here is what lets the freshness rule notice that the
   * merchant rewrote a definition.
   *
   * @return array<int, string> Unprefixed status tables
   */
  public function getSourceTables(): array
  {
    return array_column(self::TABLES, 'table');
  }

  /**
   * Build the map, one section per status table.
   *
   * @param int $languageId Language of the rows to read - the same one the generated SQL will filter on
   * @return string Rendered map, empty when no section could be read
   */
  public function render(int $languageId): string
  {
    $this->loadDefinitions();

    $sections = [];

    foreach (self::TABLES as $spec) {
      $rows = $this->readTable($spec['table'], $languageId);

      if ($rows === []) {
        continue;
      }

      $sections[] = $this->getDef('text_order_status_map_section_' . $spec['section']) . "\n" . implode("\n", $rows);
    }

    if ($sections === []) {
      return '';
    }

    return $this->getDef('text_order_status_map_intro') . "\n\n" . implode("\n\n", $sections);
  }

  /**
   * Read one status table for one language, formatted as prompt lines.
   * A table without its definition column, or unreadable, yields no section
   * rather than breaking the prompt build.
   *
   * @param string $table Status table name, without the install prefix
   * @param int $languageId Language to read
   * @return array<int, string> One formatted line per status row
   */
  private function readTable(string $table, int $languageId): array
  {
    $idColumn = $table . '_id';
    $nameColumn = $table . '_name';
    $definitionColumn = $table . '_definition';

    try {
      $Qstatus = Registry::get('Db')->prepare('select ' . $idColumn . ', ' . $nameColumn . ', ' . $definitionColumn . '
                                               from :table_' . $table . '
                                               where language_id = :language_id
                                               order by ' . $idColumn . '
                                              ');
      $Qstatus->bindInt(':language_id', $languageId);
      $Qstatus->execute();
    } catch (\Throwable $e) {
      error_log('[OrderStatusMapProvider] ' . $table . ' unreadable: ' . $e->getMessage());
      return [];
    }

    $rows = [];
    $template = $this->getDef('text_order_status_map_row');

    while ($Qstatus->fetch() !== false) {
      $definition = trim((string)$Qstatus->value($definitionColumn));

      // An empty definition is worse than no line: it invites the model to guess
      // again. The admin guard prevents it going forward; legacy rows may miss it.
      if ($definition === '') {
        continue;
      }

      $rows[] = str_replace(
        ['{{id}}', '{{name}}', '{{definition}}'],
        [(string)$Qstatus->valueInt($idColumn), (string)$Qstatus->value($nameColumn), $definition],
        $template
      );
    }

    return $rows;
  }

  /**
   * Load this provider's prompt fragments once per instance.
   *
   * @return void
   */
  private function loadDefinitions(): void
  {
    if ($this->definitionsLoaded) {
      return;
    }

    $this->definitionsLoaded = true;
    DomainConfig::loadLanguageFile('rag_order_status_map');
  }

  /**
   * @param string $key Language definition key
   * @return string Definition text
   */
  private function getDef(string $key): string
  {
    return (string)Registry::get('Language')->getDef($key);
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\DomainsAI\Analytics\Planning;

use ClicShopping\AI\Infrastructure\Prompt\PromptPlaceholders;

/**
 * HierarchySql
 *
 * Renders a domain's self-referencing HIERARCHY (node table with a parent key, items filed on
 * nodes): a recursive CTE giving every node its top-level ancestor and its full path, the scalar
 * subquery reading one level for an item, and the filter keeping the items of one named node's
 * whole sub-tree, matched on a whole path segment.
 *
 * The domain declares the tables; the shape is agnostic. Output keeps {{table_prefix}} and
 * {{language_id}} for the PromptPlaceholders chokepoint.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Planning
 */
final class HierarchySql
{
  public const ROOT = 'root_name';
  public const PATH = 'path_name';
  public const SEPARATOR = ' > ';
  public const MAX_VALUE_LENGTH = 128;

  /**
   * @param array{cte: string, node: array{table: string, key: string, parent: string, root: int}, name: array{table: string, key: string, column: string, language_scoped: bool}, membership: array{table: string, node: string, item: string}} $h
   * @return string `WITH RECURSIVE <cte> AS (...)`, to prefix the query
   */
  public static function cte(array $h): string
  {
    $prefix = PromptPlaceholders::TABLE_PREFIX;
    ['cte' => $cte, 'node' => $n, 'name' => $nm] = $h;
    $name = "JOIN {$prefix}{$nm['table']} cd ON cd.{$nm['key']} = c.{$n['key']}"
      . ($nm['language_scoped'] ? ' AND cd.language_id = ' . PromptPlaceholders::LANGUAGE_ID : '');

    return "WITH RECURSIVE {$cte} AS ("
      . "SELECT c.{$n['key']}, cd.{$nm['column']} AS " . self::ROOT . ", CAST(cd.{$nm['column']} AS CHAR(1000)) AS " . self::PATH
      . " FROM {$prefix}{$n['table']} c {$name} WHERE c.{$n['parent']} = " . (int)$n['root']
      . " UNION ALL SELECT c.{$n['key']}, t." . self::ROOT . ", CONCAT(t." . self::PATH . ", '" . self::SEPARATOR . "', cd.{$nm['column']})"
      . " FROM {$prefix}{$n['table']} c JOIN {$cte} t ON c.{$n['parent']} = t.{$n['key']} {$name})";
  }

  /**
   * @param array $h Hierarchy declaration (see cte())
   * @param string $level self::ROOT or self::PATH
   * @param string $item Item expression of the current row
   * @return string Scalar subquery: the item's node at that level, NULL when filed nowhere
   */
  public static function member(array $h, string $level, string $item): string
  {
    return "(SELECT MIN(t.{$level})" . self::filed($h, $item) . ')';
  }

  /**
   * @param array $h Hierarchy declaration (see cte())
   * @param string $item Item expression of the current row
   * @param string $value Node name as the question spells it
   * @return string|null EXISTS condition on the node's whole sub-tree, or null when the value
   *   cannot be carried safely (empty, too long, control character, placeholder brace)
   */
  public static function within(array $h, string $item, string $value): ?string
  {
    $value = trim($value);

    // Braces are refused: PromptPlaceholders resolves {{tokens}} after compilation, inside literals too.
    if ($value === '' || mb_strlen($value) > self::MAX_VALUE_LENGTH || preg_match('/[\x00-\x1F\x7F{}]/u', $value) !== 0) {
      return null;
    }

    $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    $literal = str_replace(['\\', "'"], ['\\\\', "''"], '%' . self::SEPARATOR . $like . self::SEPARATOR . '%');

    return 'EXISTS (SELECT 1' . self::filed($h, $item)
      . " AND CONCAT('" . self::SEPARATOR . "', t." . self::PATH . ", '" . self::SEPARATOR . "') LIKE '{$literal}')";
  }

  private static function filed(array $h, string $item): string
  {
    ['cte' => $cte, 'node' => $n, 'membership' => $m] = $h;

    return ' FROM ' . PromptPlaceholders::TABLE_PREFIX . "{$m['table']} m JOIN {$cte} t ON t.{$n['key']} = m.{$m['node']}"
      . " WHERE m.{$m['item']} = {$item}";
  }
}

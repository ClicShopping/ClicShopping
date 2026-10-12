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
 * EventAxisSql
 *
 * Renders a domain's SIGNED EVENT AXIS: every fact counted on its own date with weight 1, plus a
 * deduction leg (-1) dated when the fact reached a deducting state, read from its state history.
 * A fact whose state weighs 0 never enters. The domain declares the tables; the shape is agnostic.
 *
 * Output is the body of a derived table (`FROM (<body>) ev`), with {{table_prefix}} and
 * {{language_id}} left for the PromptPlaceholders chokepoint.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Planning
 */
final class EventAxisSql
{
  /**
   * @param array{fact: array{table: string, alias: string, key: string, date: string, state: string}, lines: array{table: string, alias: string, key: string}, weight: array{table: string, alias: string, key: string, column: string, language_scoped: bool}, history: array{table: string, alias: string, key: string, state: string, date: string}} $axis
   * @param array{grain: string, columns: array<string, string>, joins?: array<int, string>} $projection Columns are alias => expression; `order_line` joins the line table
   * @return string Both legs joined by UNION ALL, same projection in each
   */
  public static function render(array $axis, array $projection): string
  {
    $prefix = PromptPlaceholders::TABLE_PREFIX;
    ['fact' => $f, 'lines' => $l, 'weight' => $w, 'history' => $h] = $axis;

    $from = "FROM {$prefix}{$f['table']} {$f['alias']}";

    if ($projection['grain'] === 'order_line') {
      $from .= " JOIN {$prefix}{$l['table']} {$l['alias']} ON {$l['alias']}.{$l['key']} = {$f['alias']}.{$f['key']}";
    }

    $from .= " JOIN {$prefix}{$w['table']} {$w['alias']} ON {$w['alias']}.{$w['key']} = {$f['alias']}.{$f['state']}"
      . ($w['language_scoped'] ? " AND {$w['alias']}.language_id = " . PromptPlaceholders::LANGUAGE_ID : '');

    foreach ($projection['joins'] ?? [] as $join) {
      $from .= ' ' . $join;
    }

    $kept = [];
    $deducted = [];

    foreach ($projection['columns'] as $alias => $expression) {
      // UNION ALL takes its column names from the first leg only.
      $kept[] = str_ends_with($expression, '.' . $alias) ? $expression : "{$expression} AS {$alias}";
      $deducted[] = $expression;
    }

    $deductedOn = "(SELECT MIN({$h['alias']}.{$h['date']}) FROM {$prefix}{$h['table']} {$h['alias']}"
      . " WHERE {$h['alias']}.{$h['key']} = {$f['alias']}.{$f['key']} AND {$h['alias']}.{$h['state']} = {$f['alias']}.{$f['state']})";
    $weight = "{$w['alias']}.{$w['column']}";

    return 'SELECT ' . implode(', ', ["{$f['alias']}.{$f['date']} AS event_date", '1 AS sign', ...$kept])
      . " {$from} WHERE {$weight} <> 0"
      . ' UNION ALL SELECT ' . implode(', ', [$deductedOn, '-1', ...$deducted])
      . " {$from} WHERE {$weight} = -1";
  }
}

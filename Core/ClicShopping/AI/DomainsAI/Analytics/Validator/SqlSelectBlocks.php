<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Validator;

/**
 * SqlSelectBlocks
 *
 * Splits a SQL statement into its SELECT specs (one per SELECT, UNION members apart) and tells,
 * for any position, which SELECT block it belongs to. Shared by the post-generation checks.
 */
final class SqlSelectBlocks
{
  private const string KEYWORDS = '/\b(SELECT|FROM|WHERE|GROUP\s+BY|ORDER\s+BY|HAVING|LIMIT|UNION|WINDOW)\b/i';

  /**
   * @param string $sql Executable SQL
   * @return array{masked: string, blockAt: array<int, int>, specs: list<array{block: int, select: int, from: int, where: ?int, whereEnd: int, end: int}>}
   */
  public static function parse(string $sql): array
  {
    $masked = self::maskStrings($sql);
    [$blockAt, $ownAt, $blockEnd] = self::blocks($masked);

    return ['masked' => $masked, 'blockAt' => $blockAt, 'specs' => self::specs($masked, $blockAt, $ownAt, $blockEnd)];
  }

  /**
   * Same-length copy with string literal contents blanked, so keywords and parentheses inside
   * literals are never read as SQL.
   *
   * @param string $sql Executable SQL
   * @return string
   */
  public static function maskStrings(string $sql): string
  {
    return (string)preg_replace_callback(
      "/'(?:[^'\\\\]|\\\\.|'')*'/s",
      static fn(array $m): string => "'" . str_repeat(' ', strlen($m[0]) - 2) . "'",
      $sql
    );
  }

  /**
   * Per position: the innermost SELECT block (0 = top level, else its opening parenthesis) and
   * whether the position sits directly in that block rather than in a nested expression.
   *
   * @param string $masked SQL with literals blanked
   * @return array{0: array<int, int>, 1: array<int, bool>, 2: array<int, int>}
   */
  private static function blocks(string $masked): array
  {
    $len = strlen($masked);
    $blockAt = [];
    $ownAt = [];
    $blockEnd = [0 => $len];
    $stack = [];

    for ($i = 0; $i < $len; $i++) {
      $char = $masked[$i];

      if ($char === '(') {
        $stack[] = ['pos' => $i, 'select' => preg_match('/\G\s*(SELECT|WITH)\b/i', $masked, $m, 0, $i + 1) === 1];
      }

      $block = 0;
      for ($s = count($stack) - 1; $s >= 0; $s--) {
        if ($stack[$s]['select']) {
          $block = $stack[$s]['pos'];
          break;
        }
      }

      $blockAt[$i] = $block;
      $ownAt[$i] = $stack === [] || $stack[count($stack) - 1]['select'];

      if ($char === ')' && $stack !== []) {
        $closed = array_pop($stack);
        if ($closed['select']) {
          $blockEnd[$closed['pos']] = $i;
        }
      }
    }

    return [$blockAt, $ownAt, $blockEnd];
  }

  /**
   * One entry per SELECT (UNION members are separate specs): positions of its SELECT, FROM and
   * WHERE keywords, where that WHERE ends, and where the spec ends.
   *
   * @param string $masked SQL with literals blanked
   * @param array<int, int> $blockAt Innermost SELECT block per position
   * @param array<int, bool> $ownAt Whether the position sits directly in its block
   * @param array<int, int> $blockEnd Closing position per block
   * @return list<array{block: int, select: int, from: int, where: ?int, whereEnd: int, end: int}>
   */
  private static function specs(string $masked, array $blockAt, array $ownAt, array $blockEnd): array
  {
    preg_match_all(self::KEYWORDS, $masked, $matches, PREG_OFFSET_CAPTURE);

    $open = [];
    $specs = [];

    foreach ($matches[1] as [$word, $pos]) {
      if (!$ownAt[$pos]) {
        continue;
      }

      $block = $blockAt[$pos];
      $keyword = strtoupper((string)preg_replace('/\s+/', ' ', $word));
      $current = $open[$block] ?? null;

      if ($current !== null && $current['where'] !== null && $current['whereEnd'] === null) {
        $current['whereEnd'] = $pos;
      }

      if ($keyword === 'SELECT') {
        if ($current !== null) {
          $current['end'] = $pos;
          $specs[] = $current;
        }
        $current = ['block' => $block, 'select' => $pos, 'from' => null, 'where' => null, 'whereEnd' => null, 'end' => null];
      } elseif ($current !== null && $keyword === 'FROM' && $current['from'] === null) {
        $current['from'] = $pos;
      } elseif ($current !== null && $keyword === 'WHERE' && $current['where'] === null) {
        $current['where'] = $pos;
      }

      if ($current !== null) {
        $open[$block] = $current;
      }
    }

    array_push($specs, ...array_values($open));

    $complete = [];
    foreach ($specs as $spec) {
      if ($spec['from'] === null) {
        continue;
      }
      $spec['end'] ??= $blockEnd[$spec['block']] ?? strlen($masked);
      $spec['whereEnd'] ??= $spec['end'];
      $complete[] = $spec;
    }

    return $complete;
  }
}

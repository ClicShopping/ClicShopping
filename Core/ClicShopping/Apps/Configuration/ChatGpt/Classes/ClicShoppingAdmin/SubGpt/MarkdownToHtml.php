<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

/**
 * Converts the LLM answer (GitHub-flavoured Markdown) to HTML for the chat surface.
 *
 * The chat renders text_response with innerHTML (DOMPurify + bootstrap-table); it has no Markdown
 * parser, so a Markdown answer showed raw. This targets the narrow subset the model emits: pipe
 * tables, bold/italic, links, inline code and bullet lists. Content that is already HTML is passed
 * through unchanged, so a response formatted upstream (web search, HTML formatters) is never
 * double-processed.
 */
class MarkdownToHtml
{
  /**
   * @param string $text The answer body, Markdown or already-HTML.
   * @return string HTML safe to inject (still sanitized client-side by DOMPurify).
   */
  public static function convert(string $text): string
  {
    if ($text === '') {
      return '';
    }

    // Already rendered upstream: leave it untouched (no double-processing).
    if (preg_match('/<(table|div|p|ul|ol|blockquote|thead|tbody)\b/i', $text) === 1) {
      return $text;
    }

    $blocks = preg_split('/\n[ \t]*\n/', trim($text));
    $html = [];

    foreach ($blocks as $block) {
      $lines = explode("\n", trim($block));

      if (self::isTable($lines)) {
        $html[] = self::renderTable($lines);
      } elseif (self::isList($lines)) {
        $html[] = self::renderList($lines);
      } else {
        $html[] = '<p>' . implode('<br>', array_map([self::class, 'inline'], $lines)) . '</p>';
      }
    }

    return implode("\n", $html);
  }

  /**
   * A table is a header row plus a separator row of dashes (GFM).
   */
  private static function isTable(array $lines): bool
  {
    return count($lines) >= 2
      && str_contains($lines[0], '|')
      && preg_match('/^\s*\|?[\s:|-]+\|?\s*$/', $lines[1]) === 1
      && str_contains($lines[1], '-');
  }

  private static function isList(array $lines): bool
  {
    foreach ($lines as $line) {
      if (preg_match('/^\s*[-*]\s+/', $line) !== 1) {
        return false;
      }
    }

    return $lines !== [];
  }

  /**
   * Splits a `| a | b |` row into trimmed cells, dropping the empty edges from the outer pipes.
   *
   * @return array<int, string>
   */
  private static function cells(string $row): array
  {
    $row = trim($row);
    $row = preg_replace('/^\||\|$/', '', $row);

    return array_map('trim', explode('|', $row));
  }

  private static function renderTable(array $lines): string
  {
    $header = self::cells($lines[0]);
    $aligns = array_map(static function (string $spec): string {
      $spec = trim($spec);
      $right = str_ends_with($spec, ':');
      $left = str_starts_with($spec, ':');

      return match (true) {
        $left && $right => ' style="text-align:center"',
        $right => ' style="text-align:right"',
        default => '',
      };
    }, self::cells($lines[1]));

    $out = '<table class="table table-striped table-bordered"><thead><tr>';

    foreach ($header as $i => $cell) {
      $out .= '<th' . ($aligns[$i] ?? '') . '>' . self::inline($cell) . '</th>';
    }

    $out .= '</tr></thead><tbody>';

    foreach (array_slice($lines, 2) as $line) {
      if (trim($line) === '') {
        continue;
      }

      $out .= '<tr>';

      foreach (self::cells($line) as $i => $cell) {
        $out .= '<td' . ($aligns[$i] ?? '') . '>' . self::inline($cell) . '</td>';
      }

      $out .= '</tr>';
    }

    return $out . '</tbody></table>';
  }

  private static function renderList(array $lines): string
  {
    $out = '<ul>';

    foreach ($lines as $line) {
      $out .= '<li>' . self::inline(preg_replace('/^\s*[-*]\s+/', '', $line)) . '</li>';
    }

    return $out . '</ul>';
  }

  /**
   * Inline formatting on already-escaped text: bold, italic, inline code, links.
   */
  private static function inline(string $text): string
  {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    // [label](http…) — href limited to http/https so htmlspecialchars-escaped quotes stay safe.
    $text = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $text);

    return $text;
  }
}

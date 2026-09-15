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
 * tables, bold/italic, links, inline code and bullet lists.
 *
 * A response formatted upstream is HTML that still CARRIES Markdown: the formatters embed the model
 * prose verbatim. So the Markdown is converted inside the text runs, never on the tags, and never
 * twice. Text already inside HTML is already escaped — escaping it again would show `&amp;amp;`.
 */
class MarkdownToHtml
{
  /** Tags whose content is markup or verbatim, never Markdown. */
  private const OPAQUE = 'pre|code|script|style|textarea|table|thead|tbody|tr|th|td';

  /**
   * @param string $text The answer body, Markdown or already-HTML.
   * @return string HTML safe to inject (still sanitized client-side by DOMPurify).
   */
  public static function convert(string $text): string
  {
    if ($text === '') {
      return '';
    }

    if (preg_match('/<(table|div|p|ul|ol|blockquote|thead|tbody)\b/i', $text) === 1) {
      return self::convertInsideHtml($text);
    }

    return self::renderBody($text, true, true);
  }

  /**
   * Convert the Markdown carried by the text runs of an HTML document, leaving tags alone.
   *
   * Paragraphs are not re-wrapped and newlines are kept: the caller still applies nl2br, and
   * the surrounding markup already decides the layout.
   */
  private static function convertInsideHtml(string $html): string
  {
    $parts = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

    if ($parts === false) {
      return $html;
    }

    $out = '';
    $opaque = 0;

    foreach ($parts as $part) {
      if ($part === '') {
        continue;
      }

      if ($part[0] === '<') {
        if (preg_match('#^<(/?)(' . self::OPAQUE . ')\b#i', $part, $m) === 1) {
          $opaque = $m[1] === '/' ? max(0, $opaque - 1) : $opaque + 1;
        }

        $out .= $part;
        continue;
      }

      $out .= ($opaque > 0 || trim($part) === '') ? $part : self::renderBody($part, false, false);
    }

    return $out;
  }

  /**
   * Render the Markdown of one text body: pipe tables and bullet runs become blocks, the rest
   * keeps its shape and only gets inline formatting.
   *
   * @param bool $escape Escape the text — OFF inside HTML, where it is already escaped.
   * @param bool $wrapParagraphs Wrap free lines in <p>…<br>… — OFF inside HTML.
   */
  private static function renderBody(string $text, bool $escape, bool $wrapParagraphs): string
  {
    $lines = explode("\n", $wrapParagraphs ? trim($text) : $text);
    $out = [];
    $free = [];

    for ($i = 0, $n = count($lines); $i < $n; $i++) {
      $run = self::tableRun($lines, $i, $escape) ?? self::listRun($lines, $i, $escape);

      if ($run === null) {
        $free[] = $lines[$i];
        continue;
      }

      // A block carries its own spacing: the blank lines that only separated it from the text
      // would come back as <br><br> once the caller applies nl2br.
      while ($free !== [] && trim((string)end($free)) === '') {
        array_pop($free);
      }

      if ($free !== []) {
        $out[] = self::freeLines($free, $escape, $wrapParagraphs);
        $free = [];
      }

      [$block, $i] = $run;
      $out[] = $block;

      while (isset($lines[$i + 1]) && trim($lines[$i + 1]) === '') {
        $i++;
      }
    }

    if ($free !== []) {
      $out[] = self::freeLines($free, $escape, $wrapParagraphs);
    }

    return implode("\n", $out);
  }

  /**
   * Lines that are neither a table nor a list: inline formatting only, shape preserved.
   *
   * @param array<int, string> $lines
   */
  private static function freeLines(array $lines, bool $escape, bool $wrapParagraphs): string
  {
    $rendered = array_map(static fn(string $line): string => self::inline($line, $escape), $lines);

    return $wrapParagraphs ? '<p>' . implode('<br>', $rendered) . '</p>' : implode("\n", $rendered);
  }

  /**
   * A table starts at $i when a pipe row is followed by a GFM separator row; it runs while the
   * lines keep a pipe.
   *
   * @return array{0: string, 1: int}|null Rendered block and the index of its last line
   */
  private static function tableRun(array $lines, int $i, bool $escape): ?array
  {
    if (!self::isTable([$lines[$i], $lines[$i + 1] ?? ''])) {
      return null;
    }

    $end = $i + 1;

    while (isset($lines[$end + 1]) && str_contains($lines[$end + 1], '|')) {
      $end++;
    }

    return [self::renderTable(array_slice($lines, $i, $end - $i + 1)), $end];
  }

  /**
   * @return array{0: string, 1: int}|null Rendered block and the index of its last line
   */
  private static function listRun(array $lines, int $i, bool $escape): ?array
  {
    if (preg_match('/^\s*[-*]\s+/', $lines[$i]) !== 1) {
      return null;
    }

    $end = $i;

    // A blank line between two bullets is a loose list, not two lists.
    for ($k = $i + 1; isset($lines[$k]); $k++) {
      if (trim($lines[$k]) === '') {
        continue;
      }

      if (preg_match('/^\s*[-*]\s+/', $lines[$k]) !== 1) {
        break;
      }

      $end = $k;
    }

    return [self::renderList(array_slice($lines, $i, $end - $i + 1), $escape), $end];
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

  private static function renderTable(array $lines, bool $escape = true): string
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
      $out .= '<th' . ($aligns[$i] ?? '') . '>' . self::inline($cell, $escape) . '</th>';
    }

    $out .= '</tr></thead><tbody>';

    foreach (array_slice($lines, 2) as $line) {
      if (trim($line) === '') {
        continue;
      }

      $out .= '<tr>';

      foreach (self::cells($line) as $i => $cell) {
        $out .= '<td' . ($aligns[$i] ?? '') . '>' . self::inline($cell, $escape) . '</td>';
      }

      $out .= '</tr>';
    }

    return $out . '</tbody></table>';
  }

  /**
   * Render a bullet run, honouring indentation: two spaces (or a tab) is one nesting level.
   */
  private static function renderList(array $lines, bool $escape = true): string
  {
    $out = '';
    $depth = 0;

    foreach ($lines as $line) {
      if (preg_match('/^([ \t]*)[-*]\s+(.*)$/', $line, $m) !== 1) {
        continue;
      }

      $want = intdiv(strlen(str_replace("\t", '  ', $m[1])), 2) + 1;

      while ($depth > $want) {
        $out .= '</li></ul>';
        $depth--;
      }

      if ($depth === $want) {
        $out .= '</li>';
      }

      while ($depth < $want) {
        $out .= '<ul>';
        $depth++;
      }

      $out .= '<li>' . self::inline($m[2], $escape);
    }

    while ($depth > 0) {
      $out .= '</li></ul>';
      $depth--;
    }

    return $out;
  }

  /**
   * Inline formatting: bold, italic, inline code, links.
   *
   * @param bool $escape Escape first — OFF for text lifted out of HTML, already escaped there.
   */
  private static function inline(string $text, bool $escape = true): string
  {
    if ($escape) {
      $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    // [label](http…) — href limited to http/https so htmlspecialchars-escaped quotes stay safe.
    $text = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $text);

    return $text;
  }
}

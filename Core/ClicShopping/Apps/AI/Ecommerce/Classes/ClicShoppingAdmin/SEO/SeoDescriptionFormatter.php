<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

  /**
   * SeoDescriptionFormatter
   *
   * Deterministic guarantee that an inserted SEO description is rendered as a
   * readable multi-paragraph block (one <p>...</p> per line), never a single
   * unbroken line of prose.
   *
   * The generation prompt asks the LLM for <p> paragraphs, but compliance is not
   * reliable across models / languages (observed: a full description returned as
   * one flat block with zero <p>). This formatter is the deterministic chokepoint
   * applied just before persistence so the result is consistent regardless of what
   * the model emits — in the spirit of the project's "déterministe au chokepoint"
   * preference over prompt-only fixes.
   *
   * Policy:
   *   0. Any <table>...</table> block (e.g. a technical-characteristics table)
   *      is extracted verbatim BEFORE anything else runs, so it is never split
   *      into sentences and never touched by strip_tags(). It is re-appended,
   *      unmodified, after the formatted paragraphs.
   *   1. If the text already contains <p> blocks, keep the model's semantic split
   *      and just normalise to one <p> per line.
   *   2. Else, if paragraphs are separated by blank lines, wrap each block.
   *   3. Else (single block of prose), split into sentences and chunk them into a
   *      few balanced paragraphs.
   *
   * Pure PHP, multibyte-safe, embedding-free, deterministic.
   */
  class SeoDescriptionFormatter
  {
    /** Default number of paragraphs when chunking an unstructured single block. */
    private const DEFAULT_TARGET_PARAGRAPHS = 3;

    /**
     * Format a description into clean <p> paragraphs, one per line.
     *
     * @param string $description Raw description (HTML or plain text) from the generator/translator.
     * @param int    $targetParagraphs Number of paragraphs to aim for when splitting a single block.
     * @return string Multi-line HTML: each paragraph on its own line as <p>...</p>,
     *                followed by any extracted table(s) unmodified. Empty string stays empty.
     */
    public function format(string $description, int $targetParagraphs = self::DEFAULT_TARGET_PARAGRAPHS): string
    {
      $description = trim($description);

      if ($description === '') {
        return '';
      }

      // Case 0: pull out table(s) first, so nothing below ever sees them.
      [$description, $tables] = $this->extractTables($description);
      $description = trim($description);

      if ($description === '') {
        return $this->appendTables('', $tables);
      }

      // Case 1: the model already produced <p> blocks → preserve its semantic split,
      // normalise to exactly one <p> per line (strips the "all on one line" artefact).
      if (preg_match('/<p[\s>]/i', $description)
        && preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $description, $matches)) {
        $blocks = array_values(array_filter(
          array_map(static fn(string $s): string => trim($s), $matches[1]),
          static fn(string $s): bool => $s !== ''
        ));

        if (!empty($blocks)) {
          $formatted = implode("\n", array_map(static fn(string $s): string => '<p>' . $s . '</p>', $blocks));
          return $this->appendTables($formatted, $tables);
        }
      }

      // Case 2: author-separated paragraphs (blank line between blocks).
      $byBlankLine = preg_split('/\n\s*\n/u', $description) ?: [];
      if (count($byBlankLine) > 1) {
        $blocks = array_values(array_filter(
          array_map(static fn(string $s): string => trim(strip_tags($s)), $byBlankLine),
          static fn(string $s): bool => $s !== ''
        ));

        if (count($blocks) > 1) {
          $formatted = implode("\n", array_map(static fn(string $s): string => '<p>' . $s . '</p>', $blocks));
          return $this->appendTables($formatted, $tables);
        }
      }

      // Case 3: single unstructured block → split into sentences and chunk into balanced paragraphs.
      $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags($description)));

      if ($text === '') {
        return $this->appendTables('', $tables);
      }

      $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
      $count = count($sentences);

      if ($count <= 1) {
        return $this->appendTables('<p>' . $text . '</p>', $tables);
      }

      $paragraphs = max(1, min($targetParagraphs, $count));
      $perParagraph = (int)ceil($count / $paragraphs);

      $out = [];
      foreach (array_chunk($sentences, $perParagraph) as $chunk) {
        $block = trim(implode(' ', $chunk));
        if ($block !== '') {
          $out[] = '<p>' . $block . '</p>';
        }
      }

      return $this->appendTables(implode("\n", $out), $tables);
    }

    /**
     * Extracts every <table>...</table> block verbatim from the source HTML
     * and returns the remaining text alongside the list of extracted tables,
     * in the order they appeared. The tables themselves are never modified.
     *
     * @param string $html Raw description that may contain one or more tables.
     * @return array{0: string, 1: string[]} [$remainingText, $tables]
     */
    private function extractTables(string $html): array
    {
      $tables = [];

      if (preg_match_all('/<table\b[^>]*>.*?<\/table>/is', $html, $matches)) {
        $tables = $matches[0];
        $html = (string)preg_replace('/<table\b[^>]*>.*?<\/table>/is', '', $html);
      }

      return [$html, $tables];
    }

    /**
     * Re-appends previously-extracted table(s), unmodified, after the
     * formatted prose. Tables always end up at the end of the description.
     *
     * @param string   $formatted Already-formatted <p> paragraphs (may be empty).
     * @param string[] $tables    Verbatim table HTML extracted by extractTables().
     * @return string
     */
    private function appendTables(string $formatted, array $tables): string
    {
      if (empty($tables)) {
        return $formatted;
      }

      $tableBlock = implode("\n", $tables);

      return $formatted === '' ? $tableBlock : $formatted . "\n" . $tableBlock;
    }
  }
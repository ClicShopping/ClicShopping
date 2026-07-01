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
 *   1. If the text already contains <p> blocks, keep the model's semantic split
 *      and just normalise to one <p> per line.
 *   2. Else, if paragraphs are separated by blank lines, wrap each block.
 *   3. Else (single block of prose), split into sentences and chunk them into a
 *      few balanced paragraphs.
 *
 * Pure PHP, multibyte-safe, embedding-free, deterministic.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO
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
   * @return string Multi-line HTML: each paragraph on its own line as <p>...</p>. Empty string stays empty.
   */
  public function format(string $description, int $targetParagraphs = self::DEFAULT_TARGET_PARAGRAPHS): string
  {
    $description = trim($description);

    if ($description === '') {
      return '';
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
        return implode("\n", array_map(static fn(string $s): string => '<p>' . $s . '</p>', $blocks));
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
        return implode("\n", array_map(static fn(string $s): string => '<p>' . $s . '</p>', $blocks));
      }
    }

    // Case 3: single unstructured block → split into sentences and chunk into balanced paragraphs.
    $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags($description)));

    if ($text === '') {
      return '';
    }

    $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
    $count = count($sentences);

    if ($count <= 1) {
      return '<p>' . $text . '</p>';
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

    return implode("\n", $out);
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Shared\Embedding\Chunking;

use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;

/**
 * ChunkPolicy Class
 *
 * Immutable value object describing HOW a document is cut into chunks: the cap, the
 * ordered cascade of separators to try, and whether an unbreakable run may be hard-split.
 *
 * The cap is named maxChars, never "size": the unit is the defect this chokepoint exists
 * to make impossible to repeat. LLPhant's DocumentSplitter compares with strlen(), so the
 * cap is CHARACTERS (bytes), whatever the embedding model documents in tokens.
 *
 * Each named constructor reproduces one existing call-site behaviour, so the chokepoint can
 * be introduced without changing any produced chunk.
 *
 * @package ClicShopping\AI\DomainsAI\Shared\Embedding\Chunking
 */
final class ChunkPolicy
{
  /**
   * Cap in CHARACTERS. 0 means "never split".
   */
  public private(set) int $maxChars;

  /**
   * Separators tried in order; a chunk still over the cap is re-split with the next one.
   *
   * @var string[]
   */
  public private(set) array $separators;

  /**
   * Hard-split a run that no separator can break, so the cap always holds.
   */
  public private(set) bool $hardSplit;

  /**
   * Words repeated between two consecutive chunks, passed through to LLPhant.
   */
  public private(set) int $wordOverlap;

  /**
   * @param int $maxChars Cap in characters; 0 disables splitting
   * @param string[] $separators Ordered cascade of separators
   * @param bool $hardSplit Whether an unbreakable run is cut at character level
   * @param int $wordOverlap Words repeated between consecutive chunks
   */
  private function __construct(int $maxChars, array $separators, bool $hardSplit, int $wordOverlap = 0)
  {
    $this->maxChars = max(0, $maxChars);
    $this->separators = array_values($separators);
    $this->hardSplit = $hardSplit;
    $this->wordOverlap = max(0, $wordOverlap);
  }

  /**
   * Cap derived from the configured embedding model, as NewVector does today.
   *
   * @param string[]|null $separators Cascade; defaults to a single space
   * @return self
   */
  public static function forEmbeddingModel(?array $separators = null): self
  {
    return new self(NewVector::getOptimalChunkSize(), $separators ?? [' '], false);
  }

  /**
   * No splitting at all: the document comes back as a single chunk.
   *
   * @return self
   */
  public static function none(): self
  {
    return new self(0, [], false);
  }

  /**
   * Paragraph-first cascade with a hard-split guarantee.
   *
   * @param int $maxChars Cap in characters
   * @return self
   */
  public static function paragraphs(int $maxChars): self
  {
    return new self($maxChars, ["\n\n", ' '], true);
  }

  /**
   * Explicit cap, independent of the embedding model.
   *
   * @param int $maxChars Cap in characters
   * @param string[] $separators Ordered cascade of separators
   * @param bool $hardSplit Whether an unbreakable run is cut at character level
   * @param int $wordOverlap Words repeated between consecutive chunks
   * @return self
   */
  public static function ofChars(int $maxChars, array $separators = [' '], bool $hardSplit = false, int $wordOverlap = 0): self
  {
    return new self($maxChars, $separators, $hardSplit, $wordOverlap);
  }

  /**
   * True when this policy never splits.
   *
   * @return bool
   */
  public function splitsNothing(): bool
  {
    return $this->maxChars <= 0 || $this->separators === [];
  }
}

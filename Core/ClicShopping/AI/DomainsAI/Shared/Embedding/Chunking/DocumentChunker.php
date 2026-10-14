<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Shared\Embedding\Chunking;

use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;

/**
 * DocumentChunker Class
 *
 * The single place in the code base that calls LLPhant's DocumentSplitter. Four producers
 * used to call it directly with four different policies, none of them shared; a document
 * could therefore be cut one way on one write path and another way on the next.
 *
 * The algorithm is MemoryDocumentChunker::splitIntoChunks() generalised, not rewritten: the
 * separator cascade, the UTF-8-safe hard split and the chunk cloning are the only correct
 * implementation the repository had. What used to be hardcoded there is now carried by the
 * ChunkPolicy the caller supplies.
 *
 * @package ClicShopping\AI\DomainsAI\Shared\Embedding\Chunking
 */
final class DocumentChunker
{
  /**
   * Split a document according to a policy.
   *
   * @param Document $document Document to split; returned untouched by a non-splitting policy
   * @param ChunkPolicy $policy Cap, separator cascade and hard-split flag
   * @return Document[] Chunks, each honouring the cap when the policy hard-splits
   */
  public static function split(Document $document, ChunkPolicy $policy): array
  {
    if ($policy->splitsNothing()) {
      return [$document];
    }

    $maxChars = $policy->maxChars;
    $separators = $policy->separators;

    $chunks = DocumentSplitter::splitDocument($document, $maxChars, $separators[0], $policy->wordOverlap);

    // Cascade: a chunk still over the cap is re-split with the next separator.
    foreach (array_slice($separators, 1) as $separator) {
      $refined = [];

      foreach ($chunks as $chunk) {
        if (strlen($chunk->content) <= $maxChars) {
          $refined[] = $chunk;
          continue;
        }

        foreach (DocumentSplitter::splitDocument(self::cloneWithContent($chunk, $chunk->content), $maxChars, $separator, $policy->wordOverlap) as $sub) {
          $refined[] = $sub;
        }
      }

      $chunks = $refined;
    }

    if (!$policy->hardSplit) {
      return $chunks;
    }

    // A single unbroken run has no separator to split on, so the cascade leaves it
    // oversized. Cut it at character level so EVERY chunk honours the cap.
    $capped = [];

    foreach ($chunks as $chunk) {
      if (strlen($chunk->content) <= $maxChars) {
        $capped[] = $chunk;
        continue;
      }

      foreach (self::hardSplitText($chunk->content, $maxChars) as $piece) {
        $capped[] = self::cloneWithContent($chunk, $piece);
      }
    }

    return $capped;
  }

  /**
   * Hard-split text into byte-bounded pieces without breaking a multibyte UTF-8 character
   * (which would corrupt the embedding text). Concatenating the pieces back yields the
   * original text exactly — no loss, no duplication.
   *
   * @param string $text Text to split
   * @param int $maxBytes Maximum byte length per piece
   * @return string[] Pieces, each with strlen() <= $maxBytes
   */
  private static function hardSplitText(string $text, int $maxBytes): array
  {
    $pieces = [];
    $current = '';

    foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
      if ($current !== '' && strlen($current) + strlen($char) > $maxBytes) {
        $pieces[] = $current;
        $current = '';
      }

      $current .= $char;
    }

    if ($current !== '') {
      $pieces[] = $current;
    }

    return $pieces;
  }

  /**
   * Build a new chunk Document from a source chunk, replacing its content while preserving
   * sourceType, sourceName and metadata.
   *
   * @param Document $source Source chunk to clone
   * @param string $content Replacement content
   * @return Document
   */
  private static function cloneWithContent(Document $source, string $content): Document
  {
    $document = new Document();
    $document->content = $content;
    // sourceType/sourceName are typed with defaults on Document: they are never unset.
    $document->sourceType = $source->sourceType;
    $document->sourceName = $source->sourceName;

    // `metadata` is not declared by LLPhant's Document; it is carried as a dynamic property.
    $document->metadata = get_object_vars($source)['metadata'] ?? [];

    return $document;
  }
}

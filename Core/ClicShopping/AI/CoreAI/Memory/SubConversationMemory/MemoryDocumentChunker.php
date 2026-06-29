<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;

/**
 * MemoryDocumentChunker Class
 *
 * Splits a long interaction into safe-sized chunk Documents before embedding —
 * the write-side counterpart to ChunkReconstructor (which rebuilds interactions
 * from chunks on read). Extracted verbatim from LongTermMemoryManager (2026-06-23)
 * as the MemoryStore chunking concern, to drain the store methods' complexity.
 *
 * Cascading split: first on paragraph boundaries ("\n\n"), then any chunk still
 * over the size cap is re-split on spaces, and finally any chunk that is a single
 * unbroken token longer than the cap (base64 blob, long URL, minified JSON,
 * concatenated IDs — no separator to split on) is hard-split at the character
 * level so the maxChunkSize guarantee always holds. The per-chunk metadata merge,
 * embedding and persistence stay in the caller.
 *
 * Responsibilities:
 * - Build the base Document from content + metadata
 * - Split it to guarantee each chunk is within maxChunkSize
 */
class MemoryDocumentChunker
{
  private int $maxChunkSize;

  /**
   * Constructor
   *
   * @param int $maxChunkSize Max characters per chunk
   */
  public function __construct(int $maxChunkSize = 2000)
  {
    $this->maxChunkSize = $maxChunkSize;
  }

  /**
   * Split content into chunk Documents (guaranteed within maxChunkSize).
   *
   * @param string $content Long content to chunk
   * @param array $metadata Metadata attached to the base document
   * @return array Array of chunk Documents (pre per-chunk metadata merge / embedding)
   */
  public function splitIntoChunks(string $content, array $metadata): array
  {
    // Create base document
    $baseDocument = new Document();
    $baseDocument->content = $content;
    $baseDocument->sourceType = 'conversation';
    $baseDocument->sourceName = $metadata['user_id'] ?? 'system';

    // Store metadata in the metadata property (not as dynamic properties)
    // This avoids PHP 8.x deprecated warnings
    // Check property existence before assignment
    if (!property_exists($baseDocument, 'metadata')) {
      @$baseDocument->metadata = [];
    }
    $baseDocument->metadata = $metadata;

    // Split document with cascading separators to guarantee maxChunkSize
    $chunks = DocumentSplitter::splitDocument($baseDocument, $this->maxChunkSize, "\n\n");

    // Re-split any oversized chunks with smaller separator
    $safeChunks = [];
    foreach ($chunks as $chunk) {
      if (strlen($chunk->content) > $this->maxChunkSize) {
        $subDoc = new Document();
        $subDoc->content = $chunk->content;
        $subDoc->sourceType = $chunk->sourceType ?? 'conversation';
        $subDoc->sourceName = $chunk->sourceName ?? '';
        if (!property_exists($subDoc, 'metadata')) {
          @$subDoc->metadata = [];
        }
        $subDoc->metadata = get_object_vars($chunk)['metadata'] ?? [];
        $subChunks = DocumentSplitter::splitDocument($subDoc, $this->maxChunkSize, " ");
        foreach ($subChunks as $sub) {
          $safeChunks[] = $sub;
        }
      } else {
        $safeChunks[] = $chunk;
      }
    }

    // Final guarantee: a single unbroken token longer than the cap has no "\n\n"
    // or space to split on, so the cascade above leaves it oversized. Hard-split
    // it at the character level (UTF-8 safe) so EVERY chunk honours maxChunkSize.
    $cappedChunks = [];
    foreach ($safeChunks as $chunk) {
      if (strlen($chunk->content) > $this->maxChunkSize) {
        foreach ($this->hardSplitText($chunk->content, $this->maxChunkSize) as $piece) {
          $cappedChunks[] = $this->cloneChunkWithContent($chunk, $piece);
        }
      } else {
        $cappedChunks[] = $chunk;
      }
    }

    return $cappedChunks;
  }

  /**
   * Hard-split text into byte-bounded pieces without breaking a multibyte
   * UTF-8 character (which would corrupt the embedding text). Concatenating
   * the pieces back yields the original text exactly (no loss, no duplication).
   *
   * @param string $text Text to split
   * @param int $maxBytes Maximum byte length per piece
   * @return string[] Pieces, each with strlen() <= $maxBytes
   */
  private function hardSplitText(string $text, int $maxBytes): array
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
   * Build a new chunk Document from a source chunk, replacing its content while
   * preserving sourceType, sourceName and metadata.
   *
   * @param Document $source Source chunk to clone
   * @param string $content Replacement content
   * @return Document
   */
  private function cloneChunkWithContent(Document $source, string $content): Document
  {
    $doc = new Document();
    $doc->content = $content;
    $doc->sourceType = $source->sourceType ?? 'conversation';
    $doc->sourceName = $source->sourceName ?? '';
    if (!property_exists($doc, 'metadata')) {
      @$doc->metadata = [];
    }
    $doc->metadata = get_object_vars($source)['metadata'] ?? [];

    return $doc;
  }
}

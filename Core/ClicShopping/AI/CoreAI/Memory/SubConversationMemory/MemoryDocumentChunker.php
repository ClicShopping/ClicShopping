<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

use LLPhant\Embeddings\Document;
use ClicShopping\AI\DomainsAI\Shared\Embedding\Chunking\ChunkPolicy;
use ClicShopping\AI\DomainsAI\Shared\Embedding\Chunking\DocumentChunker;

/**
 * MemoryDocumentChunker Class
 *
 * Builds the base Document for a long interaction and hands the cutting to the shared
 * chokepoint — the write-side counterpart to ChunkReconstructor (which rebuilds
 * interactions from chunks on read).
 *
 * The cascading split it used to carry (paragraphs, then spaces, then a UTF-8-safe hard
 * split) was the only correct implementation in the repository; it now lives in
 * DocumentChunker under ChunkPolicy::paragraphs() and is shared by every producer. The
 * per-chunk metadata merge, embedding and persistence stay in the caller.
 *
 * Responsibilities:
 * - Build the base Document from content + metadata
 * - Delegate the split, which guarantees each chunk is within maxChunkSize
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

    // `metadata` is not declared by LLPhant's Document; it is carried as a dynamic property.
    $baseDocument->metadata = $metadata;

    return DocumentChunker::split($baseDocument, ChunkPolicy::paragraphs($this->maxChunkSize));
  }
}

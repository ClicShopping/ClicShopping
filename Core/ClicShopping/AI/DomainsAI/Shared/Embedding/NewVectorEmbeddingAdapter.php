<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Shared\Embedding;

use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;

/**
 * NewVectorEmbeddingAdapter Class
 *
 * LLPhant EmbeddingGeneratorInterface adapter that routes embedding calls to the
 * project's NewVector facade via its raw embedText() path (one float vector per
 * text, no file caching). Consolidates the identical inline anonymous adapters
 * that were duplicated across the conversation-memory / correction subsystems
 * (ConversationMemory, CorrectionAgent, CorrectionPatterns) — 2026-06-28.
 *
 * NB: distinct from Rag\RagEmbeddingGenerator, whose embedDocument() goes through
 * NewVector::createEmbedding() (cached, returns split Document[]) — a different
 * semantics that must NOT be merged here.
 *
 * Stateless.
 */
class NewVectorEmbeddingAdapter implements EmbeddingGeneratorInterface
{
  public function embedText(string $text): array
  {
    $generator = NewVector::gptEmbeddingsModel();
    if (!$generator) {
      throw new \RuntimeException('Embedding generator not initialized.');
    }
    return $generator->embedText($text);
  }

  public function embedDocument(Document $document): Document
  {
    $document->embedding = $this->embedText($document->content);
    return $document;
  }

  public function embedDocuments(array $documents): array
  {
    return array_map($this->embedDocument(...), $documents);
  }

  public function getEmbeddingLength(): int
  {
    return NewVector::getEmbeddingLength();
  }
}

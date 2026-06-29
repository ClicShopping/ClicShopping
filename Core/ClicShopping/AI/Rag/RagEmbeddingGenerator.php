<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Rag;

use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;

/**
 * RagEmbeddingGenerator - LLPhant EmbeddingGeneratorInterface adapter over NewVector.
 *
 * Extracted from MultiDBRAGManager (god-class decomposition): was an inline anonymous class.
 * Stateless adapter that routes LLPhant embedding calls to the project's NewVector facade.
 *
 * @package ClicShopping\AI\Rag
 * @since 2026-06-10
 */
class RagEmbeddingGenerator implements EmbeddingGeneratorInterface
{
  /**
   * Embeds a single text string
   *
   * @param string $text Text to embed
   * @return array Embedding vector
   */
  public function embedText(string $text): array
  {
    $generator = NewVector::gptEmbeddingsModel();

    if (!$generator) {
      throw new \RuntimeException('Embedding generator non initialisé');
    }

    return $generator->embedText($text);
  }

  /**
   * Embeds a single document
   *
   * @param Document $document Document object to embed
   * @return Document Embedded Document object
   */
  public function embedDocument(Document $document): Document
  {
    // Assign the actual embedding vector (NewVector::createEmbedding returns a
    // split Document[], not a vector — it must not be stored in ->embedding).
    $document->embedding = $this->embedText($document->content);

    return $document;
  }

  /**
   * Embeds multiple documents
   *
   * @param array $documents Array of Document objects to embed
   * @return array Array of embedded Document objects
   */
  public function embedDocuments(array $documents): array
  {
    $results = [];

    foreach ($documents as $document) {
      $results[] = $this->embedDocument($document);
    }

    return $results;
  }

  /**
   * Returns the length of the embedding vector
   *
   * @return int Length of the embedding vector
   */
  public function getEmbeddingLength(): int
  {
    return NewVector::getEmbeddingLength();
  }
}

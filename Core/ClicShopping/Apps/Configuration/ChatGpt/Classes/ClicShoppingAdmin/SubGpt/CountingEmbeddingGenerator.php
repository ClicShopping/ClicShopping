<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;

/**
 * CountingEmbeddingGenerator
 *
 * Transparent {@see EmbeddingGeneratorInterface} decorator that files each embedding request
 * with {@see LlmCallCounter::recordEmbedding()}, then delegates. Applied once, in
 * NewVector::gptEmbeddingsModel(), which every embedding path goes through.
 * LLPhant drops the provider's usage, so the unit is characters sent, never tokens.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt
 */
final class CountingEmbeddingGenerator implements EmbeddingGeneratorInterface
{
  public function __construct(private readonly EmbeddingGeneratorInterface $inner, private readonly string $model)
  {
  }

  public function embedText(string $text): array
  {
    LlmCallCounter::recordEmbedding($this->model, 1, mb_strlen($text));

    return $this->inner->embedText($text);
  }

  public function embedDocument(Document $document): Document
  {
    LlmCallCounter::recordEmbedding($this->model, 1, mb_strlen($document->formattedContent ?? $document->content));

    return $this->inner->embedDocument($document);
  }

  public function embedDocuments(array $documents): array
  {
    $chars = 0;

    foreach ($documents as $document) {
      $chars += mb_strlen($document->formattedContent ?? $document->content);
    }

    LlmCallCounter::recordEmbedding($this->model, count($documents), $chars);

    return $this->inner->embedDocuments($documents);
  }

  public function getEmbeddingLength(): int
  {
    return $this->inner->getEmbeddingLength();
  }
}

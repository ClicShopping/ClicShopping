<?php

declare(strict_types=1);

namespace LLPhant\Embeddings\EmbeddingGenerator\OpenAI;

final class OpenAIADA002EmbeddingGenerator extends AbstractOpenAIEmbeddingGenerator
{
    public function getEmbeddingLength(): int
    {
        return 1536;
    }

    public function getModelName(): string
    {
        return 'text-embedding-ada-002';
    }
}

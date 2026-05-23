<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory;


use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Storage\MariaDBVectorStore;
use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;


class CorrectionPatterns
{
  private SecurityLogger $securityLogger;
  private MariaDBVectorStore $vectorStore;
  private EmbeddingGeneratorInterface $embeddingGenerator;
  private bool $debug;
  private string $userId;
  private int $languageId;

  /**
   * @param string $userId
   * @param int|null $languageId
   * @param string $tableName
   * @throws \Exception
   */
  public function __construct(string $userId = 'system', ?int $languageId = null, string $tableName = 'rag_correction_patterns_embedding')
  {
    $this->userId = $userId;
    $this->securityLogger = new SecurityLogger();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')&& CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    $this->languageId = $languageId ?? Registry::get('Language')->getId();
    $this->embeddingGenerator = $this->createEmbeddingGenerator();
    $this->vectorStore = new MariaDBVectorStore($this->embeddingGenerator, $tableName);

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "CorrectionPatterns initialized for user: {$this->userId}",
        'info'
      );
    }
  }

  /**
   * Create an embedding generator using NewVector's GPT embeddings model.
   *
   * @return EmbeddingGeneratorInterface
   */
  private function createEmbeddingGenerator(): EmbeddingGeneratorInterface
  {
    return new class implements EmbeddingGeneratorInterface
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
    };
  }

  /**
   * Store a correction pattern in the vector store.
   *
   * @param string $originalQuery The original user query.
   * @param string $incorrectResponse The incorrect response provided.
   * @param string $correctedResponse The corrected response.
   * @param array $metadata Additional metadata to store with the correction.
   * @return bool True on success, false on failure.
   */
  public function storeCorrection(string $originalQuery, string $incorrectResponse, string $correctedResponse, array $metadata = [] ): bool {
    try {
      $correctionContent = $this->formatCorrectionForStorage( $originalQuery,$incorrectResponse, $correctedResponse );

      $document = new Document();
      $document->content = $correctionContent;
      $document->sourceType = 'correction_pattern';
      $document->sourceName = 'user_correction';

      $enrichedMetadata = array_merge([
        'type' => 'correction_pattern',
        'user_id' => $this->userId,
        'language_id' => $this->languageId,
        'timestamp' => time(),
        'original_query' => substr($originalQuery, 0, 500),
        'incorrect_response' => substr($incorrectResponse, 0, 500),
        'corrected_response' => substr($correctedResponse, 0, 500),
        'pattern_id' => uniqid('correction_', true),
      ], $metadata);

      $document->metadata = $enrichedMetadata;
      $this->vectorStore->addDocument($document);

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Correction pattern stored: {$enrichedMetadata['pattern_id']}",
          'info'
        );
      }

      return true;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error storing correction pattern: " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }

  /**
   * Retrieve statistics about stored correction patterns.
   *
   * @return array An array containing statistics such as total patterns stored.
   */
  public function getPatternStats(): array
  {
    try {
      $filter = function($metadata) {
        return isset($metadata['user_id'])
          && $metadata['user_id'] === $this->userId;
      };

      $allPatterns = $this->vectorStore->similaritySearch(
        "correction pattern",
        100,
        0.0,
        $filter
      );

      $patterns = [];
      foreach ($allPatterns as $pattern) {
        $patterns[] = $pattern;
      }

      return [
        'total_patterns' => count($patterns),
        'user_id' => $this->userId,
        'language_id' => $this->languageId,
      ];

    } catch (\Exception $e) {
      return ['total_patterns' => 0, 'error' => $e->getMessage()];
    }
  }

  /**
   * Format the correction data for storage.
   *
   * @param string $originalQuery The original user query.
   * @param string $incorrectResponse The incorrect response provided.
   * @param string $correctedResponse The corrected response.
   * @return string Formatted correction string.
   */
  private function formatCorrectionForStorage(
    string $originalQuery,
    string $incorrectResponse,
    string $correctedResponse
  ): string {
    return "Query: {$originalQuery}\n\n"
      . "Original (Incorrect) Response: {$incorrectResponse}\n\n"
      . "Corrected Response: {$correctedResponse}";
  }

  /**
   * Clear all stored correction patterns for the user.
   *
   * @return bool True on success, false on failure.
   */
  public function clearPatterns(): bool
  {
    try {
      $this->securityLogger->logSecurityEvent(
        "Cleared correction patterns for user: {$this->userId}",
        'info'
      );
      return true;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error clearing patterns: " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Rag\Reranking;

use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\OM\CLICSHOPPING;

use LLPhant\Chat\ChatInterface;
use LLPhant\Embeddings\Document;
use LLPhant\Query\SemanticSearch\RetrievedDocumentsTransformer;

/**
 * DocumentReranker Class
 *
 * Domain-agnostic replacement of LLPhant's LLMReranker: same contract, but a
 * malformed or partial relevance order degrades instead of aborting the ranking
 * (see RelevanceOrderParser). The prompt lives in the agnostic language bucket.
 *
 * @package ClicShopping\AI\Rag\Reranking
 */
class DocumentReranker implements RetrievedDocumentsTransformer
{
  /**
   * @param ChatInterface $chat Chat instance obtained from the Gpt facade
   * @param int $nrOfOutputDocuments Number of documents to keep after reranking
   * @param bool $debug Enable debug logging
   */
  public function __construct(
    private readonly ChatInterface $chat,
    private readonly int           $nrOfOutputDocuments,
    private readonly bool          $debug = false
  )
  {
    DomainConfig::loadAgnosticLanguageFile('rag_reranking');
  }

  /**
   * {@inheritDoc}
   */
  public function transformDocuments(array $questions, array $retrievedDocs): array
  {
    $documents = array_values($retrievedDocs);

    if (count($documents) === 0) {
      return [];
    }

    $this->chat->setSystemMessage(CLICSHOPPING::getDef('prompt_reranking_system'));

    $answer = (string)$this->chat->generateText($this->formatQuestionsAndDocuments($questions, $documents));

    return $this->sortByRelevanceOrder($answer, $documents);
  }

  /**
   * Build the ranking request: the questions, then the numbered documents.
   *
   * @param array<int, string> $questions
   * @param array<int, Document> $documents
   * @return string
   */
  private function formatQuestionsAndDocuments(array $questions, array $documents): string
  {
    $questionPrefix = CLICSHOPPING::getDef('text_reranking_question_prefix');
    $documentPrefix = CLICSHOPPING::getDef('text_reranking_document_prefix');
    $output = '';

    foreach ($questions as $query) {
      $output .= $questionPrefix . ' ' . $query . \PHP_EOL;
    }

    foreach ($documents as $index => $document) {
      $output .= $documentPrefix . ' ' . ($index + 1) . ': ' . $document->content . \PHP_EOL;
    }

    return $output;
  }

  /**
   * Reorder the documents from the parsed relevance order, capped to the output size.
   *
   * @param string $answer Raw LLM answer
   * @param array<int, Document> $documents
   * @return array<int, Document>
   */
  private function sortByRelevanceOrder(string $answer, array $documents): array
  {
    $order = RelevanceOrderParser::parse($answer, count($documents));
    $ranked = [];

    foreach ($order as $position) {
      $ranked[] = $documents[$position];

      if (count($ranked) >= $this->nrOfOutputDocuments) {
        break;
      }
    }

    $this->debugLog('Reranked ' . count($documents) . ' documents, kept ' . count($ranked) . ' (order: ' . trim($answer) . ')');

    return $ranked;
  }

  /**
   * @param string $message Message to log when debug is enabled
   * @return void
   */
  private function debugLog(string $message): void
  {
    if ($this->debug) {
      error_log('[INFO] DocumentReranker: ' . $message);
    }
  }
}

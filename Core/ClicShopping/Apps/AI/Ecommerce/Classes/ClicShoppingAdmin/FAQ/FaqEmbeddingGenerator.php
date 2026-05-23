<?php
/**
 * FaqEmbeddingGenerator
 *
 * Helper class to generate vector embeddings for FAQ content.
 * Integrates with NewVector infrastructure for semantic search capabilities.
 *
 * @package ClicShopping
 * @subpackage AI\Ecommerce\FAQ
 * @version 1.0
 * @date 2026-05-03
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ;

use ClicShopping\OM\Registry;
use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

/**
 * FaqEmbeddingGenerator Class
 *
 * Generates vector embeddings for FAQ content to enable semantic search.
 * Uses NewVector infrastructure and SemanticAgent for taxonomy generation.
 *
 * Usage:
 * ```php
 * $generator = new FaqEmbeddingGenerator();
 * $result = $generator->generateEmbeddings($productId, $languageId);
 * if ($result['success']) {
 *   echo "Generated {$result['chunks_saved']} embedding chunks";
 * }
 * ```
 */
class FaqEmbeddingGenerator
{
  /**
   * Database connection instance
   *
   * @var mixed
   */
  private mixed $db;

  /**
   * FAQ Repository instance
   *
   * @var FaqRepository
   */
  private FaqRepository $repository;

  /**
   * Semantic Agent instance for taxonomy generation
   *
   * @var SemanticAgent
   */
  private SemanticAgent $semantics;

  /**
   * Constructor
   *
   * Initializes database connection, repository, and semantic agent.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->repository = new FaqRepository();
    $this->semantics = new SemanticAgent();
  }

  /**
   * Generate embeddings for FAQ content
   *
   * Retrieves FAQ from database, formats content, generates taxonomy,
   * creates vector embeddings, and saves to products_description_faq_embedding table.
   *
   * @param int $productId Product ID
   * @param int $languageId Language ID
   * @return array Result array with success status, chunks_saved, and error message
   *
   * @example
   * ```php
   * $generator = new FaqEmbeddingGenerator();
   * $result = $generator->generateEmbeddings(123, 1);
   * 
   * if ($result['success']) {
   *   echo "Success! Saved {$result['chunks_saved']} chunks";
   * } else {
   *   echo "Error: {$result['error']}";
   * }
   * ```
   */
  public function generateEmbeddings(int $productId, int $languageId): array
  {
    try {
      error_log("[FaqEmbeddingGenerator] Starting embedding generation for product {$productId}, language {$languageId}");

      // Step 1: Retrieve FAQ from database
      $faq = $this->repository->getFaq($productId, $languageId);

      if (!$faq || empty($faq['faq_content'])) {
        error_log("[FaqEmbeddingGenerator] No FAQ content found for product {$productId}, language {$languageId}");
        return [
          'success' => false,
          'error' => 'No FAQ content found',
          'chunks_saved' => 0
        ];
      }

      // Step 2: Parse FAQ JSON content
      $faqData = json_decode($faq['faq_content'], true);

      if (!is_array($faqData) || empty($faqData)) {
        error_log("[FaqEmbeddingGenerator] Invalid FAQ JSON for product {$productId}");
        return [
          'success' => false,
          'error' => 'Invalid FAQ JSON format',
          'chunks_saved' => 0
        ];
      }

      // Step 3: Format FAQ content for embedding (Q: ... A: ...)
      $embeddingData = $this->formatFaqForEmbedding($faqData);

      if (empty($embeddingData)) {
        error_log("[FaqEmbeddingGenerator] Empty embedding data for product {$productId}");
        return [
          'success' => false,
          'error' => 'Empty embedding data after formatting',
          'chunks_saved' => 0
        ];
      }

      error_log("[FaqEmbeddingGenerator] Formatted FAQ content: " . strlen($embeddingData) . " characters");

      // Step 4: Get language code for taxonomy generation
      $languageCode = $this->getLanguageCode($languageId);

      // Step 5: Generate taxonomy using SemanticAgent
      $taxonomy = $this->semantics->createTaxonomy(
        HTMLOverrideCommon::cleanHtmlForEmbedding($embeddingData),
        $languageCode,
        null
      );

      $tags = [];
      if (!empty($taxonomy)) {
        $lines = array_filter(array_map('trim', explode("\n", $taxonomy)));
        
        foreach ($lines as $line) {
          if (preg_match('/^\[([^\]]+)\]:\s*(.+)$/', $line, $matches)) {
            $tags[$matches[1]] = trim($matches[2]);
          }
        }
      }

      error_log("[FaqEmbeddingGenerator] Generated taxonomy with " . count($tags) . " tags");

      // Step 6: Generate embeddings using NewVector
      $embeddedDocuments = NewVector::createEmbedding(null, $embeddingData);

      if (empty($embeddedDocuments)) {
        error_log("[FaqEmbeddingGenerator] Failed to create embeddings for product {$productId}");
        return [
          'success' => false,
          'error' => 'Failed to generate embeddings',
          'chunks_saved' => 0
        ];
      }

      error_log("[FaqEmbeddingGenerator] Created " . count($embeddedDocuments) . " embedding document(s)");

      // Step 7: Prepare metadata
      $baseMetadata = [
        'product_id' => $productId,
        'content' => $faq['faq_description'] ?? '',
        'type' => 'faq',
        'tags' => $taxonomy ? array_filter(array_map(fn($t) => trim(strip_tags($t)), explode("\n", $taxonomy))) : [],
        'source' => [
          'type' => 'generated',
          'name' => 'faq_embedding_generator'
        ]
      ];

      // Step 8: Save embeddings with chunks using NewVector
      $result = NewVector::saveEmbeddingsWithChunks(
        $embeddedDocuments,
        'products_description_faq_embedding',
        $productId,
        $languageId,
        $baseMetadata,
        $this->db,
        false  // isUpdate = false (will be handled by caller)
      );

      if (!$result['success']) {
        error_log("[FaqEmbeddingGenerator] Failed to save embeddings for product {$productId}: " . $result['error']);
        return [
          'success' => false,
          'error' => $result['error'],
          'chunks_saved' => 0
        ];
      }

      error_log("[FaqEmbeddingGenerator] Successfully saved {$result['chunks_saved']} chunk(s) for product {$productId}");

      return [
        'success' => true,
        'chunks_saved' => $result['chunks_saved'],
        'error' => null
      ];

    } catch (\Exception $e) {
      error_log("[FaqEmbeddingGenerator] Exception in generateEmbeddings: " . $e->getMessage());
      error_log("[FaqEmbeddingGenerator] Stack trace: " . $e->getTraceAsString());
      
      return [
        'success' => false,
        'error' => $e->getMessage(),
        'chunks_saved' => 0
      ];
    }
  }

  /**
   * Format FAQ data for embedding generation
   *
   * Converts FAQ array into formatted text with Q: and A: prefixes
   * for better semantic understanding.
   *
   * @param array $faqData FAQ array with 'q' and 'a' keys
   * @return string Formatted FAQ content
   *
   * @example
   * ```php
   * $faqData = [
   *   ['q' => 'What is this?', 'a' => 'A product.'],
   *   ['q' => 'How to use?', 'a' => 'Read manual.']
   * ];
   * $formatted = $this->formatFaqForEmbedding($faqData);
   * // Returns: "Q: What is this?\nA: A product.\n\nQ: How to use?\nA: Read manual.\n\n"
   * ```
   */
  private function formatFaqForEmbedding(array $faqData): string
  {
    $formatted = '';

    foreach ($faqData as $item) {
      if (!isset($item['q']) || !isset($item['a'])) {
        continue;
      }

      $question = HTMLOverrideCommon::cleanHtmlForEmbedding($item['q']);
      $answer = HTMLOverrideCommon::cleanHtmlForEmbedding($item['a']);

      $formatted .= "Q: {$question}\n";
      $formatted .= "A: {$answer}\n\n";
    }

    return trim($formatted);
  }

  /**
   * Get language code from language ID
   *
   * Retrieves the language code (e.g., 'en', 'fr') from the language ID.
   *
   * @param int $languageId Language ID
   * @return string Language code (defaults to 'en' if not found)
   */
  private function getLanguageCode(int $languageId): string
  {
    try {
      $Qlang = $this->db->prepare('SELECT code 
                                    FROM :table_languages 
                                    WHERE languages_id = :language_id 
                                    LIMIT 1');
      $Qlang->bindInt(':language_id', $languageId);
      $Qlang->execute();

      $lang = $Qlang->fetch();

      return $lang && !empty($lang['code']) ? $lang['code'] : 'en';

    } catch (\Exception $e) {
      error_log("[FaqEmbeddingGenerator] Error getting language code: " . $e->getMessage());
      return 'en'; // Fallback to English
    }
  }

  /**
   * Delete FAQ embeddings for a product
   *
   * Removes all embedding chunks for a specific product and language.
   * Used when FAQ is updated or deleted.
   *
   * @param int $productId Product ID
   * @param int|null $languageId Language ID (null = delete all languages)
   * @return bool True on success, false on failure
   *
   * @example
   * ```php
   * $generator = new FaqEmbeddingGenerator();
   * 
   * // Delete for specific language
   * $generator->deleteEmbeddings(123, 1);
   * 
   * // Delete for all languages
   * $generator->deleteEmbeddings(123, null);
   * ```
   */
  public function deleteEmbeddings(int $productId, ?int $languageId = null): bool
  {
    try {
      $whereClause = ['entity_id' => $productId];
      
      if ($languageId !== null) {
        $whereClause['language_id'] = $languageId;
      }

      $this->db->delete('products_description_faq_embedding', $whereClause);

      error_log("[FaqEmbeddingGenerator] Deleted embeddings for product {$productId}" . 
                ($languageId ? ", language {$languageId}" : " (all languages)"));

      return true;

    } catch (\Exception $e) {
      error_log("[FaqEmbeddingGenerator] Error deleting embeddings: " . $e->getMessage());
      return false;
    }
  }
}

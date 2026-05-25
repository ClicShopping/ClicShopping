<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Products;

use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ\FaqEmbeddingGenerator;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

class ProductEmbeddingBuilder
{
  private mixed $app;
  private mixed $semantics;
  private bool $debug;

  public function __construct(mixed $app, mixed $semantics)
  {
    $this->app = $app;
    $this->semantics = $semantics;
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
  }

  /**
   * Build the embedding content string from product data fields.
   *
   * @param array  $productData  Associative array with product fields
   * @param string $languageCode ISO language code for definition loading
   * @return string The assembled embedding text content
   */
  public function buildEmbeddingContent(array $productData, string $languageCode): string
  {
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/rag', $languageCode);

    $productsName = $productData['products_name'] ?? '';
    $productsId = $productData['products_id'] ?? 0;

    $content = $this->app->getDef('text_product_name') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productsName) . "\n";
    $content .= $this->app->getDef('text_product_id') . ': ' . $productsId . "\n";

    if (!empty($productData['products_model'])) {
      $content .= $this->app->getDef('text_product_model') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['products_model']) . "\n";
    }

    if (!empty($productData['categories_name'])) {
      $content .= $this->app->getDef('text_categories_name') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['categories_name']) . "\n";
    }

    if (!empty($productData['manufacturer_name'])) {
      $content .= $this->app->getDef('text_product_brand_name') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['manufacturer_name']) . "\n";
    }

    if (!empty($productData['products_ean'])) {
      $content .= $this->app->getDef('text_product_ean') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['products_ean']) . "\n";
    }

    if (!empty($productData['products_sku'])) {
      $content .= $this->app->getDef('text_product_sku') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['products_sku']) . "\n";
    }

    if (!empty($productData['products_date_added'])) {
      $content .= $this->app->getDef('text_product_date_added') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['products_date_added']) . "\n";
    }

    if (isset($productData['products_status'])) {
      $statusText = ($productData['products_status'] === 1 || $productData['products_status'] === '1')
        ? $this->app->getDef('text_product_enable')
        : $this->app->getDef('text_product_disable');

      $content .= $this->app->getDef('text_product_status') . ': ' . $statusText . "\n";
    }

    if (!empty($productData['products_ordered'])) {
      $content .= $this->app->getDef('text_product_ordered') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['products_ordered']) . "\n";
    }

    if (!empty($productData['products_stock_reorder_level'])) {
      $content .= $this->app->getDef('text_product_stock_reorder') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['products_stock_reorder_level']) . "\n";
    }

    if (!empty($productData['products_quantity'])) {
      $content .= $this->app->getDef('text_product_stock') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['products_quantity']) . "\n";
    }

    if (!empty($productData['products_quantity_alert'])) {
      $content .= $this->app->getDef('text_product_stock_alert') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['products_quantity_alert']) . "\n";
    }

    if (!empty($productData['products_description'])) {
      $content .= $this->app->getDef('text_product_description') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['products_description']) . "\n";
    }

    if (!empty($productData['products_description_summary'])) {
      $content .= $this->app->getDef('text_product_description_summary') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['products_description_summary']) . "\n";
    }

    if (!empty($productData['seo_product_title'])) {
      $content .= $this->app->getDef('text_product_seo_title') . ': ' . HTMLOverrideCommon::cleanHtmlForSEO($productData['seo_product_title']) . "\n";
    }

    if (!empty($productData['seo_product_description'])) {
      $content .= $this->app->getDef('text_product_seo_description') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($productData['seo_product_description']) . "\n";
    }

    if (!empty($productData['seo_product_keywords'])) {
      $content .= $this->app->getDef('text_product_seo_keywords') . ': ' . HTMLOverrideCommon::cleanHtmlForSEO($productData['seo_product_keywords']) . "\n";
    }

    if (!empty($productData['seo_product_tag'])) {
      $content .= $this->app->getDef('text_product_seo_tag') . ': ' . HTMLOverrideCommon::cleanHtmlForSEO($productData['seo_product_tag']) . "\n";
    }

    if (!empty($productData['products_description'])) {
      $taxonomy = $this->semantics->createTaxonomy(
        HTMLOverrideCommon::cleanHtmlForEmbedding($productData['products_description']),
        $languageCode,
        null
      );

      $tags = $this->parseTaxonomyTags($taxonomy);

      $content .= "\n" . $this->app->getDef('text_product_taxonomy') . " :\n";
      foreach ($tags as $key => $value) {
        $content .= "[$key]: $value\n";
      }
    }

    return $content;
  }

  /**
   * Generate and store embedding vectors for a product.
   *
   * @param string $content     The embedding content string
   * @param array  $productData Product data array
   * @param int    $productId   The product ID
   * @param int    $languageId  The language ID
   * @param bool   $isUpdate    True if updating existing embeddings
   * @param mixed  $db          Database connection object
   * @return array Result with 'success', 'chunks_saved', 'error' keys
   */
  public function storeEmbedding(
    string $content,
    array $productData,
    int $productId,
    int $languageId,
    bool $isUpdate,
    mixed $db
  ): array {
    try {
      $embeddedDocuments = NewVector::createEmbedding(null, $content);

      if (empty($embeddedDocuments)) {
        return ['success' => false, 'chunks_saved' => 0, 'error' => 'createEmbedding returned empty result'];
      }

      $taxonomy = '';
      if (!empty($productData['products_description'])) {
        $taxonomy = $this->semantics->createTaxonomy(
          HTMLOverrideCommon::cleanHtmlForEmbedding($productData['products_description']),
          $productData['language_code'] ?? 'en',
          null
        );
      }

      $baseMetadata = [
        'product_name' => $productData['products_name'] ?? '',
        'content' => $productData['products_description'] ?? '',
        'type' => 'products',
        'product_id' => $productId,
        'tags' => $taxonomy ? array_filter(array_map(fn($t) => trim(strip_tags($t)), explode("\n", $taxonomy))) : [],
        'source' => [
          'type' => 'manual',
          'name' => 'manual'
        ]
      ];

      $result = NewVector::saveEmbeddingsWithChunks(
        $embeddedDocuments,
        'products_embedding',
        $productId,
        $languageId,
        $baseMetadata,
        $db,
        $isUpdate
      );

      return $result;

    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log("ProductEmbeddingBuilder: Exception for product {$productId} - " . $e->getMessage());
      }
      return ['success' => false, 'chunks_saved' => 0, 'error' => $e->getMessage()];
    }
  }

  /**
   * Generate and store FAQ embeddings for a product.
   *
   * @param int   $productId  The product ID
   * @param int   $languageId The language ID
   * @param bool  $isUpdate   If true, deletes existing FAQ embeddings first
   * @param mixed $db         Database connection object
   * @return array Result with 'success', 'chunks_saved', 'error' keys
   */
  public function storeFaqEmbedding(int $productId, int $languageId, bool $isUpdate, mixed $db): array
  {
    try {
      $QfaqCheck = $db->prepare('SELECT faq_content
                                 FROM :table_products_description_faq
                                 WHERE products_id = :products_id
                                 AND language_id = :language_id');
      $QfaqCheck->bindInt(':products_id', $productId);
      $QfaqCheck->bindInt(':language_id', $languageId);
      $QfaqCheck->execute();

      if (!$QfaqCheck->fetch() || empty($QfaqCheck->value('faq_content'))) {
        return ['success' => false, 'chunks_saved' => 0, 'error' => 'No FAQ content found'];
      }

      $faqGenerator = new FaqEmbeddingGenerator();

      if ($isUpdate) {
        $faqGenerator->deleteEmbeddings($productId, $languageId);
      }

      $faqResult = $faqGenerator->generateEmbeddings($productId, $languageId);

      if ($faqResult['success'] && $this->debug) {
        error_log("ProductEmbeddingBuilder: Generated {$faqResult['chunks_saved']} FAQ chunk(s) for product {$productId}, language {$languageId}");
      }

      return $faqResult;

    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log("ProductEmbeddingBuilder: FAQ exception for product {$productId} - " . $e->getMessage());
      }
      return ['success' => false, 'chunks_saved' => 0, 'error' => $e->getMessage()];
    }
  }

  /**
   * @param string $taxonomy Raw taxonomy output from SemanticAgent
   * @return array Associative array of [dimension => value]
   */
  private function parseTaxonomyTags(string $taxonomy): array
  {
    if (empty($taxonomy)) {
      return [];
    }

    $lines = array_filter(array_map('trim', explode("\n", $taxonomy)));
    $tags = [];

    foreach ($lines as $line) {
      if (preg_match('/^\[([^\]]+)\]:\s*(.+)$/', $line, $matches)) {
        $tags[$matches[1]] = trim($matches[2]);
      }
    }

    return $tags;
  }
}

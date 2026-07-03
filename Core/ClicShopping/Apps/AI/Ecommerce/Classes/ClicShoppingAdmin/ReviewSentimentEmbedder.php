<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\ProductsAdmin;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

/**
 * ReviewSentimentEmbedder — macro (per-product-analysis) sentiment RAG embedding.
 *
 * Encapsulates the `reviews_sentiment_embedding` generation so BOTH the manual
 * admin trigger and the cron produce the same embedding (called from the shared
 * ReviewSentimentGenerator). This is AI/Ecommerce territory: it uses NewVector,
 * SemanticAgent and the `rag` prompt bucket + `text_review_sentiment_*` semantic
 * keys that stay in the AI/Ecommerce language files (the AI-prompt exception).
 *
 * NOTE — this is the MACRO analysis embedding, distinct from the per-review
 * `reviews_embedding` generated at review write time (Shop/ReviewsWrite/Process).
 */
class ReviewSentimentEmbedder
{
  private mixed $app;
  private mixed $semantics;
  private mixed $productsAdmin;
  private mixed $lang;

  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }
    $this->app = Registry::get('Ecommerce');

    if (!Registry::exists('Semantics')) {
      Registry::set('Semantics', new SemanticAgent());
    }
    $this->semantics = Registry::get('Semantics');

    if (!Registry::exists('ProductsAdmin')) {
      Registry::set('ProductsAdmin', new ProductsAdmin());
    }
    $this->productsAdmin = Registry::get('ProductsAdmin');

    $this->lang = Registry::get('Language');
  }

  /**
   * Build + persist the macro sentiment embedding for one analysis (all languages).
   *
   * @param int $sentimentId  reviews_sentiment.id (the analysis row).
   * @param int $reviewCount  Number of reviews analysed (embedding metadata).
   */
  public function embed(int $sentimentId, int $reviewCount): void
  {
    if (!self::isEnabled()) {
      return;
    }

    $Qcheck = $this->app->db->prepare('SELECT id FROM :table_reviews_sentiment_embedding
                                       WHERE entity_id = :entity_id');
    $Qcheck->bindInt(':entity_id', $sentimentId);
    $Qcheck->execute();
    $insert = ($Qcheck->fetch() === false);

    $Qrs = $this->app->db->prepare('SELECT DISTINCT
                                       rs.id,
                                       rs.sentiment_status,
                                       rs.sentiment_approved,
                                       rs.date_added,
                                       rs.products_id,
                                       rs.reviews_id,
                                       rsd.language_id,
                                       rsd.description,
                                       rsd.critic,
                                       rsd.critic_verdict,
                                       rv.vote,
                                       rv.customer_id,
                                       rv.sentiment AS vote_sentiment
                                     FROM :table_reviews_sentiment rs
                                     INNER JOIN :table_reviews_sentiment_description rsd ON rs.id = rsd.id
                                     LEFT JOIN :table_reviews_vote rv
                                       ON rs.reviews_id = rv.reviews_id AND rs.products_id = rv.products_id
                                     WHERE rs.id = :id');
    $Qrs->bindInt(':id', $sentimentId);
    $Qrs->execute();

    foreach ($Qrs->fetchAll() as $item) {
      $languageId   = (int)$item['language_id'];
      $languageCode = $this->lang->getLanguageCodeById($languageId);
      $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/ReviewsSentiment/rag', $languageCode);

      $productsId   = (int)$item['products_id'];
      $productsName = $this->productsAdmin->getProductsName($productsId, $languageId);

      $sentiment_status   = HTMLOverrideCommon::cleanHtmlForEmbedding($item['sentiment_status'] ?? '');
      $sentiment_approved = HTMLOverrideCommon::cleanHtmlForEmbedding($item['sentiment_approved'] ?? '');
      $date_added         = HTMLOverrideCommon::cleanHtmlForEmbedding($item['date_added'] ?? '');
      $description        = HTMLOverrideCommon::cleanHtmlForEmbedding($item['description'] ?? '');
      $critic             = HTMLOverrideCommon::cleanHtmlForEmbedding($item['critic'] ?? '');
      $critic_verdict     = HTMLOverrideCommon::cleanHtmlForEmbedding($item['critic_verdict'] ?? '');
      $vote               = HTMLOverrideCommon::cleanHtmlForEmbedding($item['vote'] ?? '0');
      $customer_id        = HTMLOverrideCommon::cleanHtmlForEmbedding($item['customer_id'] ?? '');
      $vote_sentiment     = HTMLOverrideCommon::cleanHtmlForEmbedding($item['vote_sentiment'] ?? '');

      $embedding_data  = "\n" . $this->app->getDef('text_review_sentiment_semantic_semantic_title', ['products_name' => $productsName]) . "\n";
      $embedding_data .= $this->app->getDef('text_sentiment_semantic_review_sentiment_id', ['review_sentiment_id' => $sentimentId]) . "\n";
      $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_status',        ['products_name' => $productsName]) . ' : ' . $sentiment_status   . "\n";
      $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_approved',      ['products_name' => $productsName]) . ' : ' . $sentiment_approved . "\n";
      $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_date_added',    ['products_name' => $productsName]) . ' : ' . $date_added         . "\n";
      $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_vote',          ['products_name' => $productsName]) . ' : ' . $vote               . "\n";
      $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_customer_id',   ['products_name' => $productsName]) . ' : ' . $customer_id        . "\n";
      $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_vote_sentiment',['products_name' => $productsName]) . ' : ' . $vote_sentiment     . "\n";
      $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_description',   ['products_name' => $productsName]) . ' : ' . $description        . "\n";

      if ($critic !== '') {
        $embedding_data .= 'critic qualité : ' . $critic         . "\n";
        $embedding_data .= 'Verdict critic : ' . $critic_verdict . "\n";
      }

      $taxonomy = $this->semantics->createTaxonomy($description, $languageCode, null);

      $tags = [];
      if (!empty($taxonomy)) {
        foreach (array_filter(array_map('trim', explode("\n", $taxonomy))) as $line) {
          if (preg_match('/^\[([^\]]+)\]:\s*(.+)$/', $line, $m)) {
            $tags[$m[1]] = trim($m[2]);
          }
        }
      }

      $embedding_data .= "\n" . $this->app->getDef('text_review_sentiment_taxonomy') . " :\n";
      foreach ($tags as $key => $value) {
        $embedding_data .= "[$key]: $value\n";
      }

      $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

      $baseMetadata = [
        'review_sentiment_name' => HTMLOverrideCommon::cleanHtmlForEmbedding($productsName),
        'content'               => $description,
        'critic'                => $critic,
        'critic_verdict'        => $critic_verdict,
        'review_count'          => $reviewCount,
        'id'                    => $sentimentId,
        'type'                  => 'review_sentiment',
        'source'                => ['type' => 'manual', 'name' => 'manual'],
        'tags'                  => $taxonomy ? array_filter(array_map(fn($t) => trim(strip_tags($t)), explode("\n", $taxonomy))) : [],
      ];

      $result = NewVector::saveEmbeddingsWithChunks(
        $embeddedDocuments,
        'reviews_sentiment_embedding',
        $sentimentId,
        $languageId,
        $baseMetadata,
        $this->app->db,
        !$insert
      );

      if (!$result['success']) {
        error_log("ReviewSentimentEmbedder: failed to save embeddings for sentiment {$sentimentId} - " . $result['error']);
      }
    }
  }

  /**
   * Embeddings require the RAG/embedding provider to be switched on.
   */
  public static function isEnabled(): bool
  {
    return \defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING')
      && CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'True'
      && \defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS')
      && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True';
  }
}

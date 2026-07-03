<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\ReviewsSentiment;

use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment\ReviewSentimentGenerator;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

class Update implements HooksInterface
{
  public mixed $app;
  public mixed $lang;
  public mixed $semantics;
  protected mixed $messageStack;

  /**
   * Constructor method for initializing the ChatGpt application.
   * Ensures that the ChatGpt instance is registered with the Registry and fetches it for use.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
    $this->lang = Registry::get('Language');
    $this->messageStack = Registry::get('MessageStack');

    if (!Registry::exists('Semantics')) {
      Registry::set('Semantics', new SemanticAgent());
    }
    $this->semantics = Registry::get('Semantics');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/ReviewsSentiment/review_sentiment');
  }


  // ════════════════════════════════════════════════════════════════
  // Main execution
  // ════════════════════════════════════════════════════════════════

  /**
   * Hook entry point.
   *
   * Pipeline:
   *   1. Preliminary checks (GPT status, constants)
   *   2. Review count → minimum threshold validation
   *   3. Generate sentiment summary (if threshold met)
   *   4. Critique summary via critic agent (if critic threshold met)
   *   5. Save to database (update or insert)
   *   6. Generate embeddings
   *
   * @return bool|void  Returns false if GPT unavailable or threshold not met
   */
  public function execute()
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');

    $requiredConstants = [
      'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
      'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
      'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
    ];

    CLICSHOPPING::checkAppsIsActivated($requiredConstants);

    if (!Gpt::checkGptStatus()) {
      $this->messageStack->add($this->app->getDef('text_warning_gpt_disabled'), 'warning');
      return false;
    }

    $embedding_enabled = \defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') && CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'True' && \defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True';

    $id = HTML::sanitize($_GET['rID']);
    $user_admin = AdministratorAdmin::getUserAdmin();

    // ── Generation + persistence (shared with the cron via ReviewSentimentGenerator) ──
    $Qproduct = $this->app->db->get('reviews', 'products_id', ['reviews_id' => (int)$id]);
    $products_id = $Qproduct->valueInt('products_id');

    $result = (new ReviewSentimentGenerator())->generateForProduct($products_id, (int)$id, $user_admin);

    if ($result === null) {
      $this->messageStack->add($this->app->getDef('text_warning_mesage', ['review_number' => ReviewSentimentGenerator::MIN_REVIEWS]), 'warning');

      return false;
    }

    $reviewCount = $result['review_count'];

    $this->messageStack->add($this->app->getDef('text_succes_mesage'), 'success');

    // ── Étape 3 : Embeddings ──

    $Qembcheck = $this->app->db->prepare('SELECT id
                                          FROM :reviews_sentiment_embedding
                                          WHERE entity_id = :entity_id
                                         ');
    $Qembcheck->bindInt(':entity_id', $id);
    $Qembcheck->execute();

    $insert_embedding = ($Qembcheck->fetch() === false);

    $QreviewSentiment = $this->app->db->prepare('SELECT DISTINCT
                                                    rs.id,
                                                    rs.sentiment_status,
                                                    rs.sentiment_approved,
                                                    rs.date_added,
                                                    rs.products_id,
                                                    rs.reviews_id,
                                                    rsd.id,
                                                    rsd.language_id,
                                                    rsd.description,
                                                    rsd.critic,
                                                    rsd.critic_verdict,
                                                    rv.vote,
                                                    rv.customer_id,
                                                    rv.sentiment AS vote_sentiment
                                                  FROM :table_reviews_sentiment rs
                                                  INNER JOIN :table_reviews_sentiment_description rsd
                                                    ON rs.id = rsd.id
                                                  LEFT JOIN :table_reviews_vote rv
                                                    ON rs.reviews_id = rv.reviews_id
                                                   AND rs.products_id = rv.products_id
                                                  WHERE rs.id = :id
                                                 ');
    $QreviewSentiment->bindInt(':id', $id);
    $QreviewSentiment->execute();

    $review_sentiment_array = $QreviewSentiment->fetchAll();
    $review_sentiment_id    = $QreviewSentiment->valueInt('id');

    if (is_array($review_sentiment_array)) {
      foreach ($review_sentiment_array as $item) {
        $language_code = $this->lang->getLanguageCodeById((int)$item['language_id']);
        $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/ReviewsSentiment/rag', $language_code);

        $products_id      = $item['products_id'];
        $products_name    = $CLICSHOPPING_ProductsAdmin->getProductsName($products_id, $item['language_id']);

        $sentiment_status   = isset($item['sentiment_status'])   ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['sentiment_status'])   : '';
        $sentiment_approved = isset($item['sentiment_approved']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['sentiment_approved']) : '';
        $date_added         = isset($item['date_added'])         ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['date_added'])         : '';
        $description        = isset($item['description'])        ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['description'])        : '';
        $critic           = isset($item['critic'])           ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['critic'])           : '';
        $critic_verdict   = isset($item['critic_verdict'])   ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['critic_verdict'])   : '';
        $vote               = isset($item['vote'])               ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['vote'])               : '0';
        $customer_id        = isset($item['customer_id'])        ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['customer_id'])        : '';
        $vote_sentiment     = isset($item['vote_sentiment'])     ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['vote_sentiment'])     : '';

        //********************
        // add embedding
        //********************

        if ($embedding_enabled) {
          $embedding_data  = "\n" . $this->app->getDef('text_review_sentiment_semantic_semantic_title',        ['products_name' => $products_name]) . "\n";
          $embedding_data .= $this->app->getDef('text_sentiment_semantic_review_sentiment_id',         ['review_sentiment_id' => $review_sentiment_id]) . "\n";
          $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_status',               ['products_name' => $products_name]) . ' : ' . $sentiment_status   . "\n";
          $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_approved',             ['products_name' => $products_name]) . ' : ' . $sentiment_approved . "\n";
          $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_date_added',           ['products_name' => $products_name]) . ' : ' . $date_added         . "\n";
          $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_vote',                 ['products_name' => $products_name]) . ' : ' . $vote               . "\n";
          $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_customer_id',          ['products_name' => $products_name]) . ' : ' . $customer_id        . "\n";
          $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_vote_sentiment',       ['products_name' => $products_name]) . ' : ' . $vote_sentiment     . "\n";
          $embedding_data .= $this->app->getDef('text_review_sentiment_semantic_description',          ['products_name' => $products_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($description) . "\n";

          // Critic integration inside embedding
          if (!empty($critic)) {
            $embedding_data .= 'critic qualité : ' . $critic         . "\n";
            $embedding_data .= 'Verdict critic : ' . $critic_verdict . "\n";
          }

          $taxonomy = $this->semantics->createTaxonomy(HTMLOverrideCommon::cleanHtmlForEmbedding($description), $language_code, null);

          $tags = [];
          if (!empty($taxonomy)) {
            $lines = array_filter(array_map('trim', explode("\n", $taxonomy)));
            foreach ($lines as $line) {
              if (preg_match('/^\[([^\]]+)\]:\s*(.+)$/', $line, $matches)) {
                $tags[$matches[1]] = trim($matches[2]);
              }
            }
          }

          $embedding_data .= "\n" . $this->app->getDef('text_review_sentiment_taxonomy') . " :\n";
          foreach ($tags as $key => $value) {
            $embedding_data .= "[$key]: $value\n";
          }

          $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

          $baseMetadata = [
            'review_sentiment_name' => HTMLOverrideCommon::cleanHtmlForEmbedding($products_name),
            'content'               => HTMLOverrideCommon::cleanHtmlForEmbedding($description),
            'critic'              => $critic,
            'critic_verdict'      => $critic_verdict,
            'review_count'          => $reviewCount,
            'id'                    => (int)$item['id'],
            'type'                  => 'review_sentiment',
            'source'                => ['type' => 'manual', 'name' => 'manual'],
            'tags'                  => $taxonomy ? array_filter(array_map(fn($t) => trim(strip_tags($t)), explode("\n", $taxonomy))) : [],
          ];

          $result = NewVector::saveEmbeddingsWithChunks(
            $embeddedDocuments,
            'reviews_sentiment_embedding',
            (int)$item['id'],
            (int)$item['language_id'],
            $baseMetadata,
            $this->app->db,
            !$insert_embedding
          );

          if (!$result['success']) {
            error_log("ReviewsSentiment: Failed to save embeddings for sentiment {$item['id']} - " . $result['error']);
          }
        }
      }
    }
  }
}

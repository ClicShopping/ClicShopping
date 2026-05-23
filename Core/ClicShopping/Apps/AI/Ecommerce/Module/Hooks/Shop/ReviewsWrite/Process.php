<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\Shop\ReviewsWrite;

use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

class Process implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  public mixed $lang;
  public mixed $semantics;
  public mixed $db;

  /**
   * Class constructor.
   *
   * Initializes the ChatGptApp instance in the Registry if it doesn't already exist,
   * and loads the necessary definitions for the application.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');

    if (!Registry::exists('Semantics')) {
      Registry::set('Semantics', new SemanticAgent());
    }
    $this->semantics = Registry::get('Semantics');
  }

  /**
   * Executes the necessary processes based on the provided GET and POST parameters related to category handling.
   *
   * Checks if GPT functionality is enabled and processes category-related inputs to update database records
   * such as descriptions, SEO data (title, description, keywords),
   *
   * @return bool Returns false if GPT functionality is disabled or not applicable; otherwise, performs the operations without returning a value.
   */
  public function execute()
  {
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    if (Gpt::checkGptStatus() === false || !defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') || CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'False' || !defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') || CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'False') {
      return false;
    }

    $embedding_enabled = \defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') && CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'True' && \defined( 'CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True';

    $QreviewsCheck = $this->db->prepare('select r.reviews_id
                                        from :table_reviews r
                                        order by r.reviews_id desc
                                        limit 1
                                       ');
    $QreviewsCheck->execute();
    $review_id = $QreviewsCheck->valueInt('reviews_id');

    $QcheckEntity = $this->app->db->prepare('select id
                                             from :table_reviews_embedding
                                             where entity_id = :entity_id
                                            ');
    $QcheckEntity->bindInt(':entity_id', $review_id);
    $QcheckEntity->execute();

    $insert_embedding = false;

    if ($QcheckEntity->fetch() === false) {
      $insert_embedding = true;
    }

    $Qreviews = $this->db->prepare('select r.reviews_id,
                                           r.reviews_rating,
                                           r.date_added,
                                           r.status,
                                           r.customers_id,
                                           r.products_id,
                                           r.reviews_read,
                                           r.customers_tag,
                                           rd.reviews_text,
                                           rd.languages_id,
                                           rv.vote,
                                           rv.sentiment
                                    from :table_reviews r,
                                         :table_reviews_description rd,
                                         :table_reviews_vote rv
                                    where r.reviews_id = rd.reviews_id
                                    order by r.reviews_id desc
                                    limit 1
                                    ');
    $Qreviews->execute();

    $review_array = $Qreviews->fetchAll();

    if (is_array($review_array)) {
      foreach ($review_array as $item) {
       $language_code = $this->lang->getLanguageCodeById((int)$item['languages_id']);
        $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/ReviewsWrite/process', $language_code);

        $reviews_text = isset($item['reviews_text']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['reviews_text']) : '';
        $reviews_rating = isset($item['reviews_rating']) ? (int)$item['reviews_rating'] : 0;
        $reviews_read = isset($item['reviews_read']) ? (int)$item['reviews_read'] : 0;
        $date_added = isset($item['date_added']) ? $item['date_added'] : '';
        $status = isset($item['status']) ? (int)$item['status'] : 0;
        $customers_id = isset($item['customers_id']) ? (int)$item['customers_id'] : 0;
        $customers_tag = isset($item['customers_tag']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['customers_tag']) : '';
        $vote = isset($item['vote']) ? (int)$item['vote'] : 0;
        $sentiment = isset($item['sentiment']) ? (int)$item['sentiment'] : 0;
        $products_id = isset($item['products_id']) ? (int)$item['products_id'] : 0;
        $products_name = $CLICSHOPPING_ProductsCommon->getProductsName($products_id);

        //********************
        // add embedding
        //********************

      if ($embedding_enabled) {
        $embedding_data = $this->app->getDef('text_reviews') . ' : ' . '\n';
        $embedding_data .= $this->app->getDef('text_products_name') . ' : ' . $products_name . '\n';
        $embedding_data .= $this->app->getDef('text_reviews_description') . ' : ' . $reviews_text . '\n';

        if (!empty($reviews_text)) {
           $embedding_data .= $this->app->getDef('text_reviews_description', ['products_name' => $products_name]) . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($reviews_text) . "\n";

           $taxonomy = $this->semantics->createTaxonomy(HtmlOverrideCommon::cleanHtmlForEmbedding($reviews_text), $language_code, null);

          if (!empty($taxonomy)) {
            $lines = array_filter(array_map('trim', explode("\n", $taxonomy)));
            $tags = [];

            foreach ($lines as $line) {
              if (preg_match('/^\[([^\]]+)\]:\s*(.+)$/', $line, $matches)) {
                $tags[$matches[1]] = trim($matches[2]);
              }
            }
          } else {
            $tags = [];
          }

          $embedding_data .= "\n" . $this->app->getDef('text_reviews_taxonomy') . " :\n";

          foreach ($tags as $key => $value) {
            $embedding_data .= "[$key]: $value\n";
          }
        }

          $embedding_data .= $this->app->getDef('text_reviews_rating') . ' : ' . $reviews_rating . '\n';
          $embedding_data .= $this->app->getDef('text_reviews_read') . ' : ' . $reviews_read . '\n';
          $embedding_data .= $this->app->getDef('text_reviews_date_added') . ' : ' . $date_added . '\n';
          $embedding_data .= $this->app->getDef('text_reviews_status') . ' : ' . $status . '\n';
          $embedding_data .= $this->app->getDef('text_reviews_customer_id') . ' : ' . $customers_id . '\n';
          $embedding_data .= $this->app->getDef('text_reviews_customer_tag') . ' : ' . $customers_tag . '\n';
          $embedding_data .= $this->app->getDef('text_reviews_customer_vote') . ' : ' . $vote . '\n';
          $embedding_data .= $this->app->getDef('text_reviews_customer_sentiment') . ' : ' . $sentiment . '\n';
          $embedding_data .= $this->app->getDef('text_reviews_products_id') . ' : ' . $products_id . '\n';


          $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

          // Prepare base metadata for centralized chunk management
          $baseMetadata = [
            'review_name' => HTMLOverrideCommon::cleanHtmlForEmbedding($products_name),
            'content' => HtmlOverrideCommon::cleanHtmlForEmbedding($reviews_text),
            'type' => 'reviews',  // Entity type (goes in 'type' column)
            'reviews_id' => (int)$item['reviews_id'],
            'tags' => $taxonomy ? array_filter(array_map(fn($t) => trim(strip_tags($t)), explode("\n", $taxonomy))) : [],
            'source' => ['type' => 'manual', 'name' => 'manual']  // Goes in 'sourcetype' and 'sourcename' columns
          ];

          // Save all chunks using centralized method
          $result = NewVector::saveEmbeddingsWithChunks(
            $embeddedDocuments,
            'reviews_embedding',  // Table name
            (int)$item['reviews_id'],
            (int)$item['languages_id'],
            $baseMetadata,
            $this->app->db,
            !$insert_embedding  // isUpdate = true if not inserting (i.e., updating existing entity)
          );

          if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
            if (!$result['success']) {
              error_log("Shop/ReviewsWrite: Failed to save embeddings - " . $result['error']);
            } else {
              error_log("Shop/ReviewsWrite: Successfully saved {$result['chunks_saved']} chunk(s) for review {$item['reviews_id']}");
            }
          }
        }
      }
    }
  }
}
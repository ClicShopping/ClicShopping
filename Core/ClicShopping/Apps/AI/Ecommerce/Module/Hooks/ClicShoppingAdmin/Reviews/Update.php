<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Reviews;

use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
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
    $this->lang = Registry::get('Language');

    if (!Registry::exists('Semantics')) {
      Registry::set('Semantics', new SemanticAgent());
    }

    $this->semantics = Registry::get('Semantics');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Reviews/rag');
  }

  /**
   * Re-syncs the edited review's embedding (admin review edit form).
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Update'], $_GET['Reviews'], $_GET['rID'])) {
      $this->sync((int)$_GET['rID']);
    }
  }

  /**
   * Aligns the review's embedding with its moderation status: approved (status 1) is embedded,
   * anything else is removed, so a pending review never reaches the RAG.
   *
   * @param int $rID The review ID.
   * @return void
   */
  public function sync(int $rID): void
  {
    if (!self::isEnabled()) {
      return;
    }

    $QreviewStatus = $this->app->db->prepare('select status from :table_reviews where reviews_id = :reviews_id');
    $QreviewStatus->bindInt(':reviews_id', $rID);
    $QreviewStatus->execute();

    if ($QreviewStatus->valueInt('status') !== 1) {
      $this->app->db->delete('reviews_embedding', ['entity_id' => (int)$rID]);
      return;
    }

    $CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');

        $Qcheck = $this->app->db->prepare('select id
                                           from :table_reviews_embedding
                                           where entity_id = :entity_id
                                          ');
        $Qcheck->bindInt(':entity_id', $rID);
        $Qcheck->execute();

        $insert_embedding = false;

        if ($Qcheck->fetch() === false) {
          $insert_embedding = true;
        }

        $Qreviews = $this->app->db->prepare('select r.reviews_id,
                                                    r.products_id,
                                                    r.reviews_rating,
                                                    r.date_added,
                                                    r.status,
                                                    r.customers_tag,
                                                    rd.reviews_text,
                                                    rd.languages_id,
                                                    rv.vote,
                                                    rv.sentiment
                                              from :table_reviews r
                                              join :table_reviews_description rd on rd.reviews_id = r.reviews_id
                                              left join :table_reviews_vote rv on rv.reviews_id = r.reviews_id
                                              where r.reviews_id = :reviews_id
                                              and r.status = 1
                                              ');
        $Qreviews->bindInt(':reviews_id', $rID);
        $Qreviews->execute();

        $reviews_array = $Qreviews->fetchAll();
        $reviews_id = $rID;

        foreach ($reviews_array as $item) {
      	  $language_code = $this->lang->getLanguageCodeById((int)$item['languages_id']);
          $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Reviews/rag', $language_code);		    
		    
          $products_id = $item['products_id'];
          $reviews_text = $item['reviews_text'];
          $reviews_rating = $item['reviews_rating'];
          $date_added = $item['date_added'];
          $taxonomy = '';
          $customers_tag = $item['customers_tag'];
          $vote = $item['vote'];
          $sentiment = $item['sentiment'];

          $language_id = (int)$item['languages_id'];
          $products_name = $CLICSHOPPING_ProductsAdmin->getProductsName($products_id, $language_id);
          // Only approved reviews reach this point (see the status gate above).
          $status = $this->app->getDef('text_status_active', ['products_name' => $products_name]);

          //********************
          // add embedding
          //********************

            $embedding_data = $this->app->getDef('text_reviews', ['products_name' => $products_name]) . "\n";
            $embedding_data .= $this->app->getDef('text_reviews_id', ['reviews_id' => $reviews_id]) . "\n";

            if (!empty($products_id)) {
              $embedding_data .= $this->app->getDef('text_reviews_product_name', ['products_name' => $products_name]) . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_name) . "\n";
            }


            if (!empty($reviews_rating)) {
              $embedding_data .= $this->app->getDef('text_reviews_rating', ['products_name' => $products_name]) . ': ' . (float)$reviews_rating . "\n";
            }

            if (!empty($date_added)) {
              $embedding_data .= $this->app->getDef('text_reviews_date_added', ['products_name' => $products_name]) . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($date_added) . "\n";
            }

            if (!empty($reviews_text)) {
              $embedding_data .= $this->app->getDef('text_reviews_description', ['products_name' => $products_name]) . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($reviews_text) . "\n";

              $taxonomy_text = HTMLOverrideCommon::cleanHtmlForEmbedding($reviews_text);
              $taxonomy = $this->semantics->createTaxonomy($taxonomy_text, $this->app->getDef('text_create_taxonomy', ['document_text' => $taxonomy_text]), $language_code, 300);

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

              if ($tags !== []) {
                $embedding_data .= "\n" . $this->app->getDef('text_reviews_taxonomy') . " :\n";

                foreach ($tags as $key => $value) {
                  $embedding_data .= "[$key]: $value\n";
                }
              }
            }

          if (!empty($status)) {
            $embedding_data .= $this->app->getDef('text_reviews_status', ['products_name' => $products_name]) . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($status) . "\n";
          }

          if (!empty($customers_tag)) {
            $embedding_data .= $this->app->getDef('text_reviews_customer_tag', ['products_name' => $products_name]) . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($customers_tag) . "\n";
          }

          if (!empty($vote)) {
            $embedding_data .= $this->app->getDef('text_reviews_customer_vote', ['products_name' => $products_name]) . ': ' . (int)$vote . "\n";
          }

          if (!empty($sentiment)) {
            $embedding_data .= $this->app->getDef('text_reviews_customer_sentiment', ['products_name' => $products_name]) . ': ' . (float)$sentiment . "\n";
          }

          try {
            $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

            // Prepare base metadata
            $baseMetadata = [
              'review_name' => HTMLOverrideCommon::cleanHtmlForEmbedding($products_name),
              'content' => HTMLOverrideCommon::cleanHtmlForEmbedding($reviews_text),
              'reviews_id' => (int)$item['reviews_id'],
              'type' => 'reviews',
              'source' => [
                'type' => 'manual',
                'name' => 'manual'
              ],
              'tags' => $taxonomy ? array_filter(array_map(fn($t) => trim(strip_tags($t)), explode("\n", $taxonomy))) : []
            ];

            // Save all chunks using centralized method
            $result = NewVector::saveEmbeddingsWithChunks(
              $embeddedDocuments,
              'reviews_embedding',
              (int)$item['reviews_id'],
              (int)$language_id,
              $baseMetadata,
              $this->app->db,
              !$insert_embedding  // isUpdate = true if not inserting
            );

            if (!$result['success']) {
              error_log("Reviews/Update: Failed to save embeddings for review {$item['reviews_id']} - " . $result['error']);
            } else {
              error_log("Reviews/Update: Successfully saved {$result['chunks_saved']} chunks for review {$item['reviews_id']}");
            }
          } catch (\Throwable $e) {
            error_log("Reviews/Update: Embedding exception for review {$item['reviews_id']} - " . $e->getMessage());
          }
        }
  }

  /**
   * @return bool True when the Ecommerce app, GPT and RAG embedding are all enabled.
   */
  private static function isEnabled(): bool
  {
    $requiredConstants = [
      'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
      'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
      'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
    ];

    return CLICSHOPPING::checkAppsIsActivated($requiredConstants) && Gpt::checkGptStatus();
  }
}
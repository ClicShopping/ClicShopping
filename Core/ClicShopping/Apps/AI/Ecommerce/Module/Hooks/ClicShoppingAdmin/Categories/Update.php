<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Categories;

use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

class Update implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  public mixed $lang;
  public mixed $semantics;

  /**
   * Class constructor.
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
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Categories/rag');
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
    $requiredConstants = [
      'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
      'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
      'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
    ];

    CLICSHOPPING::checkAppsIsActivated($requiredConstants);

    if (!Gpt::checkGptStatus()) {
      return false;
    }

    $embedding_enabled = \defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') && CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'True' && \defined( 'CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True';

    if (isset($_GET['Update'], $_GET['Categories'])) {
      if (isset($_GET['cID'])) {
        $cID = HTML::sanitize($_GET['cID']);

        $Qcheck = $this->app->db->prepare('select id
                                           from :table_categories_embedding
                                           where entity_id = :entity_id
                                          ');
        $Qcheck->bindInt(':entity_id', $cID);
        $Qcheck->execute();

        $insert_embedding = false;

        if ($Qcheck->fetch() === false) {
          $insert_embedding = true;
        }

        $Qcategories = $this->app->db->prepare('select categories_id,
                                                       categories_name,
                                                       categories_description,
                                                       categories_head_title_tag,
                                                       categories_head_desc_tag,
                                                       categories_head_keywords_tag,
                                                       language_id
                                             from :table_categories_description
                                             where categories_id = :categories_id
                                            ');
        $Qcategories->bindInt(':categories_id', $cID);
        $Qcategories->execute();

        $categories_array = $Qcategories->fetchAll();

        if (is_array($categories_array)) {
          foreach ($categories_array as $item) {
            $language_code = $this->lang->getLanguageCodeById((int)$item['language_id']);

            $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Categories/seo_chat_gpt', $language_code);
            $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Categories/rag', $language_code);

            $categories_id = $item['categories_id'];
            $categories_name = $item['categories_name'];
            $categories_description = $item['categories_description'];
            $seo_categories_title = $item['categories_head_title_tag'];
            $seo_categories_description = $item['categories_head_desc_tag'];
            $seo_categories_keywords = $item['categories_head_keywords_tag'];

            //********************
            // add embedding
            //********************
            if ($embedding_enabled) {
            $taxonomy = '';
            $embedding_data =  "\n" . $this->app->getDef('text_category_embedded') . "\n";

            $embedding_data .= $this->app->getDef('text_category_name') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($categories_name) . "\n";
            $embedding_data .= $this->app->getDef('text_category_id') . ' : ' . (int)$categories_id . "\n";

            if (!empty($categories_description)) {
              $categories_description = HTMLOverrideCommon::cleanHtmlForEmbedding($categories_description);
              $embedding_data .= $this->app->getDef('text_category_description', ['category_name' => $categories_name]) . ' : ' . $categories_description . "\n";;

              $taxonomy = $this->semantics->createTaxonomy($categories_description, $language_code, null);

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

                // Ajouter à embedding_data en texte pour l'embedding
                $embedding_data .= "\n" . $this->app->getDef('text_category_taxonomy') . " :\n";
                foreach ($tags as $key => $value) {
                $embedding_data .= "[$key]: $value\n";
              }
            }

              if (!empty($seo_categories_title)) {
                $embedding_data .= $this->app->getDef('text_category_seo_title', ['category_name' => $categories_name]) . ' : ' .  HTMLOverrideCommon::cleanHtmlForSEO($seo_categories_title) . "\n";
              }

              if (!empty($seo_categories_description)) {
                $embedding_data .= $this->app->getDef('text_category_seo_description', ['category_name' => $categories_name]) . ' : ' .  HTMLOverrideCommon::cleanHtmlForSEO($seo_categories_description) . "\n";
              }

              if (!empty($seo_categories_keywords)) {
                $embedding_data .= $this->app->getDef('text_category_seo_keywords', ['category_name' => $categories_name]) . ' : ' .  HTMLOverrideCommon::cleanHtmlForSEO($seo_categories_keywords) . "\n";
              }

              try {
                // Generate embeddings
                $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

                // Prepare base metadata
                $baseMetadata = [
                  'category_name' => $categories_name,
                  'content' => $categories_description,
                  'type' => 'categories',
                  'source' => [
                    'type' => 'manual',
                    'name' => 'manual'
                  ],
                  'tags' => $taxonomy ? array_filter(array_map(fn($t) => trim(strip_tags($t)), explode("\n", $taxonomy))) : []
                ];

                // Save all chunks using centralized method
                $result = NewVector::saveEmbeddingsWithChunks(
                  $embeddedDocuments,
                  'categories_embedding',
                  (int)$categories_id,
                  (int)$item['language_id'],
                  $baseMetadata,
                  $this->app->db,
                  !$insert_embedding  // isUpdate = true if not inserting
                );

                if (!$result['success']) {
                  error_log("Categories/Update: Failed to save embeddings for category {$categories_id} - " . $result['error']);
                } else {
                  error_log("Categories/Update: Successfully saved {$result['chunks_saved']} chunks for category {$categories_id}");
                }
              } catch (\Throwable $e) {
                error_log("Categories/Update: Embedding exception for category {$categories_id} - " . $e->getMessage());
              }
            }
          }
        }
      }
    }
  }
}
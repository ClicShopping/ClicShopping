<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Categories;

use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;
use ClicShopping\Apps\Marketing\SEO\Classes\ClicShoppingAdmin\SeoAdmin;

/**
 * Class Insert
 *
 * This class handles the insertion of product data into the database.
 * It generates SEO metadata, summaries, and translations based on product information,
 * and also creates categories-related images if specified.
 */

class Insert implements HooksInterface
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
   * such as descriptions, SEO data (title, description, keywords), and optionally images.
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

    if (isset($_GET['Insert'], $_GET['Categories'])) {
      $translate_language = $this->app->getDef('text_seo_page_translate_language');

      $CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');

      $Qcheck = $this->app->db->prepare('select categories_id
                                            from :table_categories
                                            order by categories_id desc
                                            limit 1
                                          ');
      $Qcheck->execute();

      if ($Qcheck->valueInt('categories_id') !== null) {
        $Qcategories = $this->app->db->prepare('select categories_id,
                                                       categories_name,
                                                       language_id
                                                 from :table_categories_description
                                                 where categories_id = :categories_id
                                                ');
        $Qcategories->bindInt(':categories_id', $Qcheck->valueInt('categories_id'));
        $Qcategories->execute();

        $categories_array = $Qcategories->fetchAll();

        if (is_array($categories_array)) {
          foreach ($categories_array as $item) {
            $categories_name = $CLICSHOPPING_CategoriesAdmin->getCategoryName($item['categories_id'], $item['language_id']);
            $language_name = $this->lang->getLanguagesName($item['language_id']);
            $language_code = $this->lang->getLanguageCodeById((int)$item['language_id']);

            $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Categories/seo_chat_gpt', $language_code);
            $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Categories/rag', $language_code);

            $categories_id = $item['categories_id'];
	    
            $update_sql_data = [
              'language_id' => $item['language_id'],
              'categories_id' => $categories_id
            ];

            //-------------------
            // categories description
            //-------------------
            $categories_description = '';

            if (isset($_POST['option_gpt_description'])) {
              $question_description = $this->app->getDef('text_categories_description', ['category_name' => $categories_name]);
              $categories_description = $translate_language . ' ' . $language_name . ' ' . $question_description;
              $categories_description = Gpt::getGptResponse($categories_description);

              if ($categories_description !== false) {
                $sql_data_array = [
                  'categories_description' => SeoAdmin::normalizeSeoDescription($categories_description),
                ];

                $this->app->db->save('categories_description', $sql_data_array, $update_sql_data);
              }
            }

            //-------------------
            // Seo Title
            //-------------------
            $seo_categories_title = '';
            if (isset($_POST['option_gpt_seo_title'])) {
              $question = $this->app->getDef('text_seo_page_title_question', ['category_name' => $categories_name]);

              $seo_categories_title = $translate_language . ' ' . $language_name . ' : ' . $question;
              $seo_categories_title = Gpt::getGptResponse($seo_categories_title);

              if ($seo_categories_title !== false) {
                $sql_data_array = [
                  'categories_head_title_tag' => SeoAdmin::normalizeSeoTitle($seo_categories_title),
                ];

                $this->app->db->save('categories_description', $sql_data_array, $update_sql_data);
              }
            }

            //-------------------
            // Seo description
            //-------------------
            $seo_categories_description = '';
	    
            if (isset($_POST['option_gpt_seo_description'])) {
              $question_summary_description = $this->app->getDef('text_seo_page_summary_description_question', ['category_name' => $categories_name]);

              $seo_categories_description = $translate_language . ' ' . $language_name . ' : ' . $question_summary_description;
              $seo_categories_description = Gpt::getGptResponse($seo_categories_description);

              if ($seo_categories_description !== false) {
                $sql_data_array = [
                  'categories_head_desc_tag' => strip_tags($seo_categories_description) ?? '',
                ];

                $this->app->db->save('categories_description', $sql_data_array, $update_sql_data);
              }
            }
            //-------------------
            // Seo keywords
            //-------------------
            $seo_categories_keywords = '';
	    
            if (isset($_POST['option_gpt_seo_keywords'])) {
              $question_keywords = $this->app->getDef('text_seo_page_keywords_question', ['category_name' => $categories_name]);

              $seo_categories_keywords = $translate_language . ' ' . $language_name . ' : ' . $question_keywords;
              $seo_categories_keywords = Gpt::getGptResponse($seo_categories_keywords);

              if ($seo_categories_keywords !== false) {
                $sql_data_array = [
                  'categories_head_keywords_tag' => SeoAdmin::normalizeSeoKeywords($seo_categories_keywords),
                ];

                $this->app->db->save('categories_description', $sql_data_array, $update_sql_data);
              }
            }

              //********************
              // add embedding
              //********************

            if ($embedding_enabled) {
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

                $embedding_data .= "\n" . $this->app->getDef('text_category_taxonomy') . " :\n";

                foreach ($tags as $key => $value) {
                  $embedding_data .= "[$key]: $value\n";
                }
              }

              if (!empty($seo_categories_title)) {
                $embedding_data .= $this->app->getDef('text_category_seo_title', ['category_name' => $categories_name]) . ' : ' .  HTMLOverrideCommon::cleanHtmlForSEO($seo_categories_title) . "\n";
              }

              if (!empty($seo_categories_description)) {
                $embedding_data .= $this->app->getDef('text_category_seo_description', ['category_name' => $categories_name]) . ': ' .  HTMLOverrideCommon::cleanHtmlForSEO($seo_categories_description) . "\n";
              }

              if (!empty($seo_categories_keywords)) {
                $embedding_data .= $this->app->getDef('text_category_seo_keywords', ['category_name' => $categories_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForSEO($seo_categories_keywords) . "\n";
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
                  (int)$item['categories_id'],
                  (int)$item['language_id'],
                  $baseMetadata,
                  $this->app->db,
                  false  // isUpdate = false for insert
                );

                if (!$result['success']) {
                  error_log("Categories/Insert: Failed to save embeddings for category {$categories_id} - " . $result['error']);
                } else {
                  error_log("Categories/Insert: Successfully saved {$result['chunks_saved']} chunks for category {$categories_id}");
                }
              } catch (\Throwable $e) {
                error_log("Categories/Insert: Embedding exception for category {$categories_id} - " . $e->getMessage());
              }
            }
          }
        }
//-------------------
//image
//-------------------
/*
        if (isset($_POST['option_gpt_create_image'])) {
          $Qcategories = $this->app->db->prepare('select categories_name,
                                                           language_id
                                                    from :table_categories_description
                                                    where categories_id = :categories_id
                                                    and language_id = 1
                                                  ');
          $Qcategories->bindInt(':categories_id', $Qcheck->valueInt('categories_id'));
          $Qcategories->execute();

          $image = Gpt::createImageChatGpt($Qcategories->value('categories_name'), 'categories', '256x256');

          if (!empty($image) || $image !== false) {
            $sql_data_array = [
              'categories_image' => $image ?? '',
            ];

            $update_sql_data = ['categories_id' => $Qcheck->valueInt('categories_id')];

            $this->app->db->save('categories', $sql_data_array, $update_sql_data);
          }
}
*/
      }
    }
  }
}
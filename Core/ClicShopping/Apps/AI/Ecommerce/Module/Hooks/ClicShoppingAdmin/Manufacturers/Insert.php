<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Manufacturers;

use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;
use ClicShopping\Apps\Marketing\SEO\Classes\ClicShoppingAdmin\SeoAdmin;

class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  public mixed $lang;
  public mixed $semantics;
  /**
   * Constructor method for initializing the ChatGpt application.
   * It ensures that the ChatGpt instance is registered in the Registry.
   * Loads the necessary definitions for the specified module hook.
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
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Manufacturer/rag');
  }

  /**
   * Processes the execution related to manufacturers data management and updates in the database.
   * This includes generating SEO metadata (e.g., titles, descriptions, tags, keywords),
   * summaries, and translations based on manufacturer information, as well as optional
   *
   * @return void
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

    if (isset($_GET['Insert'], $_GET['Manufacturers'])) {
      $translate_language = $this->app->getDef('text_seo_page_translate_language');

      $Qcheck = $this->app->db->prepare('select manufacturers_id,
                                                manufacturers_name,
                                                suppliers_id
                                          from :table_manufacturers
                                          order by manufacturers_id desc
                                          limit 1
                                        ');
      $Qcheck->execute();

      $manufacturers_name = $Qcheck->value('manufacturers_name');
      $manufacturers_id = $Qcheck->valueInt('manufacturers_id');
      $suppliers_id = $Qcheck->valueInt('suppliers_id');

      if ($manufacturers_id !== null) {
        $Qmanufacturers = $this->app->db->prepare('select manufacturers_id,
                                                          languages_id
                                                from :table_manufacturers_info
                                                where manufacturers_id = :manufacturers_id
                                              ');
        $Qmanufacturers->bindInt(':manufacturers_id', $manufacturers_id);
        $Qmanufacturers->execute();

        $manufacturers_array = $Qmanufacturers->fetchAll();

        if (is_array($manufacturers_array)) {
          foreach ($manufacturers_array as $item) {
           $language_name = $this->lang->getLanguagesName($item['languages_id']);
           $language_code = $this->lang->getLanguageCodeById((int)$item['languages_id']);

            $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Manufacturers/seo_chat_gpt', $language_code);
            $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Manufacturers/rag', $language_code);

            $manufacturers_id = $item['manufacturers_id'];

            $update_sql_data = [
              'languages_id' => $item['languages_id'],
              'manufacturers_id' => $item['manufacturers_id']
            ];

            //-------------------
            // Manufacturer description
            //-------------------

            if (isset($_POST['option_gpt_description'])) {
              $question_summary_description = $this->app->getDef('text_seo_page_summary_description_question', ['brand_name' => $manufacturers_name]);

              $manufacturers_description = $translate_language . ' ' . $language_name . ' : ' . $question_summary_description;
              $manufacturers_description = Gpt::getGptResponse($manufacturers_description);

              if ($manufacturers_description !== false) {
                $sql_data_array = [
                  'manufacturer_description' => SeoAdmin::normalizeSeoDescription($manufacturers_description),
                ];

                $this->app->db->save('manufacturers_info', $sql_data_array, $update_sql_data);
              }
            }

            ////-------------------
            // Seo Title
            //-------------------
            if (isset($_POST['option_gpt_seo_title'])) {
              $question = $this->app->getDef('text_seo_page_title_question', ['brand_name' => $manufacturers_name]);

              $seo_manufacturer_title = $translate_language . ' ' . $language_name . ' : ' . $question;
              $seo_manufacturer_title = Gpt::getGptResponse($seo_manufacturer_title);

              if ($seo_manufacturer_title !== false) {
                $sql_data_array = [
                  'manufacturer_seo_title' => SeoAdmin::normalizeSeoTitle($seo_manufacturer_title) ?? '',
                ];

                $this->app->db->save('manufacturers_info', $sql_data_array, $update_sql_data);
              }
             }

            //-------------------
            // Seo description
            //-------------------
            if (isset($_POST['option_gpt_seo_description'])) {
              $question_summary_description = $this->app->getDef('text_seo_page_summary_description_question', ['brand_name' => $manufacturers_name]);

              $seo_manufacturer_description = $translate_language . ' ' . $language_name . ' : ' . $question_summary_description;
              $seo_manufacturer_description = Gpt::getGptResponse($seo_manufacturer_description);

              if ($seo_manufacturer_description !== false) {
                $sql_data_array = [
                  'manufacturer_seo_description' => strip_tags($seo_manufacturer_description) ?? '',
                ];

                $this->app->db->save('manufacturers_info', $sql_data_array, $update_sql_data);
              }
            }
            //-------------------
            // Seo keywords
            //-------------------
            if (isset($_POST['option_gpt_seo_keywords'])) {
              $question_keywords = $this->app->getDef('text_seo_page_keywords_question', ['brand_name' => $manufacturers_name]);

              $seo_manufacturer_keywords = $translate_language . ' ' . $language_name . ' : ' . $question_keywords;
              $seo_manufacturer_keywords = Gpt::getGptResponse($seo_manufacturer_keywords);

              if ($seo_manufacturer_keywords !== false) {
                $sql_data_array = [
                  'manufacturer_seo_keyword' => SeoAdmin::normalizeSeoKeywords($seo_manufacturer_keywords)
                ];

                $this->app->db->save('manufacturers_info', $sql_data_array, $update_sql_data);
              }
            }

            //********************
            // add embedding
            //********************

            if ($embedding_enabled) {
              $embedding_data =  "\n" . $this->app->getDef('text_manufacturer_embedded') . "\n";

              $embedding_data .= $this->app->getDef('text_manufacturer_name') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($manufacturers_name) . "\n";
              $embedding_data .= $this->app->getDef('text_manufacturer_id') . ' : ' . (int)$manufacturers_id . "\n";

              if (!empty($manufacturers_description)) {
                $embedding_data .= $this->app->getDef('text_manufacturer_description') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($manufacturers_description) . "\n";
                $taxonomy = $this->semantics->createTaxonomy(HTMLOverrideCommon::cleanHtmlForEmbedding($manufacturers_description), $language_code, null);

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

                $embedding_data .= "\n" . $this->app->getDef('text_manufacturer_taxonomy') . " :\n";

                foreach ($tags as $key => $value) {
                  $embedding_data .= "[$key]: $value\n";
                }
              }

              if (!empty($seo_manufacturer_title)) {
                $embedding_data .= $this->app->getDef('text_manufacturer_seo_title') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($seo_manufacturer_title) . "\n";
              }

              if (!empty($seo_manufacturer_description)) {
                $embedding_data .= $this->app->getDef('text_manufacturer_seo_description') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($seo_manufacturer_description) . "\n";
              }

              if (!empty($seo_manufacturer_keywords)) {
                $embedding_data .= $this->app->getDef('text_manufacturer_seo_keywords') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($seo_manufacturer_keywords) . "\n";
              }

              if (!empty($suppliers_id)) {
                $embedding_data .= $this->app->getDef('text_manufacturer_suppliers_id') . ' : ' . $suppliers_id . "\n";
              }

              // Generate embeddings
              $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

              // Prepare base metadata
              $baseMetadata = [
                'brand_name' => $manufacturers_name,
                'content' => $manufacturers_description ?? '',
                'manufacturer_id' => (int)$manufacturers_id,
                'type' => 'manufacturers',
                'tags' => isset($tags) ? $tags : [],
                'source' => ['type' => 'manual', 'name' => 'manual']
              ];

              // Save all chunks using centralized method
              $result = NewVector::saveEmbeddingsWithChunks(
                $embeddedDocuments,
                'manufacturers_embedding',
                (int)$item['manufacturers_id'],
                (int)$item['languages_id'],
                $baseMetadata,
                $this->app->db,
                false  // isUpdate = false for insert
              );

              if (!$result['success']) {
                error_log("Manufacturers Insert: Failed to save embeddings - " . $result['error']);
              } else {
                error_log("Manufacturers Insert: Successfully saved {$result['chunks_saved']} chunks for manufacturer {$manufacturers_id}");
              }
            }
          }
        }
      }
	//-------------------
	//image
	//-------------------
	/*
	      if (isset($_POST['option_gpt_create_image'])) {
	        $image = Gpt::createImageChatGpt($manufacturers_name, 'manufacturers');

	        if (!empty($image) || $image !== false) {
	          $sql_data_array = [
	            'manufacturers_image' => $image ?? '',
	          ];

	          $update_sql_data = [
	            'manufacturers_id' => $Qcheck->valueInt('manufacturers_id')
	          ];

	          $this->app->db->save('manufacturers', $sql_data_array, $update_sql_data);
	        }
	}
	*/
    }
  }
}
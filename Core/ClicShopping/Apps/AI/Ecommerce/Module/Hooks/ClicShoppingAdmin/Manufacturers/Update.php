<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Manufacturers;

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
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Manufacturer/rag');
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

    $embedding_enabled = \defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') && CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'True' && \defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True';

    if (isset($_GET['Update'], $_GET['Manufacturers'])) {
      if (isset($_GET['mID'])) {
        $mID = HTML::sanitize($_GET['mID']);

        $Qcheck = $this->app->db->prepare('select id
                                           from :table_manufacturers_embedding
                                           where entity_id = :entity_id
                                          ');

        $Qcheck->bindInt(':entity_id', $mID);
        $Qcheck->execute();

        $total = $Qcheck->rowCount();

        $insert_embedding = false;

        if ($total === 0) {
          $insert_embedding = true;
        }

        $Qmanufacturers = $this->app->db->prepare('select m.manufacturers_id,
                                                         m.manufacturers_name,
                                                         mi.manufacturer_description,
                                                         mi.manufacturer_seo_title,
                                                         mi.manufacturer_seo_description,
                                                         mi.manufacturer_seo_keyword,
                                                         mi.languages_id 
                                                   from :table_manufacturers m,
                                                        :table_manufacturers_info mi
                                                   where m.manufacturers_id = :manufacturers_id
                                                   and m.manufacturers_id = mi.manufacturers_id
                                                  ');
        $Qmanufacturers->bindInt(':manufacturers_id', $mID);
        $Qmanufacturers->execute();

        $manufacturers_array = $Qmanufacturers->fetchAll();

        if (is_array($manufacturers_array)) {
          foreach ($manufacturers_array as $item) {
            $language_code = $this->lang->getLanguageCodeById((int)$item['languages_id']);

            $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Manufacturers/seo_chat_gpt', $language_code);
            $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Manufacturers/rag', $language_code);

            $languages_id = $item['languages_id'];
            $manufacturers_id = $item['manufacturers_id'];
            $manufacturers_name = $item['manufacturers_name'];
            $manufacturers_description = $item['manufacturer_description'];
            $seo_manufacturer_title = $item['manufacturer_seo_title'];
            $seo_manufacturer_description = $item['manufacturer_seo_description'];
            $seo_manufacturer_keywords = $item['manufacturer_seo_keyword'];

//********************
// add embedding
//********************

            if ($embedding_enabled) {
              $embedding_data = "\n" . $this->app->getDef('text_manufacturer_embedded') . "\n";

              $embedding_data .= $this->app->getDef('text_manufacturer_name') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($manufacturers_name) . "\n";
              $embedding_data .= $this->app->getDef('text_manufacturer_id') . ' : ' . (int)$manufacturers_id . "\n";

              if (!empty($manufacturers_description)) {
                $embedding_data .= $this->app->getDef('text_manufacturer_description') . ' : ' . HtmlOverrideCommon::cleanHtmlForEmbedding($manufacturers_description) . "\n";
                $taxonomy = $this->semantics->createTaxonomy(HtmlOverrideCommon::cleanHtmlForEmbedding($manufacturers_description), $language_code, null);

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
                (int)$manufacturers_id,
                (int)$languages_id,
                $baseMetadata,
                $this->app->db,
                !$insert_embedding  // isUpdate = true if not inserting
              );

              if (!$result['success']) {
                error_log("Manufacturers Update: Failed to save embeddings - " . $result['error']);
              } else {
                error_log("Manufacturers Update: Successfully saved {$result['chunks_saved']} chunks for manufacturer {$manufacturers_id}");
              }
            }
          }
        }
      }
    }
  }
}
<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;
use ClicShopping\Apps\Marketing\SEO\Classes\ClicShoppingAdmin\SeoAdmin;

/**
 * Class Insert
 *
 * This class handles the insertion of product data into the database.
 * It generates SEO metadata, summaries, and translations based on product information,
 * and also creates product-related images if specified.
 */

class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  public mixed $lang;
  public mixed $semantics;
  private bool $debug;
  
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
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/rag');
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
  }

  /**
   * Processes the execution related to product data management and updates in the database.
   * This includes generating SEO metadata (e.g., titles, descriptions, tags, keywords),
   * summaries, and translations based on product information, as well as optional
   * operations like creating product-related images or updating descriptions.
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
    
    if (isset($_GET['Insert'], $_GET['Products'])) {
      $Qproducts = $this->app->db->prepare('select products_id
                                          from :table_products
                                          order by products_id desc
                                          limit 1
                                          ');
       $Qproducts->execute();

      $products_id = $Qproducts->valueInt('products_id');
      
      $translate_language = $this->app->getDef('text_seo_page_translate_language');

      if (!empty($products_id) || $products_id !== null) {
        $Qproducts = $this->app->db->prepare('select p.products_id,
                                                     p.products_model,
                                                     p.manufacturers_id,
                                                     p.products_ean,
                                                     p.products_sku,
                                                     p.products_date_added,
                                                     p.products_status,
                                                     p.products_ordered,
                                                     p.products_quantity,
                                                     p.products_quantity_alert,
                                                     pd.products_name,
                                                     pd.products_description,
                                                     pd.products_head_title_tag,
                                                     pd.products_head_desc_tag,
                                                     pd.products_head_keywords_tag,
                                                     pd.products_head_tag,
                                                     pd.products_description_summary,
                                                     pd.language_id
                                                from :table_products p,
                                                     :table_products_description pd
                                                where p.products_id = :products_id
                                                and p.products_id = pd.products_id
                                              ');
        $Qproducts->bindInt(':products_id', $products_id);
        $Qproducts->execute();

        $products_array = $Qproducts->fetchAll();

        $Qcategories = $this->app->db->get('products_to_categories', 'categories_id', ['products_id' => $products_id]);

        if (is_array($products_array)) {
          foreach ($products_array as $item) {
            $products_id = $item['products_id'];
            $products_name = $item['products_name'];
            $products_model = $item['products_model'];
            $products_ean = $item['products_ean'];
            $products_sku = $item['products_sku'];
            $products_date_added = $item['products_date_added'];
            $products_status = $item['products_status'];
            $products_ordered = $item['products_ordered'];
            $products_quantity = $item['products_quantity']; //product stock
            $products_stock_reorder_level = (int)STOCK_REORDER_LEVEL; //alert stock  fixfor all  products
            $products_quantity_alert = $item['products_quantity_alert']; // alert stock fix
            $manufacturer_name = HTML::sanitize($_POST['manufacturers_name']);
            $products_description = $item['products_description'];
            $products_description_summary = $item['products_description_summary'];
            $language_name = $this->lang->getLanguagesName($item['language_id']);
            $language_code = $this->lang->getLanguageCodeById((int)$item['language_id']);

            $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/seo_chat_gpt', $language_code);
            $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/rag', $language_code);

            $update_sql_data = [
              'language_id' => $item['language_id'],
              'products_id' => $item['products_id']
            ];

            $Qcategories = $this->app->db->get('categories_description', 'categories_name', ['categories_id' => $Qcategories->valueInt('categories_id'), 'language_id' => $item['language_id']]);
            $categories_name = $Qcategories->value('categories_name');

            $products_name_array = ['products_name' => $products_name];
            $question_summary_description = $this->app->getDef('text_seo_page_summary_description_question', $products_name_array);
            //-------------------
            // products description
            //-------------------
            if (isset($_POST['option_gpt_description'])) {
              $question_description = $this->app->getDef('text_question_description', $products_name_array);
              $technical_question = $this->app->getDef('text_technical_question', $products_name_array);

              $products_description = $translate_language . ' ' . $language_name . '. ' . $question_description . ' ' . $technical_question;
              $products_description = Gpt::getGptResponse($products_description);

              if ($products_description !== false) {
                $sql_data_array = ['products_description' => $products_description ?? ''];

                $this->app->db->save('products_description', $sql_data_array, $update_sql_data);
              }
            }
            //-------------------
            // Summary description
            //-------------------
            $summary_description = '';
            if (isset($_POST['option_gpt_summary_description'])) {
              $summary_description = $translate_language . ' ' . $language_name . ' : ' . $question_summary_description;
              $summary_description = Gpt::getGptResponse($summary_description);

              if ($summary_description !== false) {
                $sql_data_array = [
                  'products_description_summary' => SeoAdmin::normalizeSeoDescription($summary_description),
                ];

                $this->app->db->save('products_description', $sql_data_array, $update_sql_data);
              }
            }
            ////-------------------
            // Seo Title
            //-------------------
            $seo_product_title = '';
            if (isset($_POST['option_gpt_seo_title'])) {
              $question = $this->app->getDef('text_seo_page_title_question', $products_name_array);

              $seo_product_title = $translate_language . ' ' . $language_name . ' : ' . $question;
              $seo_product_title = Gpt::getGptResponse($seo_product_title);
              $seo_product_title = SeoAdmin::normalizeSeoTitle($seo_product_title);

              if ($seo_product_title !== false) {
                $sql_data_array = [
                  'products_head_desc_tag' => SeoAdmin::normalizeSeoDescription($seo_product_description),
                ];

                $this->app->db->save('products_description', $sql_data_array, $update_sql_data);
              }
            }

            //-------------------
            // Seo description
            //-------------------
            $seo_product_description = '';
            if (isset($_POST['option_gpt_seo_description'])) {
              $seo_product_description = $translate_language . ' ' . $language_name . ' : ' . $question_summary_description;
              $seo_product_description = Gpt::getGptResponse($seo_product_description);

              if ($seo_product_description !== false) {
                $sql_data_array = [
                  'products_head_desc_tag' => strip_tags($seo_product_description) ?? '',
                ];

                $this->app->db->save('products_description', $sql_data_array, $update_sql_data);
              }
            }
            //-------------------
            // Seo keywords
            //-------------------
            $seo_product_keywords = '';
            if (isset($_POST['option_gpt_seo_keywords'])) {
              $question_keywords = $this->app->getDef('text_seo_page_keywords_question', $products_name_array);

              $seo_product_keywords = $translate_language . ' ' . $language_name . ' : ' . $question_keywords;
              $seo_product_keywords = Gpt::getGptResponse($seo_product_keywords);

              if ($seo_product_keywords !== false) {
                $sql_data_array = [
                  'products_head_keywords_tag' => SeoAdmin::normalizeSeoKeywords($seo_product_keywords),
                ];

                $this->app->db->save('products_description', $sql_data_array, $update_sql_data);
              }
            }
            //-------------------
            // Seo tag
            //-------------------
            $seo_product_tag = '';
            if (isset($_POST['option_gpt_seo_tags'])) {
              $question_tag = $this->app->getDef('text_seo_page_tag_question', $products_name_array);

              $seo_product_tag = $translate_language . ' ' . $language_name . ' : ' . $question_tag;
              $seo_product_tag = Gpt::getGptResponse($seo_product_tag);

              if ($seo_product_tag !== false) {
                $sql_data_array = [
                  'products_head_tag' => SeoAdmin::normalizeSeoKeywords($seo_product_tag),
                ];

                $this->app->db->save('products_description', $sql_data_array, $update_sql_data);
              }
            }

            //********************
            // add embedding
            //********************

            if ($embedding_enabled) {
              $embedding_data = $this->app->getDef('text_product_name') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_name) . "\n";
              $embedding_data .= $this->app->getDef('text_product_id') . ': ' . $products_id . "\n";

              if (!empty($products_model)) {
                $embedding_data .= $this->app->getDef('text_product_model') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_model) . "\n";
              }

              if (!empty($categories_name)) {
                $embedding_data .= $this->app->getDef('text_categories_name') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($categories_name) . "\n";
              }

              if (!empty($manufacturer_name)) {
                $embedding_data .= $this->app->getDef('text_product_brand_name') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($manufacturer_name) . "\n";
              }

              if (!empty($products_ean)) {
                $embedding_data .= $this->app->getDef('text_product_ean') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_ean) . "\n";
              }

              if (!empty($products_sku)) {
                $embedding_data .= $this->app->getDef('text_product_sku') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_sku) . "\n";
              }

              if (!empty($products_date_added)) {
                $embedding_data .= $this->app->getDef('text_product_date_added') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_date_added) . "\n";
              }

              if (!empty($products_status)) {
                if ($products_status === 1) {
                  $products_status = $this->app->getDef('text_product_enable');
                } else {
                  $products_status = $this->app->getDef('text_product_disable');
                }

                $embedding_data .= $this->app->getDef('text_product_status') . ': ' . $products_status . "\n";
              }

              if (!empty($products_ordered)) {
                $embedding_data .= $this->app->getDef('text_product_ordered') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_ordered) . "\n";
              }

              if (!empty($products_stock_reorder_level)) {
                $embedding_data .= $this->app->getDef('text_product_stock_reorder') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_stock_reorder_level) . "\n";
              }

              if (!empty($products_quantity)) {
                $embedding_data .= $this->app->getDef('text_product_stock') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_quantity) . "\n";
              }

              if (!empty($products_quantity_alert)) {
                $embedding_data .= $this->app->getDef('text_product_stock_alert') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_quantity_alert) . "\n";
              }

              if (!empty($products_description)) {
                $embedding_data .= $this->app->getDef('text_product_description') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_description) . "\n";
              }

              if (!empty($products_description_summary)) {
                $embedding_data .= $this->app->getDef('text_product_description_summary') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_description_summary) . "\n";
              }

              if (!empty($seo_product_title)) {
                $embedding_data .= $this->app->getDef('text_product_seo_title') . ': ' . HTMLOverrideCommon::cleanHtmlForSEO($seo_product_title) . "\n";
              }

              if (!empty($seo_product_description)) {
                $embedding_data .= $this->app->getDef('text_product_seo_description') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($seo_product_description) . "\n";
              }

              if (!empty($seo_product_keywords)) {
                $embedding_data .= $this->app->getDef('text_product_seo_keywords') . ': ' . HTMLOverrideCommon::cleanHtmlForSEO($seo_product_keywords) . "\n";
              }

              if (!empty($seo_product_tag)) {
                $embedding_data .= $this->app->getDef('text_product_seo_tag') . ': ' . HTMLOverrideCommon::cleanHtmlForSEO($seo_product_tag) . "\n";
              }

              if (!empty($products_description)) {
                $embedding_data .= $this->app->getDef('text_product_description') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_description) . "\n";
                $taxonomy = $this->semantics->createTaxonomy(HTMLOverrideCommon::cleanHtmlForEmbedding($products_description), $language_code, null);

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

                $embedding_data .= "\n" . $this->app->getDef('text_product_taxonomy') . " :\n";

                foreach ($tags as $key => $value) {
                  $embedding_data .= "[$key]: $value\n";
                }
              }
            }

            // Generate embeddings
            $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

            if (!empty($embeddedDocuments)) {
              // Prepare base metadata
              $baseMetadata = [
                'product_name' => $products_name,
                'content' => $products_description,
                'type' => 'products',
                'product_id' => (int)$item['products_id'],
                'tags' => $taxonomy ? array_filter(array_map(fn($t) => trim(strip_tags($t)), explode("\n", $taxonomy))) : [],
                'source' => [
                  'type' => 'manual',
                  'name' => 'manual'
                ]
              ];

              // Save all chunks using centralized method
              $result = NewVector::saveEmbeddingsWithChunks(
                $embeddedDocuments,
                'products_embedding',
                (int)$item['products_id'],
                (int)$item['language_id'],
                $baseMetadata,
                $this->app->db,
                false  // isUpdate = false for insert
              );

              if (!$result['success']) {
                error_log("Products/Insert: Failed to save embeddings for product {$item['products_id']} - " . $result['error']);
              } else {
                error_log("Products/Insert: Successfully saved {$result['chunks_saved']} chunk(s) for product {$item['products_id']}");
              }
            }

            //********************
            // Add FAQ embeddings
            //********************
            if ($embedding_enabled) {
              // Check if FAQ exists for this product and language
              $QfaqCheck = $this->app->db->prepare('SELECT faq_content 
                                                     FROM :table_products_description_faq 
                                                     WHERE products_id = :products_id 
                                                     AND language_id = :language_id');
              $QfaqCheck->bindInt(':products_id', $products_id);
              $QfaqCheck->bindInt(':language_id', $item['language_id']);
              $QfaqCheck->execute();

              if ($QfaqCheck->fetch() && !empty($QfaqCheck->value('faq_content'))) {
                try {
                  // Use FaqEmbeddingGenerator to generate FAQ embeddings
                  $faqGenerator = new \ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ\FaqEmbeddingGenerator();
                  $faqResult = $faqGenerator->generateEmbeddings($products_id, $item['language_id']);

                  if ($faqResult['success']) {
                    if($this->debug) {
                      error_log("Products/Insert: Successfully generated {$faqResult['chunks_saved']} FAQ embedding chunk(s) for product {$products_id}, language {$item['language_id']}");
                    }
                  } else {
                    error_log("Products/Insert: Failed to generate FAQ embeddings for product {$products_id}, language {$item['language_id']}: {$faqResult['error']}");
                  }
                } catch (\Exception $e) {
                  error_log("Products/Insert: Exception generating FAQ embeddings for product {$products_id}, language {$item['language_id']}: " . $e->getMessage());
                  // Do not block product insertion if FAQ embedding generation fails
                }
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
                $Qproducts = $this->app->db->prepare('select products_name,
                                                               language_id
                                                        from :table_products_description
                                                        where products_id = :products_id
                                                        and language_id = 1
                                                      ');
                $Qproducts->bindInt(':products_id', $Qcheck->valueInt('products_id'));
                $Qproducts->execute();

                $update_sql_data = [
                  'products_id' => $Qcheck->valueInt('products_id')
                ];

                $products_image = Gpt::createImageChatGpt($Qproducts->value('products_name'), 'products', '256x256', true, true);

                if (!empty($products_image) || $products_image !== false) {
                  $sql_data_products_image = [
                    'products_image' => $products_image ?? '',
                    'products_image_small' => $products_image ?? ''
                  ];

                  $this->app->db->save('products', $sql_data_products_image, $update_sql_data);
                }

               //zoom
                $products_image_zoom = Gpt::createImageChatGpt($Qproducts->value('products_name'), 'products', '512x512', true);

                if (!empty($products_image_zoom) || $products_image_zoom !== false) {
                  $sql_data_array_products_image_zoom = [
                    'products_image_zoom' => $products_image_zoom ?? '',
                  ];

                  $this->app->db->save('products', $sql_data_array_products_image_zoom, $update_sql_data);

                  $sql_array = [
                    'products_id' => $Qcheck->valueInt('products_id'),
                    'image' => $products_image_zoom ?? '',
                    'htmlcontent' => '',
                    'sort_order' => 2
                  ];

                  $this->app->db->save('products_images', $sql_array);
                }

                // medium
                $products_image_medium = Gpt::createImageChatGpt($Qproducts->value('products_name'), 'products', '512x512', true);

                if (!empty($products_image_medium) || $products_image_medium !== false) {
                  $sql_data_array_products_image_medium = [
                    'products_image_medium' => $products_image_medium ?? '',
                  ];

                  $this->app->db->save('products', $sql_data_array_products_image_medium, $update_sql_data);

                  $sql_array = [
                    'products_id' => $Qcheck->valueInt('products_id'),
                    'image' => $products_image_medium ?? '',
                    'htmlcontent' => '',
                    'sort_order' => 2
                  ];

                  $this->app->db->save('products_images', $sql_array);
                }
              }
      */
    }
  }
}
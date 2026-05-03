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
 * Class Update
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\ClicShoppingAdmin\Products
 *
 * This class handles the insertion of product data into the database.
 * It generates SEO metadata, summaries, and translations based on product information,
 * and also creates product-related images if specified.
 */
class Update implements \ClicShopping\OM\Modules\HooksInterface
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
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/rag');
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
    
    if (isset($_GET['Update'], $_GET['Products'])) {
      if (isset($_GET['pID'])) {
        $pID = HTML::sanitize($_GET['pID']);

        $Qcheck = $this->app->db->prepare('select id
                                           from :table_products_embedding
                                           where entity_id = :entity_id
                                          ');
        $Qcheck->bindInt(':entity_id', $pID);
        $Qcheck->execute();

        $insert_embedding = false;

        if ($Qcheck->fetch() === false) {
          $insert_embedding = true;
        }

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
        $Qproducts->bindInt(':products_id', $pID);
        $Qproducts->execute();

        $products_array = $Qproducts->fetchAll();

        $Qcategories = $this->app->db->get('products_to_categories', ['categories_id'], ['products_id' => $pID]);

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
            $seo_product_title = $item['products_head_title_tag'];
            $seo_product_description = $item['products_head_desc_tag'];
            $seo_product_keywords = $item['products_head_keywords_tag'];
            $seo_product_tag = $item['products_head_tag'];
            $products_description_summary = $item['products_description_summary'];

            $language_code = $this->lang->getLanguageCodeById((int)$item['language_id']);

            $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/seo_chat_gpt', $language_code);
            $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/rag', $language_code);

            $Qcategories = $this->app->db->get('categories_description', 'categories_name', ['categories_id' => $Qcategories->valueInt('categories_id'), 'language_id' => $item['language_id']]);
            $categories_name = $Qcategories->value('categories_name');

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
                !$insert_embedding  // isUpdate = true if not inserting
              );

              if (!$result['success']) {
                error_log("Products/Update: Failed to save embeddings for product {$item['products_id']} - " . $result['error']);
              } else {
                error_log("Products/Update: Successfully saved {$result['chunks_saved']} chunk(s) for product {$item['products_id']}");
              }
            }

            //********************
            // Regenerate FAQ embeddings
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
                  // Use FaqEmbeddingGenerator to regenerate FAQ embeddings
                  $faqGenerator = new \ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ\FaqEmbeddingGenerator();
                  
                  // Delete existing FAQ embeddings for this product and language
                  $deleteResult = $faqGenerator->deleteEmbeddings($products_id, $item['language_id']);
                  
                  if ($deleteResult) {
                    error_log("Products/Update: Deleted existing FAQ embeddings for product {$products_id}, language {$item['language_id']}");
                  }
                  
                  // Regenerate FAQ embeddings
                  $faqResult = $faqGenerator->generateEmbeddings($products_id, $item['language_id']);

                  if ($faqResult['success']) {
                    if($this->debug) {
                    error_log("Products/Update: Successfully regenerated {$faqResult['chunks_saved']} FAQ embedding chunk(s) for product {$products_id}, language {$item['language_id']}");
                    }
                  } else {
                    error_log("Products/Update: Failed to regenerate FAQ embeddings for product {$products_id}, language {$item['language_id']}: {$faqResult['error']}");
                  }
                } catch (\Exception $e) {
                  error_log("Products/Update: Exception regenerating FAQ embeddings for product {$products_id}, language {$item['language_id']}: " . $e->getMessage());
                  // Do not block product update if FAQ embedding regeneration fails
                }
              }
            }
          }
        }
      }
    }
  }
}
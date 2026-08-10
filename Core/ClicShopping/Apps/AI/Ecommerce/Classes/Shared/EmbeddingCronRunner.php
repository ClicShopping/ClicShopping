<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shared;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron as Cronjob;
use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\ProductsAdmin;

/**
 * EmbeddingCronRunner — daily upsert of e-commerce entity embeddings: creates
 * the embedding when missing, replaces its chunks when it already exists.
 *
 * Reached through the unified Cronjob/Process dispatch (Shop/Cronjob/Process),
 * exactly like SeoCronRunner and ReviewSentimentCronRunner. Consolidated here
 * from the former ChatGpt app Cron class: categories, manufacturers, products
 * and reviews are e-commerce entities, so their embedding definitions and
 * generation belong to AI/Ecommerce, not to the ChatGpt app.
 *
 * Page manager and the schema embedding sync stay in the ChatGpt Cron class
 * (page manager is not an e-commerce entity).
 *
 * Self-gates on the shared 'embeddings' clic_cron code (cron_id 5, covers both
 * this runner and the ChatGpt page-manager/schema sync) exactly like the
 * ChatGpt Shop/ClicShoppingAdmin Cronjob\Process hooks already do for the same
 * code, plus the same master switch the former Cron::execute() applied.
 */
class EmbeddingCronRunner
{
  private const CRON_CODE = 'embeddings';

  private mixed $app;
  private mixed $lang;
  private mixed $semantics;

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
  }

  /**
   * Entry point invoked by the Shop / ClicShoppingAdmin cron hooks.
   *
   * @return bool|null false when opted-out / provider offline, null otherwise.
   */
  public function run(): bool|null
  {
    if (!Gpt::checkGptStatus()) {
      return false;
    }

    if (!defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') || CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'False') {
      return false;
    }

    $cron_id_embedding = Cronjob::getCronCode(self::CRON_CODE);

    if (isset($_GET['cronId'])) {
      $cron_id = HTML::sanitize($_GET['cronId']);

      if ($cron_id !== null && !empty($cron_id) && is_numeric($cron_id)) {
        $cron_id = (int)$cron_id;
        Cronjob::updateCron($cron_id);

        if ($cron_id_embedding == $cron_id) {
          $this->updateAllEmbeddings();
        }
      } else {
        error_log('Invalid cronId parameter detected: ' . (isset($_GET['cronId']) ? htmlspecialchars($_GET['cronId']) : 'empty'));
      }
    } else {
      Cronjob::updateCron($cron_id_embedding);

      if (isset($cron_id_embedding)) {
        $this->updateAllEmbeddings();
      }
    }

    return null;
  }

  /**
   * Generates the missing embeddings for every e-commerce entity, per language.
   *
   * @return void
   */
  private function updateAllEmbeddings(): void
  {
    $language_array = $this->lang->getLanguages();

    foreach ($language_array as $value) {
      $this->updateAllEmbeddingCategories($value['id']);
      $this->updateAllEmbeddingManufacturers($value['id']);
      $this->updateAllEmbeddingProducts($value['id']);
      $this->updateAllEmbeddingReviews($value['id']);
    }

    // Suppliers doesn't use language_id, so it's called once outside the loop
    $this->updateAllEmbeddingSuppliers();
  }

  /**
   * Updates the embedding categories for a specific language.
   *
   * @param int $language_id The ID of the language to update.
   * @return void
   */
  private function updateAllEmbeddingCategories(int $language_id): void
  {
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Categories/rag');

    $Qcheck = $this->app->db->prepare('select c.categories_id,
                                              cd.categories_name,
                                              cd.categories_description,
                                              cd.categories_head_title_tag,
                                              cd.categories_head_desc_tag,
                                              cd.categories_head_keywords_tag,
                                              cd.language_id
                                       from :table_categories c,
                                             :table_categories_description cd
                                       where c.categories_id = cd.categories_id
                                       and cd.language_id = :language_id
                                      ');
    $Qcheck->bindInt(':language_id', $language_id);
    $Qcheck->execute();

    $check_array = $Qcheck->fetchAll();

    foreach ($check_array as $item) {
      $language_code = $this->lang->getLanguageCodeById((int)$item['language_id']);
      $categories_name = $item['categories_name'];
      $categories_description = $item['categories_description'];
      $seo_categories_title = $item['categories_head_title_tag'];
      $seo_categories_description = $item['categories_head_desc_tag'];
      $seo_categories_keywords = $item['categories_head_keywords_tag'];

//********************
// add embedding
//********************
      $embedding_data = "\n" . $this->app->getDef('text_category_embedded') . "\n";
      $embedding_data .= $this->app->getDef('text_category_name') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($categories_name) . "\n";

     if (!empty($seo_categories_title)) {
       $embedding_data .= $this->app->getDef('text_category_seo_title', ['category_name' => $categories_name]) . ' : ' .  HTMLOverrideCommon::cleanHtmlForEmbedding($seo_categories_title) . "\n";;
     }

     if (!empty($seo_categories_description)) {
       $embedding_data .= $this->app->getDef('text_category_seo_description', ['category_name' => $categories_name]) . ' : ' .  HTMLOverrideCommon::cleanHtmlForEmbedding($seo_categories_description) . "\n";;
     }

     if (!empty($seo_categories_keywords)) {
       $embedding_data .= $this->app->getDef('text_category_seo_keywords', ['category_name' => $categories_name]) . ' : ' .  HTMLOverrideCommon::cleanHtmlForEmbedding($seo_categories_keywords) . "\n";;
     }

      if (!empty($categories_description)) {
        $embedding_data .= $this->app->getDef('text_category_description', ['category_name' => $categories_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($categories_description) . "\n";;
      }

      // Generate taxonomy separately (NOT added to embedding_data)
      $tags = [];
      if (!empty($categories_description)) {
        $taxonomy = $this->semantics->createTaxonomy($categories_description, $this->app->getDef('text_create_taxonomy', ['document_text' => $categories_description]), $language_code, 300);

        if (!empty($taxonomy)) {
          $lines = array_filter(array_map('trim', explode("\n", $taxonomy)));

          foreach ($lines as $line) {
            // Updated regex to handle optional spaces before colon: [key] : value or [key]: value
            if (preg_match('/^\[([^\]]+)\]\s*:\s*(.+)$/', $line, $matches)) {
              $tags[$matches[1]] = trim($matches[2]);
            }
          }
        }
      }

     $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

     // Prepare base metadata
     $baseMetadata = [
       'brand_name' => $categories_name,
       'content' => $embedding_data,
       'type' => 'category',
       'tags' => $tags,
       'source' => ['type' => 'manual', 'name' => 'manual']
     ];

     // Save all chunks using centralized method
     $result = NewVector::saveEmbeddingsWithChunks(
       $embeddedDocuments,
       'categories_embedding',
       (int)$item['categories_id'],
       (int)$item['language_id'],
       $baseMetadata,
       $this->app->db,
       true  // isUpdate = true (upsert: repair existing embeddings)
     );

     if (!$result['success']) {
       error_log("Cron Categories: Failed to save embeddings for category {$item['categories_id']} - " . $result['error']);
     } else {
       error_log("Cron Categories: Successfully saved {$result['chunks_saved']} chunks for category {$item['categories_id']}");
     }
    }
  }

  /**
   * Updates the embedding manufacturers for a specific language.
   *
   * @param int $language_id The ID of the language to update.
   * @return void
   */
  private function updateAllEmbeddingManufacturers(int $language_id): void
  {
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Manufacturer/rag');

    $Qcheck = $this->app->db->prepare('select m.manufacturers_id,
                                              m.manufacturers_name,
                                              m.suppliers_id,
                                              mi.manufacturer_description,
                                              mi.manufacturer_seo_title,
                                              mi.manufacturer_seo_description,
                                              mi.manufacturer_seo_keyword,
                                              mi.languages_id
                                       from :table_manufacturers m,
                                             :table_manufacturers_info mi
                                       where m.manufacturers_id = mi.manufacturers_id
                                       and mi.languages_id = :language_id
                                      ');
    $Qcheck->bindInt(':language_id', $language_id);
    $Qcheck->execute();

    $check_array = $Qcheck->fetchAll();

    foreach ($check_array as $item) {
      $language_code = $this->lang->getLanguageCodeById((int)$item['languages_id']);
      $manufacturers_name = $item['manufacturers_name'];
      $manufacturers_description = $item['manufacturer_description'];
      $seo_manufacturer_title = $item['manufacturer_seo_title'];
      $seo_manufacturer_description = $item['manufacturer_seo_description'];
      $seo_manufacturer_keywords = $item['manufacturer_seo_keyword'];
      $suppliers_id = $item['suppliers_id'];
//********************
// add embedding
//********************
      $embedding_data =  "\n" . $this->app->getDef('text_manufacturer_embedded') . "\n";

      $embedding_data .= $this->app->getDef('text_manufacturer_name') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($manufacturers_name) . "\n";

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

      if (!empty($manufacturers_description)) {
        $embedding_data .= $this->app->getDef('text_manufacturer_description') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($manufacturers_description) . "\n";

        $taxonomy = $this->semantics->createTaxonomy($manufacturers_description, $this->app->getDef('text_create_taxonomy', ['document_text' => $manufacturers_description]), $language_code, 300);
        if (!empty($taxonomy)) {
          $embedding_data .= $this->app->getDef('text_manufacturer_taxonomy') . ' : ' . "\n" . $taxonomy . "\n";
        }
      }

      $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

      // Prepare base metadata
      $baseMetadata = [
        'brand_name' => $manufacturers_name,
        'content' => $embedding_data,
        'type' => 'manufacturers',
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
        true  // isUpdate = true (upsert: repair existing embeddings)
      );

      if (!$result['success']) {
        error_log("Cron Manufacturers: Failed to save embeddings for manufacturer {$item['manufacturers_id']} - " . $result['error']);
      } else {
        error_log("Cron Manufacturers: Successfully saved {$result['chunks_saved']} chunks for manufacturer {$item['manufacturers_id']}");
      }
    }
  }

  /**
   * Updates the embedding products for a specific language.
   *
   * @param int $language_id The ID of the language to update.
   * @return void
   */
  private function updateAllEmbeddingProducts(int $language_id): void
  {
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/rag');

    $QcheckProducts = $this->app->db->prepare('select p.products_id,
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
                                                  where p.products_id = pd.products_id
                                                and pd.language_id = :language_id
                                              ');
    $QcheckProducts->bindInt(':language_id', $language_id);
    $QcheckProducts->execute();

    $check_array = $QcheckProducts->fetchAll();

    foreach ($check_array as $item) {
      $Qcategories = $this->app->db->get('products_to_categories', 'categories_id', ['products_id' => $item['products_id']]);

      $Qmanufacturers = $this->app->db->prepare('select manufacturers_name
                                                  from :table_manufacturers
                                                  where manufacturers_id = :manufacturers_id
                                                  ');

      $Qmanufacturers->bindInt(':manufacturers_id', $item['manufacturers_id']);
      $Qmanufacturers->execute();

      $manufacturer_name = $Qmanufacturers->value('manufacturers_name');

      $language_code = $this->lang->getLanguageCodeById((int)$item['language_id']);
      $products_name = $item['products_name'];
      $products_model = $item['products_model'];
      $products_ean = $item['products_ean'];
      $products_sku = $item['products_sku'];
      $products_date_added = $item['products_date_added'];
      $products_status = $item['products_status'];
      $products_ordered = $item['products_ordered'];
      $products_quantity = $item['products_quantity']; //product stock
      $products_stock_reorder_level = (int)STOCK_REORDER_LEVEL; // reorder level
      $products_quantity_alert = $item['products_quantity_alert']; // alert stock fix
      $products_description = $item['products_description'];
      $products_description_summary = $item['products_description_summary'];
      $seo_product_title = $item['products_head_title_tag'];
      $seo_product_description = $item['products_head_desc_tag'];
      $seo_product_keywords = $item['products_head_keywords_tag'];
      $seo_product_tag = $item['products_head_tag'];

      $Qcategories = $this->app->db->get('categories_description', 'categories_name', ['categories_id' => $Qcategories->valueInt('categories_id'), 'language_id' => $item['language_id']]);
      $categories_name = $Qcategories->value('categories_name');

//********************
// add embedding
//********************
      $embedding_data = $this->app->getDef('text_product_name') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_name) . "\n";

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
        $embedding_data .= $this->app->getDef('text_product_seo_title') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($seo_product_title) . "\n";
      }

      if (!empty($seo_product_description)) {
        $embedding_data .= $this->app->getDef('text_product_seo_description') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($seo_product_description) . "\n";
      }

      if (!empty($seo_product_keywords)) {
        $embedding_data .= $this->app->getDef('text_product_seo_keywords') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($seo_product_keywords) . "\n";
      }

      if (!empty($seo_product_tag)) {
        $embedding_data .= $this->app->getDef('text_product_seo_tag') . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($seo_product_tag) . "\n";
      }

      // The description itself is appended above; only its taxonomy is added here.
      if (!empty($products_description)) {
        $taxonomy = $this->semantics->createTaxonomy($products_description, $this->app->getDef('text_create_taxonomy', ['document_text' => $products_description]), $language_code, 300);
        if (!empty($taxonomy)) {
          $embedding_data .= $this->app->getDef('text_product_taxonomy') . ' : ' . "\n" . $taxonomy . "\n";
        }
      }

      $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

      // Prepare base metadata
      $baseMetadata = [
        'brand_name' => $manufacturer_name ?? '',
        'content' => $embedding_data,
        'type' => 'products',
        'source' => ['type' => 'manual', 'name' => 'manual']
      ];

      // Save all chunks using centralized method
      $result = NewVector::saveEmbeddingsWithChunks(
        $embeddedDocuments,
        'products_embedding',
        (int)$item['products_id'],
        (int)$item['language_id'],
        $baseMetadata,
        $this->app->db,
        true  // isUpdate = true (upsert: repair existing embeddings)
      );

      if (!$result['success']) {
        error_log("Cron Products: Failed to save embeddings for product {$item['products_id']} - " . $result['error']);
      } else {
        error_log("Cron Products: Successfully saved {$result['chunks_saved']} chunks for product {$item['products_id']}");
      }
    }
  }

  /**
   * Updates the embedding reviews for a specific language.
   *
   * @param int $language_id The ID of the language to update.
   * @return void
   */
  private function updateAllEmbeddingReviews(int $language_id): void
  {
    if (!Registry::exists('ProductsAdmin')) {
      Registry::set('ProductsAdmin', new ProductsAdmin());
    }

    $CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Reviews/rag');

    $Qcheck = $this->app->db->prepare('select r.reviews_id,
                                              r.products_id,
                                              r.reviews_rating,
                                              r.date_added,
                                              r.status,
                                              r.customers_tag,
                                              rd.reviews_text,
                                              rv.vote,
                                              rv.sentiment
                                        from :table_reviews r,
                                             :table_reviews_description rd,
                                             :table_reviews_vote rv
                                        where r.reviews_id = rd.reviews_id
                                        and r.reviews_id = rv.reviews_id
                                        and rd.languages_id = :language_id
                                      ');

    $Qcheck->bindInt(':language_id', $language_id);
    $Qcheck->execute();

    $check_array = $Qcheck->fetchAll();

    foreach ($check_array as $item) {
      $products_id = $item['products_id'];
      $reviews_text = $item['reviews_text'];
      $reviews_rating = $item['reviews_rating'];
      $date_added = $item['date_added'];
      $status = $item['status'];

      if ($status === 0) {
        $status = $this->app->getDef('text_status_active');
      } else {
        $status = $this->app->getDef('text_status_inactive');
      }

      $customers_tag = $item['customers_tag'];
      $vote = $item['vote'];
      $sentiment = $item['sentiment'];

      $products_name = $CLICSHOPPING_ProductsAdmin->getProductsName($products_id, $language_id);

      //********************
      // add embedding
      //********************
      $embedding_data = $this->app->getDef('text_reviews', ['products_name' => $products_name]) . "\n";

      if (!empty($products_id)) {
        $embedding_data .= $this->app->getDef('text_reviews_product_name', ['products_name' => $products_name]) . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($products_name) . "\n";
      }

      if (!empty($reviews_text)) {
        $embedding_data .= $this->app->getDef('text_reviews_description', ['products_name' => $products_name]) . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($reviews_text) . "\n";
      }

      if (!empty($reviews_rating)) {
        $embedding_data .= $this->app->getDef('text_reviews_rating', ['products_name' => $products_name]) . ': ' . (float)$reviews_rating . "\n";
      }

      if (!empty($date_added)) {
        $embedding_data .= $this->app->getDef('text_reviews_date_added', ['products_name' => $products_name]) . ': ' . HTMLOverrideCommon::cleanHtmlForEmbedding($date_added) . "\n";
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

      $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

      // Prepare base metadata
      $baseMetadata = [
        'brand_name' => $products_name,
        'content' => $embedding_data,
        'type' => 'reviews',
        'source' => ['type' => 'manual', 'name' => 'manual']
      ];

      // Save all chunks using centralized method
      $result = NewVector::saveEmbeddingsWithChunks(
        $embeddedDocuments,
        'reviews_embedding',
        (int)$item['reviews_id'],
        (int)$language_id,
        $baseMetadata,
        $this->app->db,
        true  // isUpdate = true (upsert: repair existing embeddings)
      );

      if (!$result['success']) {
        error_log("Cron Reviews: Failed to save embeddings for review {$item['reviews_id']} - " . $result['error']);
      } else {
        error_log("Cron Reviews: Successfully saved {$result['chunks_saved']} chunks for review {$item['reviews_id']}");
      }
    }
  }

  /**
   * Updates the embedding suppliers .
   *
   * @return void
   * @throws \JsonException
   */
  private function updateAllEmbeddingSuppliers(): void
  {
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Supplier/rag');

    $Qcheck = $this->app->db->prepare('select suppliers_id,
                                              suppliers_name,
                                              date_added,
                                              suppliers_city,
                                              suppliers_country_id,
                                              suppliers_status,
                                              suppliers_image,
                                              date_added,
                                              last_modified,
                                              suppliers_manager,
                                              suppliers_phone,
                                              suppliers_email_address,
                                              suppliers_fax,
                                              suppliers_address,
                                              suppliers_suburb,
                                              suppliers_postcode,
                                              suppliers_city,
                                              suppliers_states,
                                              suppliers_country_id,
                                              suppliers_notes,
                                              suppliers_status
                                        from :table_suppliers
                                      ');

    $Qcheck->execute();

    $check_array = $Qcheck->fetchAll();

    foreach ($check_array as $item) {
      $supplier_name = $item['suppliers_name'];
      $date_added = $item['date_added'];
      $suppliers_country_id = $item['suppliers_country_id'];
      $suppliers_status = $item['suppliers_status'];

      if ($suppliers_status == 0) {
        $suppliers_status = $this->app->getDef('text_status_active');
      } else {
        $suppliers_status = $this->app->getDef('text_status_inactive');
      }

      $suppliers_city = $item['suppliers_city'];
      $suppliers_notes = $item['suppliers_notes'];
      $suppliers_states = $item['suppliers_states'];

      //********************
      // add embedding
      //********************
      $embedding_data = $this->app->getDef('text_supplier_name') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($supplier_name) . "\n";

      if (!empty($date_added)) {
        $embedding_data .= $this->app->getDef('text_supplier_date_added', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($date_added) . "\n";
      }

      if (!empty($suppliers_status)) {
        $embedding_data .= $this->app->getDef('text_supplier_status', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($suppliers_status) . "\n";
      }

      if (!empty($suppliers_states)) {
        $embedding_data .= $this->app->getDef('text_suppliers_states', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($suppliers_states) . "\n";
      }

      if (!empty($suppliers_city)) {
        $embedding_data .= $this->app->getDef('text_supplier_city', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($suppliers_city) . "\n";
      }

      if (!empty($suppliers_country_id)) {
        $embedding_data .= $this->app->getDef('text_supplier_country_id', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($suppliers_country_id) . "\n";
      }

      if (!empty($suppliers_notes)) {
        $embedding_data .= $this->app->getDef('text_suppliers_notes', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($suppliers_notes) . "\n";
      }

      $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

      // Prepare base metadata (suppliers table doesn't have language_id)
      $baseMetadata = [
        'brand_name' => $supplier_name,
        'content' => $embedding_data,
        'type' => 'suppliers',
        'source' => ['type' => 'manual', 'name' => 'manual']
      ];

      // Save all chunks using centralized method
      // Note: Suppliers table doesn't have language_id, so we pass null
      $result = NewVector::saveEmbeddingsWithChunks(
        $embeddedDocuments,
        'suppliers_embedding',
        (int)$item['suppliers_id'],
        null,  // language_id - suppliers table doesn't have this column
        $baseMetadata,
        $this->app->db,
        true  // isUpdate = true (upsert: repair existing embeddings)
      );

      if (!$result['success']) {
        error_log("Cron Suppliers: Failed to save embeddings for supplier {$item['suppliers_id']} - " . $result['error']);
      } else {
        error_log("Cron Suppliers: Successfully saved {$result['chunks_saved']} chunks for supplier {$item['suppliers_id']}");
      }
    }
  }
}

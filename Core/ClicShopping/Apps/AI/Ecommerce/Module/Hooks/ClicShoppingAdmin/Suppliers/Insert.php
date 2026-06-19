<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Suppliers;

use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

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
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Supplier/rag');
  }

  /**
   * Processes the execution related to Suppliers_id data management and updates in the database.
   * This includes generating SEO metadata (e.g., titles, descriptions ...),
   * summaries, and translations based on supplier information, as well as optional
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

    if (isset($_GET['Insert'], $_GET['Suppliers'])) {
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
                                          order by suppliers_id desc
                                          limit 1
                                        ');
      $Qcheck->execute();

      $supplier_name = $Qcheck->value('suppliers_name');
      $suppliers_id = $Qcheck->valueInt('suppliers_id');
      $date_added = $Qcheck->value('date_added');
      $suppliers_country_id = $Qcheck->valueInt('suppliers_country_id');
      $suppliers_status = $Qcheck->valueInt('suppliers_status');
      $suppliers_manager = $Qcheck->value('suppliers_manager');
      $suppliers_phone = $Qcheck->value('suppliers_phone');
      $suppliers_email_address = $Qcheck->value('suppliers_email_address');
      $suppliers_address = $Qcheck->value('suppliers_address');
      $suppliers_suburb = $Qcheck->value('suppliers_suburb');
      $suppliers_postcode = $Qcheck->value('suppliers_postcode');
      $suppliers_city = $Qcheck->value('suppliers_city');
      $suppliers_notes = $Qcheck->value('suppliers_notes');
      $suppliers_states = $Qcheck->value('suppliers_states');

      if ($suppliers_status == 0) {
        $suppliers_status = $this->app->getDef('text_status_active');
      } else {
        $suppliers_status = $this->app->getDef('text_status_inactive');
      }

      if ($suppliers_id !== null) {
        //********************
        // add embedding
        //********************
         if ($embedding_enabled) {
            $embedding_data = $this->app->getDef('text_supplier_name') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($supplier_name) . "\n";
            $embedding_data .= $this->app->getDef('text_supplier_id') . ' : ' . $suppliers_id . "\n";

            if (!empty($date_added)) {
              $embedding_data .= $this->app->getDef('text_supplier_date_added', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($date_added) . "\n";
            }

            if (!empty($suppliers_manager)) {
              $embedding_data .= $this->app->getDef('text_suppliers_manager', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($suppliers_manager) . "\n";
            }

            if (!empty($suppliers_phone)) {
              $embedding_data .= $this->app->getDef('text_suppliers_phone', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($suppliers_phone) . "\n";
            }

            if (!empty($suppliers_email_address)) {
              $embedding_data .= $this->app->getDef('text_suppliers_email_address', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($suppliers_email_address) . "\n";
            }

            if (!empty($suppliers_address)) {
              $embedding_data .= $this->app->getDef('text_suppliers_address', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($suppliers_address) . "\n";
            }

            if (!empty($suppliers_suburb)) {
              $embedding_data .= $this->app->getDef('text_supplier_suburb', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($suppliers_suburb) . "\n";
            }

            if (!empty($suppliers_postcode)) {
              $embedding_data .= $this->app->getDef('text_suppliers_postcode', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($suppliers_postcode) . "\n";
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

            $embedding_data .= $this->app->getDef('text_supplier_status', ['supplier_name' => $supplier_name]) . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($suppliers_status) . "\n";

            // Get default language code for taxonomy (suppliers don't have language_id)
            $default_language_code = $this->lang->getCode() ?? 'en';
            $taxonomy = $this->semantics->createTaxonomy(HtmlOverrideCommon::cleanHtmlForEmbedding($embedding_data), $default_language_code, null);

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

            $embedding_data .= "\n" . $this->app->getDef('text_supplier_taxonomy') . " :\n";

            foreach ($tags as $key => $value) {
              $embedding_data .= "[$key]: $value\n";
            }
	    
	    
            // Generate embeddings
            $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

            // Prepare base metadata (suppliers table doesn't have language_id)
            $baseMetadata = [
              'supplier_name' => HTMLOverrideCommon::cleanHtmlForEmbedding($supplier_name),
              'content' => HTMLOverrideCommon::cleanHtmlForEmbedding($supplier_name),
              'supplier_id' => (int)$suppliers_id,
              'type' => 'suppliers',
              'tags' => isset($tags) ? $tags : [],
              'source' => ['type' => 'manual', 'name' => 'manual']
            ];

            // Save all chunks using centralized method (pass null for language_id as suppliers table doesn't have this column)
            $result = NewVector::saveEmbeddingsWithChunks(
              $embeddedDocuments,
              'suppliers_embedding',
              (int)$suppliers_id,
              null,  // language_id - suppliers table doesn't have this column
              $baseMetadata,
              $this->app->db,
              false  // isUpdate = false for insert
            );

            if (!$result['success']) {
              error_log("Suppliers Insert: Failed to save embeddings - " . $result['error']);
            } else {
              error_log("Suppliers Insert: Successfully saved {$result['chunks_saved']} chunks for supplier {$suppliers_id}");
            }
         }
      }
    }
  }
}
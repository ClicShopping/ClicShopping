<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

class DeleteAll implements HooksInterface
{
  public mixed $app;
  private bool $debug;
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
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/seo_chat_gpt');
  }

  /**
   * Processes the execution related to product data management and delete in the database.
   * This includes deleting products_embedding and FAQ data (products_description_faq and 
   * products_description_faq_embedding) based on product information.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_POST['selected']) && is_array($_POST['selected']) && isset($_POST['DeleteAll'])) {
      foreach ($_POST['selected'] as $items) {
        if (isset($items)) {
          // Delete product embeddings
          $this->app->delete('products_embedding', 'entity_id', $items);

          try {
            $deletedFaq = $this->app->db->delete('products_description_faq', ['products_id' => (int)$items]);
            $deletedFaqEmbeddings = $this->app->db->delete('products_description_faq_embedding', ['entity_id' => (int)$items]);
            $seo_original_snapshot = $this->app->db->delete('seo_original_snapshot', ['entity_id' => (int)$items]);
            
            if ($deletedFaq || $deletedFaqEmbeddings || $seo_original_snapshot) {
              if($this->debug) {
                error_log("Products/DeleteAll: Deleted FAQ / embeddings / SEO Original for product {$items}");
              }
            }
          } catch (\Exception $e) {
            error_log("Products/DeleteAll: Error deleting FAQ for product {$items}: " . $e->getMessage());
            // Do not block product deletion if FAQ deletion fails
          }
        }
      }
    }
  }
}
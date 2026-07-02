<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

/**
 * Class DeleteConfirm
 * Handles the deletion confirmation of products and their associated embeddings
 */
class DeleteConfirm implements HooksInterface
{
  /** @var mixed Reference to the Products application */
  public mixed $app;

  /** @var int Product ID to be deleted */
  protected $Id;

  /** @var int Product categories ID */
  protected $productCategoriesId;

  /**
   * Constructor
   * Initializes the Products app and gets the product ID from POST data
   */
  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');

    $this->Id = HTML::sanitize($_POST['products_id']);
  }

  /**
   * Execute the deletion of product embeddings, FAQ, and FAQ embeddings
   * Triggered when DeleteConfirm action is requested
   */
  public function execute()
  {
    if (isset($_GET['DeleteConfirm']) && isset($this->Id)) {
      // Delete the product embedding from the database
      $this->app->db->delete('products_embedding', ['entity_id' => (int)$this->Id]);
      
      // Delete FAQ and FAQ embeddings for all languages
      try {
        // Delete FAQ content
        $deletedFaq = $this->app->db->delete('products_description_faq', ['products_id' => (int)$this->Id]);
        
        // Delete FAQ embeddings
        $deletedFaqEmbeddings = $this->app->db->delete('products_description_faq_embedding', ['entity_id' => (int)$this->Id]);
        $seo_original_snapshot = $this->app->db->delete('seo_original_snapshot', ['entity_id' => (int)$this->Id]);
        $seo_seo_quality_benchmark_log = $this->app->db->delete('clic_seo_quality_benchmark_log', ['entity_id' => (int)$this->Id]);

        if ($deletedFaq || $deletedFaqEmbeddings || $seo_original_snapshot || $seo_seo_quality_benchmark_log) {
          error_log("Products/DeleteConfirm: Deleted FAQ and embeddings for product {$this->Id}");
        }
      } catch (\Exception $e) {
        error_log("Products/DeleteConfirm: Error deleting FAQ for product {$this->Id}: " . $e->getMessage());
        // Do not block product deletion if FAQ deletion fails
      }
    } // end if
  }
}

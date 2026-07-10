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
      $pID = (int)$this->Id;

      // Generic RAG embedding.
      $this->app->db->delete('products_embedding', ['entity_id' => $pID]);

      try {
        // FAQ (product-only).
        $this->app->db->delete('products_description_faq', ['products_id' => $pID]);
        $this->app->db->delete('products_description_faq_embedding', ['entity_id' => $pID]);

        // SEO embedding/report history + multilingual SEO workflow rows.
        $this->app->db->delete('products_seo_embedding', ['entity_id' => $pID]);
        $this->app->db->delete('seo_original_snapshot', ['entity_type' => 'product', 'entity_id' => $pID]);
        $this->app->db->delete('seo_serp_reports', ['entity_type' => 'product', 'entity_id' => $pID]);
        $this->app->db->delete('seo_product_action_log', ['entity_type' => 'product', 'entity_id' => $pID]);
        $this->app->db->delete('seo_quality_benchmark_log', ['entity_type' => 'product', 'entity_id' => $pID]);
      } catch (\Exception $e) {
        error_log("Products/DeleteConfirm: SEO cleanup failed for product {$pID}: " . $e->getMessage());
        // Do not block product deletion if cleanup fails.
      }
    } // end if
  }
}

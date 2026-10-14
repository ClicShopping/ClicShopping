<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Categories;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

class DeleteConfirm implements HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
  }
  /**
   * Executes the necessary processes based on the provided GET and POST parameters related to category handling.
   *
   * When a category is deleted it removes every AI/SEO artefact tied to that
   * category: the generic RAG embedding, the SEO embedding/report history, and
   * the multilingual SEO workflow rows (original snapshots, SERP reports, the
   * accept/reject/revert action log and the quality-benchmark log).
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['DeleteConfirm'], $_GET['Categories'], $_GET['categories_id'])) {
      $cID = (int)HTML::sanitize($_GET['categories_id']);

      // Generic RAG embedding.
      $this->app->db->delete('categories_embedding', ['entity_id' => $cID]);

      try {
        $this->app->db->delete('categories_seo_embedding', ['entity_id' => $cID]);
        $this->app->db->delete('seo_original_snapshot', ['entity_type' => 'category', 'entity_id' => $cID]);
        $this->app->db->delete('seo_serp_reports', ['entity_type' => 'category', 'entity_id' => $cID]);
        $this->app->db->delete('seo_product_action_log', ['entity_type' => 'category', 'entity_id' => $cID]);
        $this->app->db->delete('seo_quality_benchmark_log', ['entity_type' => 'category', 'entity_id' => $cID]);
      } catch (\Exception $e) {
        error_log("Categories/DeleteConfirm: SEO cleanup failed for category {$cID}: " . $e->getMessage());
      }
    }
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;

class DeleteConfirm implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Products application.
   *
   * This method checks if the 'Eccomerce' instance exists in the Registry.
   * If it does not exist, it initializes and sets a new instance of EcommerceApp.
   * The 'Eccomerce' instance is then retrieved and assigned to the $app property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
  }

  /**
   * Deletes a  description based on the provided language ID.
   *
   * @param int|null $id The ID of the language to be deleted. If null, no action is taken.
   * @return void
   */
  private function delete(int $id)
  {
    if (!\is_null($id)) {
      $this->app->db->delete('products_description_faq', ['language_id' => $id]);
      $this->app->db->delete('products_description_faq_embedding', ['language_id' => $id]);
      $this->app->db->delete('seo_serp_reports', ['language_id' => $id]);
    }
  }

  /**
   * Executes the method logic, including checking if the application is enabled and handling deletion confirmation.
   *
   * @return bool Returns false if the application status is disabled; otherwise, no return value is expected.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS') || CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['DeleteConfirm'])) {
      $id = HTML::sanitize($_GET['lID']);
      $this->delete($id);
    }
  }
}
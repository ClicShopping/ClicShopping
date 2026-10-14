<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\Apps\Catalog\ProductsAttributes\ProductsAttributes as ProductsAttributesApp;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

/**
 * Cleans up products_options and products_options_values rows tied to a
 * language that has just been removed by the admin. Fires after the host
 * action has deleted the languages row — $_GET['lID'] is still present.
 */
class DeleteConfirm implements HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('ProductsAttributes')) {
      Registry::set('ProductsAttributes', new ProductsAttributesApp());
    }

    $this->app = Registry::get('ProductsAttributes');
  }

  /**
   * @return bool false when the app is disabled or lID is missing.
   */
  public function execute(): bool
  {
    if (!\defined('CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS') || CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS === 'False') {
      return false;
    }

    if (!isset($_GET['lID']) || !is_numeric($_GET['lID'])) {
      return false;
    }

    $lID = (int)HTML::sanitize($_GET['lID']);

    $this->app->db->delete('products_options', ['language_id' => $lID]);
    $this->app->db->delete('products_options_values', ['language_id' => $lID]);

    return true;
  }
}

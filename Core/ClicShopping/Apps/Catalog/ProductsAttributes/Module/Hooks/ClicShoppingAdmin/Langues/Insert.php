<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\Apps\Catalog\ProductsAttributes\ProductsAttributes as ProductsAttributesApp;
use ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin\LanguageAdmin;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

/**
 * Replicates products_options and products_options_values rows from the
 * current language to the newly created language so the catalog stays
 * consistent across all admin locales. Fires after the host action has
 * inserted the new languages row — LanguageAdmin::getLatestLanguageID()
 * returns the freshly created id.
 */
class Insert implements HooksInterface
{
  public mixed $app;
  private mixed $lang;

  public function __construct()
  {
    if (!Registry::exists('ProductsAttributes')) {
      Registry::set('ProductsAttributes', new ProductsAttributesApp());
    }

    $this->app = Registry::get('ProductsAttributes');
    $this->lang = Registry::get('Language');
  }

  /**
   * @return bool false when the app is disabled.
   */
  public function execute(): bool
  {
    if (!\defined('CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS') || CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS === 'False') {
      return false;
    }

    if (!isset($_GET['Langues'], $_GET['Insert'])) {
      return false;
    }

    $insert_language_id = (int)LanguageAdmin::getLatestLanguageID();
    $source_language_id = (int)$this->lang->getId();

    // Duplicate every products_options row of the source language into the new one
    $Qoptions = $this->app->db->get('products_options', '*', ['language_id' => $source_language_id]);

    while ($Qoptions->fetch()) {
      $cols = $Qoptions->toArray();
      $cols['language_id'] = $insert_language_id;
      $this->app->db->save('products_options', $cols);
    }

    // Same for products_options_values
    $Qvalues = $this->app->db->get('products_options_values', '*', ['language_id' => $source_language_id]);

    while ($Qvalues->fetch()) {
      $cols = $Qvalues->toArray();
      $cols['language_id'] = $insert_language_id;
      $this->app->db->save('products_options_values', $cols);
    }

    return true;
  }
}

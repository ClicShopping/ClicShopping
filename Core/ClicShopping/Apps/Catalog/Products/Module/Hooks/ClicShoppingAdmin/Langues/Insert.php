<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Module\Hooks\ClicShoppingAdmin\Langues;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Products\Products as ProductsApp;
use ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin\LanguageAdmin;

class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  private mixed $lang;
  protected $insert_language_id;

  /**
   * Initializes the Products application and sets the language registry.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Products')) {
      Registry::set('Products', new ProductsApp());
    }

    $this->app = Registry::get('Products');
    $this->lang = Registry::get('Language');
  }

  /**
   * Inserts product descriptions for a new language into the database.
   * Retrieves product data for the current language, modifies it for the new language,
   * and saves the modified data into the database.
   *
   * @return void
   */
  private function insert()
  {
    $insert_language_id = LanguageAdmin::getLatestLanguageID();

    $Qproducts = $this->app->db->prepare('select p.products_id as orig_product_id,
                                                   pd.*
                                            from :table_products p left join :table_products_description pd on p.products_id = pd.products_id
                                            where pd.language_id = :language_id
                                            ');

    $Qproducts->bindInt(':language_id', $this->lang->getId());
    $Qproducts->execute();

    while ($Qproducts->fetch()) {
      $cols = $Qproducts->toArray();

      $cols['products_id'] = $cols['orig_product_id'];
      $cols['language_id'] = (int)$insert_language_id;
      $cols['products_viewed'] = 0;

      unset($cols['orig_product_id']);

      $this->app->db->save('products_description', $cols);
    }
  }

  /**
   * Executes the main logic for the method.
   *
   * Performs a check to ensure the application status is defined and active.
   * Calls the insert method if specific GET parameters are set.
   *
   * @return bool Returns false if the application status is not defined or inactive.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS') || CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Langues'], $_GET['Insert'])) {
      $this->insert();
    }
  }
}
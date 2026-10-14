<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Catalog\Manufacturers\Classes\ClicShoppingAdmin\ManufacturerAdmin;
use ClicShopping\Apps\Catalog\Manufacturers\Manufacturers as ManufacturersApp;

class Update implements HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Manufacturers')) {
      Registry::set('Manufacturers', new ManufacturersApp());
    }

    $this->app = Registry::get('Manufacturers');
  }

  /**
   * Executes the update of the manufacturer ID for a specified product.
   *
   * @return bool Returns false if the application status is not enabled; otherwise, performs the update operation.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS') || CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Update'], $_GET['Products'])) {
      $id = HTML::sanitize($_GET['pID']);

      $manufacturers_id = ManufacturerAdmin::getManufacturerId($_POST['manufacturers_name']);

      $sql_data_array = ['manufacturers_id' => (int)$manufacturers_id];

      $this->app->db->save('products', $sql_data_array, ['products_id' => (int)$id]);
    }
  }
}
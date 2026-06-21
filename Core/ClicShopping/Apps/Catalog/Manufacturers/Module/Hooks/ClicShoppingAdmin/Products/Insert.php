<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\Apps\Catalog\Manufacturers\Manufacturers as ManufacturersApp;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Manufacturers\Classes\ClicShoppingAdmin\ManufacturerAdmin;

class Insert implements HooksInterface
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
   * Executes the method functionality to handle product insertion based on the provided GET parameters.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Insert'], $_GET['Products'])) {
      $Qproducts = $this->app->db->prepare('select products_id
                                              from :table_products
                                              order by products_id desc
                                               limit 1
                                              ');
      $Qproducts->execute();

      $id = $Qproducts->valueInt('products_id');

      $manufacturers_id = ManufacturerAdmin::getManufacturerId($_POST['manufacturers_name']);

      $sql_data_array = ['manufacturers_id' => (int)$manufacturers_id];

      $this->app->db->save('products', $sql_data_array, ['products_id' => (int)$id]);
    }
  }
}
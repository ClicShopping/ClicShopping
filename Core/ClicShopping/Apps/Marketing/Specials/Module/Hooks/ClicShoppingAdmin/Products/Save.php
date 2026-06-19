<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Specials\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\Specials\Specials as SpecialsApp;

class Save implements HooksInterface
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Specials')) {
      Registry::set('Specials', new SpecialsApp());
    }

    $this->app = Registry::get('Specials');
  }

  /**
   * Saves special pricing for a product based on provided input.
   *
   * @param int $id The ID of the product for which the special pricing is to be saved.
   * @return void
   */
  private function saveProductsSpecials($id)
  {
    if (!empty($_POST['products_specials']) && !empty($_POST['percentage_products_specials'])) {
      if (isset($_POST['percentage_products_specials'])) {
        if (substr($_POST['percentage_products_specials'], -1) == '%') {
          $specials_price = str_replace('%', '', $_POST['percentage_products_specials']);
          $specials_price = ($_POST['products_price'] - (($specials_price / 100) * $_POST['products_price']));
        } else {
          $specials_price = $_POST['products_price'] - $_POST['percentage_products_specials'];
        }

        if (is_float($specials_price)) {
          $this->app->db->save('specials', ['products_id' => (int)$id,
              'specials_new_products_price' => (float)$specials_price,
              'specials_date_added' => 'now()',
              'status' => 1,
              'customers_group_id' => 0
            ]
          );
        } // end is_numeric
      } // $_POST['percentage_products_specials']
    } // $_POST['products_specials']
  }

  /**
   * Saves the data associated with the provided identifier.
   *
   * @param mixed $id The identifier to save data for.
   * @return void
   */
  private function save($id)
  {
    $this->saveProductsSpecials($id);
  }

  /**
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['pID'])) {
      $id = HTML::sanitize($_GET['pID']);
      $this->save($id);
    }
  }
}
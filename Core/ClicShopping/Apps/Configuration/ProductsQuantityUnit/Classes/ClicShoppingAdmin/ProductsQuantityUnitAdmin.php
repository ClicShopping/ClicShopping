<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsQuantityUnit\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

class ProductsQuantityUnitAdmin
{
  public mixed $app;

  /**
   * Constructor method.
   *
   * Initializes the instance by retrieving the 'ProductsQuantityUnit' application from the registry.
   *
   * @return void
   */
  public function __construct()
  {
    $this->app = Registry::get('ProductsQuantityUnit');
  }

  /**
   * Retrieves a dropdown array of product quantity units, including their IDs and titles.
   * It fetches data from the products quantity unit table and organizes it by product quantity unit ID.
   * The default option is set to none.
   *
   * @return array An array of product quantity units, each represented by an associative array with 'id' and 'text' keys.
   */

  public function productsQuantityUnitDropDown(): array
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $products_quantity_unit_array = array(array('id' => '',
      'text' => $this->app->getDef('text_none'))
    );

    $QproductsQuantityUnit = $this->app->db->prepare('select products_quantity_unit_id,
                                                               products_quantity_unit_title
                                                       from :table_products_quantity_unit
                                                       where language_id = :language_id
                                                       order by products_quantity_unit_id
                                                      ');
    $QproductsQuantityUnit->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $QproductsQuantityUnit->execute();

    while ($QproductsQuantityUnit->fetch() !== false) {
      $products_quantity_unit_array[] = ['id' => $QproductsQuantityUnit->valueInt('products_quantity_unit_id'),
        'text' => $QproductsQuantityUnit->value('products_quantity_unit_title')
      ];
    }

    return $products_quantity_unit_array;
  }

  /**
   * Retrieves the title of the product's quantity unit based on the product ID and language ID.
   *
   * @return string Returns the title of the product's quantity unit.
   */
  public function getProductsQuantityUnitTitle(): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $QproductQtyUnit = $this->app->db->prepare('select p.products_quantity_unit_id,
                                                        pqt.products_quantity_unit_title
                                                 from :table_products p,
                                                      :table_products_quantity_unit pqt
                                                 where p.products_id = :products_id
                                                 and pqt.language_id = :language_id
                                                 and pqt.products_quantity_unit_id = p.products_quantity_unit_id
                                                ');
    $QproductQtyUnit->bindInt(':products_id', $_GET['pID']);
    $QproductQtyUnit->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $QproductQtyUnit->execute();

    return $QproductQtyUnit->value('products_quantity_unit_title');
  }
}
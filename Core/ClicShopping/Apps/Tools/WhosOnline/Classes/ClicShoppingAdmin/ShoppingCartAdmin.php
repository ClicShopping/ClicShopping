<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\WhosOnline\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use ClicShopping\Sites\ClicShoppingAdmin\Tax;

use ClicShopping\Sites\ClicShoppingAdmin\ShopppingCartAdmin;
use function is_array;

class ShoppingCartAdmin
{
  protected $contents;
  protected $total;
  protected $weight;
  private mixed $db;

  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->contents = [];
    $this->total = 0;
  }

  /**
   * Adds a product to the cart. If the product is already in the cart, its quantity is updated.
   *
   * @param mixed $products_id The unique identifier for the product.
   * @param int|string $qty Optional. The quantity of the product to add. Defaults to 1 if not specified.
   * @param array|string $attributes Optional. The attributes of the product to add, if any.
   *
   * @return void
   */
  public function addCart($products_id, $qty = '', $attributes = '')
  {
    $products_id = $this->getUprid($products_id, $attributes);

    if ($this->inCart($products_id)) {
      $this->updateQuantity($products_id, $qty, $attributes);
    } else {
      if ($qty == '') $qty = 1; // if no quantity is supplied, then add '1' to the customers basket

      $this->contents[$products_id] = ['qty' => $qty];

      if (is_array($attributes)) {
        foreach ($attributes as $option => $value) {
          $this->contents[$products_id]['attributes'][$option] = $value;
        }
      }
    }
    $this->cleanup();
  }

  /**
   * Updates the quantity of a specific product in the contents.
   *
   * @param string $products_id The identifier of the product to update.
   * @param int|string $quantity The quantity to set for the specified product. Default is an empty string.
   * @param array|string $attributes Optional product attributes as an associative array or string.
   * @return bool Returns true if no quantity is provided, indicating no update was necessary.
   */
  public function updateQuantity($products_id, $quantity = '', $attributes = '')
  {

    if ($quantity == '') return true; // nothing needs to be updated if theres no quantity, so we return true..

    $this->contents[$products_id] = ['qty' => $quantity];

    if (is_array($attributes)) {
      foreach ($attributes as $option => $value) {
        $this->contents[$products_id]['attributes'][$option] = $value;
      }
    }
  }

  /**
   *
   * Cleans up the contents array by removing items with a quantity less than 1.
   *
   * @return void
   */
  public function cleanup()
  {
    foreach (array_keys($this->contents) as $key) {
      if ($this->contents[$key]['qty'] < 1) {
        unset($this->contents[$key]);
      }
    }
  }

  /**
   * Checks if a product is in the cart.
   *
   * @param int|string $products_id The ID of the product to check.
   * @return bool Returns true if the product is in the cart, false otherwise.
   */
  public function inCart($products_id)
  {
    if (isset($this->contents[$products_id])) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * Calculates the total price and weight for the current contents, including product prices,
   * special prices, taxes, and attribute prices, if applicable.
   *
   * @return void
   */
  public function calculate()
  {
    $this->total = 0;
    $this->weight = 0;
    if (!is_array($this->contents)) return 0;

    foreach (array_keys($this->contents) as $products_id) {
      $qty = $this->contents[$products_id]['qty'];

// products price
      $Qproduct = $this->db->get('products', [
        'products_id',
        'products_price',
        'products_tax_class_id',
        'products_weight'
      ], [
          'products_id' => (int)$this->getPrid($products_id)
        ]
      );

      if ($Qproduct->fetch() !== false) {

        $prid = $Qproduct->valueInt('products_id');
//          $products_tax = Tax::getTaxRate($Qproduct->valueInt('products_tax_class_id'));
        $products_tax = 0;
        $products_price = $Qproduct->value('products_price');
        $products_weight = $Qproduct->value('products_weight');

        $Qspecials = $this->db->get('specials', 'specials_new_products_price', ['products_id' => $prid, 'status' => '1']);

        if ($Qspecials->fetch() !== false) {
          $products_price = $Qspecials->value('specials_new_products_price');
        }

        $this->total += Tax::addTax($products_price, $products_tax) * $qty;
        $this->weight += ($qty * $products_weight);

// attributes price
        if (isset($this->contents[$products_id]['attributes'])) {
          foreach ($this->contents[$products_id]['attributes'] as $option => $value) {
            $Qattribute = $this->db->get('products_attributes', [
              'options_values_price',
              'price_prefix'
            ], [
                'products_id' => $prid,
                'options_id' => (int)$option,
                'options_values_id' => (int)$value
              ]
            );

            if ($Qattribute->value('price_prefix') == '+') {
              $this->total += $qty * Tax::addTax($Qattribute->value('options_values_price'), $products_tax);
            } else {
              $this->total -= $qty * Tax::addTax($Qattribute->value('options_values_price'), $products_tax);
            }
          }
        }
      }
    }
  }

  /**
   * Calculates and returns the total.
   *
   * @return mixed The calculated total value.
   */
  public function show_total()
  {
    $this->calculate();

    return $this->total;
  }


  /**
   * Generates a unique product identifier (uprid) by appending the parameter options and values to the base product ID.
   *
   * @param string $prid The base product identifier.
   * @param array $params An associative array of options and their corresponding values to be appended to the product identifier.
   * @return string The unique product identifier (uprid) with appended options and values.
   */
  public function getUprid($prid, $params)
  {
    $uprid = $prid;
    if ((is_array($params)) && (!strstr($prid, '{'))) {
      foreach ($params as $option => $value) {
        $uprid = $uprid . '{' . $option . '}' . $value;
      }
    }

    return $uprid;
  }

  /**
   *
   * @param string $uprid The input string to be processed.
   * @return string The substring extracted before the first '{' character in the input string.
   */
  public function getPrid($uprid)
  {
    $pieces = explode('{', $uprid);

    return $pieces[0];
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\Shop;

use ClicShopping\OM\HTML;
use function is_array;
/**
 * Retrieves the product ID from various superglobals ($_GET, $_POST) or null if not available.
 *
 * @return null|int The product ID or null if not found.
 */
class Prod
{
  protected string $sort_by = '';
  protected string $sort_by_direction = '';

  /**
   * Retrieves the ID from GET or POST request parameters. The method sanitizes the input to ensure safety
   * and checks various conditions to determine the appropriate ID value to return based on availability.
   *
   * @return mixed The sanitized ID value if one is found, or null if no valid ID is present.
   */
  public function getID()
  {
// products description
    if (isset($_GET['Id'])) {
      $id = empty($_GET['Id']) ? null : HTML::sanitize($_GET['Id']);
    } else {
      $id = empty($_GET['products_id']) ? null : HTML::sanitize($_GET['products_id']);
    }
// products listing
    if (empty($id) && !isset($_GET['Search']) && !isset($_GET['Q'])) {
      if (isset($_POST['Id']) && is_numeric($_POST['Id']) && !empty(HTML::sanitize($_POST['Id']))) {
        $id = empty($_POST['Id']) ? null : HTML::sanitize($_POST['Id']);
      } elseif (isset($_POST['products_id']) && is_numeric($_POST['products_id']) && !empty(HTML::sanitize($_POST['products_id']))) {
        $id = empty($_POST['products_id']) ? null : HTML::sanitize($_POST['products_id']);
      }
    } elseif (isset($_GET['Search'], $_GET['Q'])) {
      if (isset($_POST['Id']) && is_numeric($_POST['Id']) && !empty(HTML::sanitize($_POST['Id']))) {
        $id = HTML::sanitize($_POST['Id']);
      } elseif (isset($_POST['products_id']) && is_numeric($_POST['products_id']) && !empty(HTML::sanitize($_POST['products_id']))) {
        $id = HTML::sanitize($_POST['products_id']);
      }
    }

    return $id;
  }

  /**
   * Generates a product ID string by appending formatted attribute IDs to the provided string.
   *
   * @param string $string The base string to which attribute IDs will be appended.
   * @param mixed $params An array containing numeric keys and values representing attribute IDs.
   *                      If the array is not valid, the string remains unchanged.
   * @return string The modified string with attribute IDs appended in the specified format,
   *                or the original string if parameters are invalid.
   */

  public static function getProductIDString(string $string, $params): string
  {
    if (is_array($params) && !empty($params)) {
      $attributes_check = true;
      $attributes_ids = [];

      foreach ($params as $option => $value) {
        if (is_numeric($option) && is_numeric($value)) {
          $attributes_ids[] = (int)$option . '}' . (int)$value;
        } else {
          $attributes_check = false;
          break;
        }
}

      if ($attributes_check === true) {
        $string .= '{' . implode(';', $attributes_ids);
      }
}

    return $string;
  }

  /**
   * Gets the product ID from the given input string.
   *
   * @param string $id The input string containing the product ID or other related data.
   * @return int The extracted product ID as an integer.
   */

  public static function getProductID(string $id): int
  {
    if (is_numeric($id)) {
      return $id;
    }

    $id = HTML::sanitize($id);

    $product = explode('{', $id, 2);

    return (int)$product[0];
  }

  /**
   * Sets the sorting field and direction for the query.
   *
   * @param string $field The field by which the sorting should be applied. Supported values are 'model', 'manufacturer', 'quantity', 'weight', 'price', and 'date_added'.
   * @param string $direction The sorting direction. Use '+' for ascending or '-' for descending. Defaults to '+'.
   * @return void
   */
  public function setSortBy(string $field, string $direction = '+'): void
  {
    switch ($field) {
      case 'model':
        $this->sort_by = 'p.products_model';
        break;
      case 'manufacturer':
        $this->sort_by = 'm.manufacturers_name';
        break;
      case 'quantity':
        $this->sort_by = 'p.products_quantity';
        break;
      case 'weight':
        $this->sort_by = 'p.products_weight';
        break;
      case 'price':
        $this->sort_by = 'p.products_price';
        break;
      case 'date_added':
        $this->sort_by = 'p.products_date_added';
        break;
    }

    $this->sort_by_direction = ($direction == '-') ? '-' : '+';
  }

  /**
   * Sets the sort direction for sorting operations.
   *
   * @param string $direction The sorting direction, either '+' for ascending or '-' for descending. Any other input defaults to '+'.
   * @return void
   */
  public function setSortByDirection(string $direction): void
  {
    $this->sort_by_direction = ($direction == '-') ? '-' : '+';
  }
}
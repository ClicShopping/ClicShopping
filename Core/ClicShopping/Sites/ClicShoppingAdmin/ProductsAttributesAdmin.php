<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
/**
 * Retrieves the name associated with a given product option ID.
 *
 * This function fetches the product option name based on the provided option ID and the language ID
 * obtained from the system's registry.
 *
 * @param int $options_id The ID of the product option.
 * @return string The name of the product option.
 */
class ProductsAttributesAdmin
{

  /**
   * Retrieves the name of a product option based on its ID and the current language.
   *
   * @param int $options_id The ID of the product option to retrieve the name for.
   * @return string The name of the product option associated with the provided ID.
   */
  public static function getOptionsName(int $options_id): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qoptions = Registry::get('Db')->get('products_options', 'products_options_name', ['products_options_id' => (int)$options_id,
        'language_id' => (int)$CLICSHOPPING_Language->getId()
      ]
    );

    return $Qoptions->value('products_options_name');

  }

  /**
   * Retrieves the name of a product option value based on its ID and the current language ID.
   *
   * @param int $values_id The ID of the product option value.
   * @return string The name of the product option value.
   */
  public static function getValuesName(int $values_id): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $Qvalues = Registry::get('Db')->get('products_options_values', 'products_options_values_name', ['products_options_values_id' => (int)$values_id,
        'language_id' => (int)$CLICSHOPPING_Language->getId()
      ]
    );

    return $Qvalues->value('products_options_values_name');
  }

  /**
   * Generates a string representation of HTML radio buttons based on the given select array, key name, and key value.
   *
   * @param array $select_array An associative or indexed array containing the options for the select input. If indexed, the value will be used as both the key and the display text.
   * @param string $key_name The name attribute for the radio input elements.
   * @param string $key_value The current value to match against keys in the select array to mark the corresponding option as checked.
   * @return string A string containing the generated HTML for the radio buttons.
   */
  public static function getModSelectOption(array $select_array, string $key_name, string $key_value): string
  {
    $string = '';

    foreach ($select_array as $key => $value) {
      if (is_int($key)) $key = $value;
      $checked = ($key_value == $key) ? ' checked="checked"' : '';
      $string .= '<br />' . HTML::radioField('configuration[' . $key_name . ']', $key, $checked) . ' ' . $value;
    }

    return $string;
  }
}
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
 * Handles tax-related functionality for the ClicShoppingAdmin site, extending
 * the shop tax functionality by providing additional features and overrides.
 */
class Tax extends \ClicShopping\Sites\Shop\Tax
{
  /**
   * Retrieves the tax rate based on the provided class ID, country ID, and zone ID.
   *
   * @param int $class_id The tax class ID to retrieve the rate for.
   * @param int|null $country_id The ID of the country to consider for the tax rate. Defaults to the store's country if not provided.
   * @param int|null $zone_id The ID of the zone to consider for the tax rate. Defaults to the store's zone if not provided.
   *
   * @return float The tax rate for the specified parameters.
   */
  public function getTaxRate(int|null $class_id, int|null $country_id = null, int|null $zone_id = null): float
  {
    if (!isset($country_id) && !isset($zone_id)) {
      $country_id = HTML::sanitize(STORE_COUNTRY);
      $zone_id = (int)STORE_ZONE;
    }

    return parent::getTaxRate($class_id, $country_id, $zone_id);
  }

  /**
   * Retrieves a list of tax classes, including their IDs and titles, from the database.
   *
   * @return array An array of tax classes, where each entry contains the tax class ID and title.
   */
  public static function getClasses(): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qtc = $CLICSHOPPING_Db->query('select tax_class_id,
                                             tax_class_title
                                      from :table_tax_class
                                      order by tax_class_title
                                    ');
    $Qtc->execute();

    return $Qtc->fetchAll();
  }

  /**
   * Calculates the tax amount based on the provided price and tax rate.
   *
   * @param mixed $price The price to calculate the tax for.
   * @param mixed $tax The tax rate to apply.
   * @param bool $override Optional. If true, forces the calculation even if DISPLAY_PRICE_WITH_TAX is false.
   * @return mixed The calculated tax amount.
   */
  public static function addTax(mixed $price, mixed $tax, bool $override = false): mixed
  {
    if (((DISPLAY_PRICE_WITH_TAX == 'true') || ($override === true)) && ($tax > 0)) {
      return $price + parent::calculate($price, $tax);
    } else {
      return $price;
    }
  }

  /**
   * Generates an HTML dropdown menu with tax class options from the database.
   *
   * @param string $parameters The name and id attribute for the select element.
   * @param string $selected The value of the tax class to be pre-selected, if any.
   * @return string The generated HTML string for the dropdown menu.
   */
  public static function getTaxClassesPullDown(string $parameters, string $selected = ''): string
  {
    $select_string = '<select name="' . $parameters . '" id ="' . $parameters . '">';

    $Qclasses = Registry::get('Db')->get('tax_class', [
      'tax_class_id',
      'tax_class_title'
    ],
      null,
      'tax_class_title'
    );

    while ($Qclasses->fetch()) {
      $select_string .= '<option value="' . $Qclasses->valueInt('tax_class_id') . '"';

      if ($selected == $Qclasses->valueInt('tax_class_id')) {
        $select_string .= ' SELECTED';
      }

      $select_string .= '>' . $Qclasses->value('tax_class_title') . '</option>';
    }

    $select_string .= '</select>';

    return $select_string;
  }

  /**
   * Retrieves the tax rate value for a given class ID.
   *
   * @param int $class_id The identifier of the tax class.
   * @return string The calculated tax rate value.
   */
  public function getTaxRateValue(int $class_id): string
  {
    return $this->getTaxRate($class_id, -1, -1);
  }

  /**
   * Retrieves a dropdown array for tax classes.
   *
   * @return array List of tax class options.
   */
  public static function taxClassDropDown(): array
  {
    return parent::taxClassDropDown();
  }

  /**
   * Formats and displays the tax rate value with optional padding.
   *
   * @param float $value The tax rate value to be formatted and displayed.
   * @param string|null $padding Optional padding to apply to the displayed tax rate value.
   * @return string The formatted tax rate value as a string.
   */
  public static function displayTaxRateValue(float $value, string|null $padding = null): string
  {
    return parent::displayTaxRateValue($value, $padding);
  }
}

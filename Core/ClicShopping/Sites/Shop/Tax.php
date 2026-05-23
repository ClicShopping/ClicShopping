<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use function strlen;

/**
 * Class Tax
 * Handles tax calculation, tax rate retrieval, and display functionality for different tax scenarios.
 */
class Tax
{
  protected array $tax_rates = [];
  public static string $tag;

  /**
   * Retrieves the tax rate for a given tax class, country, and zone.
   *
   * This method calculates the applicable tax rate based on the provided tax class ID and either the
   * specified or default country and zone IDs. If no country and zone IDs are explicitly provided,
   * the customer's default address is used, or the store's default country and zone are used if the
   * customer is not logged in.
   *
   * @param int $class_id The tax class ID for which the tax rate is to be retrieved.
   * @param int|null $country_id The ID of the country. If not provided or set to -1, the customer's
   *                             country (if logged in) or the store's default country will be used.
   * @param int|null $zone_id The ID of the zone. If not provided or set to -1, the customer's zone
   *                          (if logged in) or the store's default zone will be used.
   *
   * @return float The calculated tax rate for the specified class, country, and zone.
   */
  public function getTaxRate(int $class_id, int|null $country_id = -1, int|null $zone_id = -1)
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (($country_id == -1) && ($zone_id == -1)) {
      if (!$CLICSHOPPING_Customer->isLoggedOn() || !$CLICSHOPPING_Customer->hasDefaultAddress()) {
        $country_id = \defined('STORE_COUNTRY') ? STORE_COUNTRY : 0;
        $zone_id = \defined('STORE_ZONE') ? (int)STORE_ZONE : 0;
      } else {
        $country_id = $CLICSHOPPING_Customer->getCountryID();
        $zone_id = $CLICSHOPPING_Customer->getZoneID();
      }
    }

    if (!isset($this->tax_rates[$class_id][$country_id][$zone_id]['rate'])) {
      $Qtax = $CLICSHOPPING_Db->prepare('select sum(tr.tax_rate) as tax_rate
                                          from :table_tax_rates tr left join :table_zones_to_geo_zones za on (tr.tax_zone_id = za.geo_zone_id)
                                                                   left join :table_geo_zones tz on (tz.geo_zone_id = tr.tax_zone_id)
                                          where (za.zone_country_id is null
                                                  or za.zone_country_id = 0
                                                  or za.zone_country_id = :zone_country_id
                                                 )
                                          and (za.zone_id is null
                                                or za.zone_id = 0
                                                or za.zone_id = :zone_id
                                                )
                                          and tr.tax_class_id = :tax_class_id
                                          group by tr.tax_priority
                                         ');
      $Qtax->bindInt(':zone_country_id', $country_id);
      $Qtax->bindInt(':zone_id', $zone_id);
      $Qtax->bindInt(':tax_class_id', $class_id);
      $Qtax->execute();

      if ($Qtax->rowCount() > 0) {
        $tax_multiplier = 1.0;

        do {
          $tax_multiplier *= 1.0 + ($Qtax->valueDecimal('tax_rate') / 100);
        } while ($Qtax->fetch());

        $tax_rate = ($tax_multiplier - 1.0) * 100;
      } else {
        $tax_rate = 0;
      }

      $this->tax_rates[$class_id][$country_id][$zone_id]['rate'] = $tax_rate;
    }

    return $this->tax_rates[$class_id][$country_id][$zone_id]['rate'];
  }

  /**
   * Retrieves the tax rate description for a specified tax class, country, and zone.
   *
   * @param int $class_id The tax class ID for which the tax rate description is being fetched.
   * @param int|null $country_id The ID of the country related to the tax rate, or null if not applicable.
   * @param int|null $zone_id The ID of the zone related to the tax rate, or null if not applicable.
   *
   * @return string The description of the tax rate, including any applicable tags or default text.
   */
  public function getTaxRateDescription(int $class_id, int|null $country_id, int|null $zone_id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!isset($this->tax_rates[$class_id][$country_id][$zone_id]['description'])) {
      if (\defined('DISPLAY_PRICE_WITH_TAX') && DISPLAY_PRICE_WITH_TAX == 'true') {
        $tag = CLICSHOPPING::getDef('text_price_with_tax_tag');
      } else {
        $tag = '';
      }

      $Qtax = $CLICSHOPPING_Db->prepare('select tr.tax_description
                                            from :table_tax_rates tr left join :table_zones_to_geo_zones za on (tr.tax_zone_id = za.geo_zone_id)
                                                                     left join :table_geo_zones tz on (tz.geo_zone_id = tr.tax_zone_id)
                                            where (za.zone_country_id is null or
                                                    za.zone_country_id = 0 or
                                                    za.zone_country_id = :zone_country_id
                                                    )
                                            and (za.zone_id is null or
                                                  za.zone_id = 0 or
                                                  za.zone_id = :zone_id
                                                  )
                                            and tr.tax_class_id = :tax_class_id
                                            order by tr.tax_priority
                                           ');

      $Qtax->bindInt(':zone_country_id', $country_id);
      $Qtax->bindInt(':zone_id', $zone_id);
      $Qtax->bindInt(':tax_class_id', $class_id);
      $Qtax->execute();

      if ($Qtax->rowCount() > 0) {
        $tax_description = '';

        do {
          $tax_description .= $Qtax->value('tax_description') . ' x ';
        } while ($Qtax->fetch());

        $this->tax_rates[$class_id][$country_id][$zone_id]['description'] = $tag . substr($tax_description, 0, -3);
      } else {
        $this->tax_rates[$class_id][$country_id][$zone_id]['description'] = $tag . CLICSHOPPING::getDef('text_unknown_tax_rate');
      }
    }

    return $this->tax_rates[$class_id][$country_id][$zone_id]['description'];
  }

  /**
   * Calculates the tax amount based on the given price and tax rate.
   *
   * @param float|null $price The base price of the item. If null, it will be treated as 0.
   * @param float|null $tax_rate The tax rate to be applied. If null, it will be treated as 0.
   * @return float The calculated tax amount, rounded to the decimal places of the default currency.
   */
  public static function calculate(?float $price, ?float $tax_rate): float
  {
    $CLICSHOPPING_Currencies = Registry::get('Currencies');

    return round($price * $tax_rate / 100, $CLICSHOPPING_Currencies->currencies[DEFAULT_CURRENCY]['decimal_places']);
  }

  /**
   * Formats a given tax rate value, optionally padding it to a specific number of decimal places.
   *
   * @param float $value The tax rate value to be formatted.
   * @param string|null $padding The number of decimal places to pad the value to. If not numeric, it defaults to a constant value.
   * @return string The formatted tax rate value as a string, including a '%' symbol.
   */
  public static function displayTaxRateValue(float $value, string|null $padding = null): string
  {
    if (!is_numeric($padding)) {
      $padding = (int)TAX_DECIMAL_PLACES;
    }

    if (str_contains($value, '.')) {
      while (true) {
        if (substr($value, -1) == '0') {
          $value = substr($value, 0, -1);
        } else {
          if (substr($value, -1) == '.') {
            $value = substr($value, 0, -1);
          }

          break;
        }
      }
    }

    if ($padding > 0) {
      if (($decimal_pos = strpos($value, '.')) !== false) {
        $decimals = strlen(substr($value, ($decimal_pos + 1)));

        for ($i = $decimals; $i < $padding; $i++) {
          $value .= '0';
        }
      } else {
        $value .= '.';

        for ($i = 0; $i < $padding; $i++) {
          $value .= '0';
        }
      }
    }

    return $value . '%';
  }

  /**
   * Calculates the final price by optionally adding tax based on customer and group configurations.
   *
   * @param float $price The base price of the item.
   * @param float|null $tax The applicable tax rate as a percentage. Pass null if no tax is applied.
   * @return float Returns the price adjusted for tax, if applicable.
   */
  public static function addTax($price, ?float $tax)
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');

    $QgroupTax = $CLICSHOPPING_Db->prepare('select group_tax
                                              from :table_customers_groups
                                              where customers_group_id = :customers_group_id
                                              ');
    $QgroupTax->bindInt(':customers_group_id', $CLICSHOPPING_Customer->getCustomersGroupID());

    $QgroupTax->execute();

    $group_tax = $QgroupTax->fetch();

// Code modifie par rapport a l'original afin d'avoir un meilleur controle sur l'affichage de la TVA selon les comptes clients
    if (($CLICSHOPPING_Customer->isLoggedOn()) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) && ($group_tax['group_tax'] == 'true') && ($tax > 0)) {
      $group_taxed = 'true';
    } elseif (($CLICSHOPPING_Customer->isLoggedOn()) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) && ($group_tax['group_tax'] != 'true')) {
      $group_taxed = 'false';
    } elseif (($CLICSHOPPING_Customer->isLoggedOn()) && (DISPLAY_PRICE_WITH_TAX == 'true') && ($tax > 0)) {
      $group_taxed = 'true';
    } elseif ((!$CLICSHOPPING_Customer->isLoggedOn()) && (DISPLAY_PRICE_WITH_TAX == 'true') && ($tax > 0)) {
      $group_taxed = 'true';
    } else {
      $group_taxed = 'false';
    }

    // defined the tag to use on the product price with tax or without tax (HT or TTC)
    if (DISPLAY_PRODUCT_PRICE_VALUE_TAX == 'true' || DISPLAY_PRODUCT_PRICE_VALUE_TAX_PRO == 'true') {
      static::$tag = CLICSHOPPING::getDef('tax_included');
    } else {
      static::$tag = CLICSHOPPING::getDef('tax_excluded');
    }

    return match ($group_taxed) {
      'true' => round($price, $CLICSHOPPING_Currencies->currencies[DEFAULT_CURRENCY]['decimal_places']) + static::calculate($price, $tax),
      'false' => round($price, $CLICSHOPPING_Currencies->currencies[DEFAULT_CURRENCY]['decimal_places']),
      default => round($price, $CLICSHOPPING_Currencies->currencies[DEFAULT_CURRENCY]['decimal_places']),
    };
  }

  /**
   * Retrieves an array representing a drop-down menu of tax classes.
   * Each entry in the array contains an ID and a corresponding text representation of a tax class.
   * Default includes an option with ID 0 and a text of "None".
   *
   * @return array Returns an array of tax classes, where each element is an associative array with keys 'id' and 'text'.
   */

  public static function taxClassDropDown(): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $tax_class_array = array(array('id' => '0',
      'text' => CLICSHOPPING::getDef('text_none')
    )
    );

    $QtaxClass = $CLICSHOPPING_Db->get('tax_class', [
      'tax_class_id',
      'tax_class_title'
    ],
      null,
      'tax_class_title'
    );

    while ($QtaxClass->fetch()) {
      $tax_class_array[] = ['id' => $QtaxClass->valueInt('tax_class_id'),
        'text' => $QtaxClass->value('tax_class_title')
      ];
    }

    return $tax_class_array;
  }

  /**
   * Retrieves the tag associated with the class.
   *
   * @return string The tag value.
   */
  public function getTag(): string
  {
    $tag = static::$tag;

    return $tag;
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsLength\Classes\Shop;

use ClicShopping\OM\Registry;
use function is_null;

class ProductsLength
{
  protected $products_length_classes = [];
  protected $precision = 2;

  /**
   * Constructor method for the class.
   *
   * @param int|null $precision Optional precision value. If provided and is an integer, it sets the precision.
   * @return void
   */
  public function __construct(mixed $precision = null)
  {
    if (is_int($precision)) {
      $this->precision = $precision;
    }

    $this->prepareRules();
  }

  /**
   *
   * @return string The numeric decimal separator.
   */
  public static function getNumericDecimalSeparator()
  {
    return '.';
  }

  /**
   * Retrieves the character used as the thousands separator in numeric values.
   *
   * @return string The thousands separator character.
   */
  public static function getNumericThousandsSeparator()
  {
    return ' ';
  }

  /**
   * Retrieves the title of a product length class based on the given length class ID and optional language ID.
   *
   * @param int $id The ID of the product length class.
   * @param int|null $language_id Optional. The ID of the language. If null, the default language ID will be used.
   * @return string The title of the product length class.
   */
  public static function getTitle(mixed $id, ?int $language_id = null)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (is_null($language_id)) {
      $Qproducts_length = $CLICSHOPPING_Db->prepare('select products_length_class_title
                                                        from :table_products_length_classes
                                                        where products_length_class_id = :products_length_class_id
                                                        and language_id = :language_id
                                                       ');
      $Qproducts_length->bindInt(':products_length_class_id', $id);
      $Qproducts_length->bindInt(':language_id', $CLICSHOPPING_Language->getID());
      $Qproducts_length->execute();
    } else {
      $Qproducts_length = $CLICSHOPPING_Db->prepare('select products_length_class_title
                                                      from :table_products_length_classes
                                                      where products_length_class_id = :products_length_class_id
                                                      and language_id = :language_id
                                                     ');
      $Qproducts_length->bindInt(':products_length_class_id', $id);
      $Qproducts_length->bindInt(':language_id', $language_id);
      $Qproducts_length->execute();
    }

    return $Qproducts_length->value('products_length_class_title');
  }

  /**
   * Retrieves the unit of measurement key for a given product length class.
   * If no language ID is provided, the current language ID is used.
   *
   * @param int $id The ID of the product length class to retrieve.
   * @param int|null $language_id The optional ID of the language to use. Defaults to the current language.
   *
   * @return string|null The product length class key for the given ID and language, or null if not found.
   */
  public static function getUnit(mixed $id, ?int $language_id = null)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (is_null($language_id)) {
      $Qproducts_length = $CLICSHOPPING_Db->prepare('select products_length_class_key
                                                        from :table_products_length_classes
                                                        where products_length_class_id = :products_length_class_id
                                                        and language_id = :language_id
                                                       ');
      $Qproducts_length->bindInt(':products_length_class_id', $id);
      $Qproducts_length->bindInt(':language_id', $CLICSHOPPING_Language->getID());
      $Qproducts_length->execute();
    } else {
      $Qproducts_length = $CLICSHOPPING_Db->prepare('select products_length_class_key
                                                      from :table_products_length_classes
                                                      where products_length_class_id = :products_length_class_id
                                                      and language_id = :language_id
                                                     ');
      $Qproducts_length->bindInt(':products_length_class_id', $id);
      $Qproducts_length->bindInt(':language_id', $language_id);
      $Qproducts_length->execute();
    }

    return $Qproducts_length->value('products_length_class_key');
  }

  /**
   * Prepares the rules and data for product length classes by fetching them from the database
   * and storing the information in the class property `$products_length_classes`.
   * Rules include mapping between length class IDs and their respective conversion rates along with
   * their associated keys and titles based on the language.
   *
   * @return void
   */
  public function prepareRules()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qrules = $CLICSHOPPING_Db->prepare('select r.products_length_class_from_id,
                                                  r.products_length_class_to_id,
                                                  r.products_length_class_rule
                                          from :table_products_length_classes_rules r,
                                                :table_products_length_classes c
                                          where c.products_length_class_id = r.products_length_class_from_id
                                          ');
    $Qrules->setCache('products_length-rules');
    $Qrules->execute();

    while ($Qrules->fetch()) {
      $this->products_length_classes[$Qrules->valueInt('products_length_class_from_id')][$Qrules->valueInt('products_length_class_to_id')] = $Qrules->value('products_length_class_rule');
    }

    $Qclasses = $CLICSHOPPING_Db->prepare('select products_length_class_id,
                                                    products_length_class_key,
                                                    products_length_class_title
                                              from :table_products_length_classes
                                              where language_id = :language_id
                                              ');
    $Qclasses->bindInt(':language_id', $CLICSHOPPING_Language->getID());
    $Qclasses->setCache('products_length-classes');
    $Qclasses->execute();

    while ($Qclasses->fetch()) {
      $this->products_length_classes[$Qclasses->valueInt('products_length_class_id')]['key'] = $Qclasses->value('products_length_class_key');
      $this->products_length_classes[$Qclasses->valueInt('products_length_class_id')]['title'] = $Qclasses->value('products_length_class_title');
    }
  }

  /**
   * Converts a value from one unit to another based on predefined conversion rates.
   *
   * @param float|int $value The numerical value to be converted.
   * @param int $unit_from The unit ID of the original value.
   * @param int $unit_to The unit ID to which the value should be converted.
   * @return string The converted value, formatted with the defined precision, decimal separator, and thousands separator.
   */
  public function convert($value, $unit_from, $unit_to)
  {
    if ($unit_from == $unit_to) {
      $convert = number_format($value, $this->precision, static::getNumericDecimalSeparator(), static::getNumericThousandsSeparator());
    } else {
      $convert = number_format($value * $this->products_length_classes[(int)$unit_from][(int)$unit_to], $this->precision, static::getNumericDecimalSeparator(), static::getNumericThousandsSeparator());
    }
    return $convert;
  }

  /**
   * Formats and displays a numeric value with specified precision, decimal separator, and thousands separator, appending a class-specific key.
   *
   * @param float|int $value The numeric value to be formatted and displayed.
   * @param string $class The class key used to retrieve the corresponding unit or identifier.
   * @return string The formatted string representation of the value appended with the class key.
   */
  public function display($value, $class)
  {
    return number_format($value, $this->precision, static::getNumericDecimalSeparator(), static::getNumericThousandsSeparator()) . $this->products_length_classes[$class]['key'];
  }

  /**
   * Retrieves a list of product length classes from the database, including their IDs and titles.
   *
   * @return array An array of length class data, where each entry includes 'id' and 'title' keys.
   */
  public static function getClasses()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $products_length_class_array = [];

    $Qclasses = $CLICSHOPPING_Db->prepare('select products_length_class_id,
                                                    products_length_class_title
                                              from :table_products_length_classes
                                              where language_id = :language_id
                                              order by products_length_class_title
                                            ');
    $Qclasses->bindInt(':language_id', $CLICSHOPPING_Language->getID());
    $Qclasses->execute();

    while ($Qclasses->fetch()) {
      $products_length_class_array[] = ['id' => $Qclasses->valueInt('products_length_class_id'),
        'title' => $Qclasses->value('products_length_class_title')
      ];
    }

    return $products_length_class_array;
  }
}
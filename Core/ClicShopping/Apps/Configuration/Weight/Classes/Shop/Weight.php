<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Weight\Classes\Shop;

use ClicShopping\OM\Registry;

class Weight
{
  protected array $weight_classes = [];
  protected $precision;

  /**
   * Constructor method.
   *
   * @param int $precision Optional. The precision to be used, defaults to 2.
   * @return void
   */
  public function __construct(int $precision = 2)
  {
    if (\is_int($precision)) {
      $this->precision = $precision;
    }

    $this->prepareRules();
  }

  /**
   * Gets the character used as the decimal separator in numeric values.
   *
   * @return string The decimal separator used in numeric formatting.
   */
  public static function getNumericDecimalSeparator(): string
  {
    return '.';
  }

  /**
   * Retrieves the character used as the thousands separator in numeric formatting.
   *
   * @return string The character used as the thousands separator.
   */
  public static function getNumericThousandsSeparator(): string
  {
    return ' ';
  }

  /**
   * Retrieves the title of a weight class based on its ID and optionally a language ID.
   *
   * @param int $id The ID of the weight class.
   * @param int|null $language_id The optional ID of the language. If null, the default language ID is used.
   * @return string The title of the weight class.
   */
  public static function getTitle(int $id,  int|null $language_id = null): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (\is_null($language_id)) {
      $Qweight = $CLICSHOPPING_Db->prepare('select weight_class_title
                                              from :table_weight_classes
                                              where weight_class_id = :weight_class_id
                                              and language_id = :language_id
                                             ');
      $Qweight->bindInt(':weight_class_id', $id);
      $Qweight->bindInt(':language_id', $CLICSHOPPING_Language->getID());
      $Qweight->execute();
    } else {
      $Qweight = $CLICSHOPPING_Db->prepare('select weight_class_title
                                            from :table_weight_classes
                                            where weight_class_id = :weight_class_id
                                            and language_id = :language_id
                                           ');
      $Qweight->bindInt(':weight_class_id', $id);
      $Qweight->bindInt(':language_id', $language_id);
      $Qweight->execute();
    }

    return $Qweight->value('weight_class_title');
  }

  /**
   * Prepares the rules and classes for weight conversions by fetching
   * and caching the data from the database and assigning the results
   * to the weight_classes property.
   *
   * @return void
   */
  public function prepareRules(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qrules = $CLICSHOPPING_Db->prepare('select r.weight_class_from_id,
                                                  r.weight_class_to_id,
                                                  r.weight_class_rule
                                          from :table_weight_classes_rules r,
                                                :table_weight_classes c
                                          where c.weight_class_id = r.weight_class_from_id
                                          ');
    $Qrules->setCache('weight-rules');
    $Qrules->execute();

    while ($Qrules->fetch()) {
      $this->weight_classes[$Qrules->valueInt('weight_class_from_id')][$Qrules->valueInt('weight_class_to_id')] = $Qrules->value('weight_class_rule');
    }

    $Qclasses = $CLICSHOPPING_Db->prepare('select weight_class_id,
                                                    weight_class_key,
                                                    weight_class_title
                                              from :table_weight_classes
                                              where language_id = :language_id
                                              ');
    $Qclasses->bindInt(':language_id', $CLICSHOPPING_Language->getID());
    $Qclasses->setCache('weight-classes');
    $Qclasses->execute();

    while ($Qclasses->fetch()) {
      $this->weight_classes[$Qclasses->valueInt('weight_class_id')]['key'] = $Qclasses->value('weight_class_key');
      $this->weight_classes[$Qclasses->valueInt('weight_class_id')]['title'] = $Qclasses->value('weight_class_title');
    }
  }

  /**
   * Converts a given value from one unit to another unit.
   *
   * @param mixed $value The value to be converted.
   * @param mixed $unit_from The unit from which conversion starts.
   * @param mixed $unit_to The target unit to which the value will be converted.
   * @return false|string Returns the converted value as a formatted string, or false on failure.
   */
  public function convert(mixed $value, mixed $unit_from, mixed $unit_to): false|string
  {
    $convert = false;

    if (!\is_null($value)) {
      if ($unit_from == $unit_to) {
        $convert = number_format($value, $this->precision, static::getNumericDecimalSeparator(), static::getNumericThousandsSeparator());
      } else {
        if ($unit_from !== false && $unit_to !== false && $value !== false && is_numeric($value)) {
          $convert = number_format($value * $this->weight_classes[(int)$unit_from][(int)$unit_to], $this->precision, static::getNumericDecimalSeparator(), static::getNumericThousandsSeparator());
        } else {
          $convert = false;
        }
      }
    }

    return $convert;
  }

  /**
   * Formats a numeric value according to a specific precision and appends a class key.
   *
   * @param float|int $value The numeric value to be formatted.
   * @param mixed $class The class used to determine the key to append.
   * @return string The formatted numeric value concatenated with the class key.
   */
  public function display(float|int $value, mixed $class): string
  {
    return number_format($value, $this->precision, static::getNumericDecimalSeparator(), static::getNumericThousandsSeparator()) . $this->weight_classes[$class]['key'];
  }

  /**
   * Retrieves a list of weight classes from the database for the current language.
   *
   * @return array Returns an array of weight classes, where each entry is an associative array
   * containing 'id' as the weight class ID and 'title' as the weight class title.
   */
  public static function getClasses(): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    $weight_class_array = [];

    $Qclasses = $CLICSHOPPING_Db->prepare('select weight_class_id,
                                                    weight_class_title
                                              from :table_weight_classes
                                              where language_id = :language_id
                                              order by weight_class_title
                                            ');
    $Qclasses->bindInt(':language_id', $CLICSHOPPING_Language->getID());
    $Qclasses->execute();

    while ($Qclasses->fetch()) {
      $weight_class_array[] = [
        'id' => $Qclasses->valueInt('weight_class_id'),
        'title' => $Qclasses->value('weight_class_title')
      ];
    }

    return $weight_class_array;
  }
}
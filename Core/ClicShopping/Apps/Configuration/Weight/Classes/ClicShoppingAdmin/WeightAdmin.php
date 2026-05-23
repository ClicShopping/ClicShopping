<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Weight\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use function is_null;

class WeightAdmin extends \ClicShopping\Apps\Configuration\Weight\Classes\Shop\Weight
{
  protected array $weight_classes = [];
  protected $precision = 2;

  /**
   * Constructor method to initialize the object with optional precision value.
   *
   * @param int|null $precision The precision value to set, or null for default behavior.
   * @return void
   */
  public function __construct(mixed $precision = null)
  {
  }

  /**
   * Retrieves the title for the specified ID and optionally a language ID.
   *
   * @param int $id The identifier for which the title is being retrieved.
   * @param int|null $language_id The optional language identifier for the title.
   * @return string The title associated with the given ID and optional language.
   */
  public static function getTitle(int $id,  int|null $language_id = null): string
  {
    return parent::getTitle($id, $language_id);
  }

  /**
   * Retrieves a list of classes.
   *
   * @return array An array of class names.
   */
  public static function getClasses(): array
  {
    return parent::getClasses();
  }

  /**
   * Displays a formatted value with a specified class.
   *
   * @param mixed $value The value to be displayed.
   * @param string $class The CSS class to be applied for styling.
   * @return string The formatted display output.
   */
  public function display($value, $class): string
  {
    return parent::display($value, $class);
  }

  /**
   * Converts a value from one unit to another.
   *
   * @param mixed $value The value to be converted.
   * @param string $unit_from The source unit of the value.
   * @param string $unit_to The target unit for the conversion.
   * @return false|string Returns the converted value as a string, or false on failure.
   */
  public function convert($value, $unit_from, $unit_to): false|string
  {
    parent::convert($value, $unit_from, $unit_to);
  }

  /**
   * Retrieves a list of weight classes from the database, formatted for use in a pull-down menu.
   *
   * @return array An array of weight classes, each represented as an associative array with keys 'id' and 'text'.
   */
  public static function getClassesPullDown(): array
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qclasses = $CLICSHOPPING_Db->prepare('select weight_class_id,
                                                    weight_class_title
                                              from :table_weight_classes
                                              where language_id = :language_id
                                              order by weight_class_title
                                            ');
    $Qclasses->bindInt(':language_id', $CLICSHOPPING_Language->getID());
    $Qclasses->execute();

    $classes = [];

    while ($Qclasses->fetch() !== false) {
      $classes[] = [
        'id' => $Qclasses->valueInt('weight_class_id'),
        'text' => $Qclasses->value('weight_class_title')
      ];
    }

    return $classes;
  }

  /**
   * Retrieves the weight class title for a given weight class ID and language.
   *
   * @param int|null $id The ID of the weight class. If null, no query is executed.
   * @return string The title of the weight class.
   */
  public static function getWeightTitle( int|null $id = null): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!is_null($id)) {
      $Qweight = $CLICSHOPPING_Db->prepare('select weight_class_title
                                               from :table_weight_classes
                                               where weight_class_id = :weight_class_id
                                               and language_id = :language_id
                                               ');
      $Qweight->bindInt(':weight_class_id', $id);
      $Qweight->bindInt(':language_id', $CLICSHOPPING_Language->getID());

      $Qweight->execute();

      $result = $Qweight->value('weight_class_title');

      return $result;
    }
  }
}

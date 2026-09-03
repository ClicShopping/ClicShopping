<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use function is_null;
/**
 * Class ManufacturerAdmin
 *
 * Provides utility methods for managing manufacturer-related operations in the admin panel.
 */
class ManufacturerAdmin
{
  /**
   * Retrieves the description of a manufacturer based on the given manufacturer ID and language ID.
   *
   * @param int|null $manufacturers_id The ID of the manufacturer. If null, no manufacturer description will be returned.
   * @param int $language_id The language ID for which the manufacturer description is requested.
   *
   * @return string The description of the manufacturer corresponding to the provided IDs.
   */
  public static function getManufacturerDescription( int|null $manufacturers_id, int $language_id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qmanufacturers = $CLICSHOPPING_Db->prepare('select manufacturer_description
                                                    from :table_manufacturers_info
                                                    where manufacturers_id = :manufacturers_id
                                                    and languages_id = :language_id
                                                  ');

    $Qmanufacturers->bindInt(':manufacturers_id', $manufacturers_id);
    $Qmanufacturers->bindInt(':language_id', $language_id);
    $Qmanufacturers->execute();

    return $Qmanufacturers->value('manufacturer_description');
  }

  /**
   * Retrieves the name of a manufacturer based on its ID.
   *
   * @param int $id The ID of the manufacturer.
   * @return string The name of the manufacturer.
   */
  public static function getManufacturerNameById(int $id): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qmanufacturers = $CLICSHOPPING_Db->prepare('select manufacturers_name
                                                  from :table_manufacturers
                                                  where manufacturers_id = :manufacturers_id
                                                ');
    $Qmanufacturers->bindInt(':manufacturers_id', $id);
    $Qmanufacturers->execute();

    $result = $Qmanufacturers->value('manufacturers_name');

    return $result;
  }

  /**
   * Retrieves the manufacturer name or detailed information based on a given product ID.
   *
   * @param int|null $id The ID of the product for which the manufacturer details are to be retrieved.
   *                     Pass null to return an empty string.
   * @return array|string Returns an array containing manufacturer details if a valid product ID is provided.
   *                      Returns an empty string if the product ID is null.
   */
  public static function getManufacturerName( int|null $id = null): array|string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!is_null($id)) {
      $Qproducts = $CLICSHOPPING_Db->prepare('select manufacturers_id
                                                from :table_products
                                                where products_id = :products_id
                                              ');
      $Qproducts->bindInt(':products_id', $id);

      $Qproducts->execute();

      $Qmanufacturers = $CLICSHOPPING_Db->prepare('select manufacturers_id,
                                                           manufacturers_name
                                                    from :table_manufacturers
                                                    where manufacturers_id = :manufacturers_id
                                                  ');
      $Qmanufacturers->bindInt(':manufacturers_id', $Qproducts->valueInt('manufacturers_id'));
      $Qmanufacturers->execute();

      $result = $Qmanufacturers->fetchAll();

      return $result;
    } else {
      return '';
    }
  }

  /**
   * Retrieves the manufacturer ID based on the provided manufacturer name.
   * If no manufacturer name is provided, it defaults to returning 0.
   *
   * @param string|null $manufacturer_name The name of the manufacturer to look up. If null, returns default value 0.
   * @return int|string The manufacturer ID as an integer if found, or 0 if no name is provided or if the name does not exist.
   */
  public static function getManufacturerId(?string $manufacturer_name = null): int|string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!is_null($manufacturer_name)) {
      $Qmanufacturers = $CLICSHOPPING_Db->prepare('select manufacturers_id
                                                    from :table_manufacturers
                                                    where manufacturers_name = :manufacturers_name
                                                    limit 1
                                                   ');
      $Qmanufacturers->bindValue(':manufacturers_name', $manufacturer_name);

      $Qmanufacturers->execute();

      return $Qmanufacturers->valueInt('manufacturers_id');
    } else {
      return $manufacturers_id = 0;
    }
  }
}

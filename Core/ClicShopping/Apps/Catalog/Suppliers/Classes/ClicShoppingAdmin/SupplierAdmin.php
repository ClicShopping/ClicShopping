<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Classes\ClicShoppingAdmin;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function is_null;
/**
 * Class SupplierAdmin
 *
 * Provides methods for managing supplier-related data in the admin environment.
 */
class SupplierAdmin
{
  private mixed $db;

  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Retrieves the supplier information based on the product ID provided in the query parameter.
   *
   * @return array|bool Returns an array containing supplier information if the product ID is valid and found;
   *                    otherwise, returns false if the product ID is not set or invalid.
   */
  public function getSupplier(): array|bool
  {
    if (isset($_GET['pID'])) {
      $pID = HTML::sanitize($_GET['pID']);

      $Qproducts = $this->db->prepare('select suppliers_id
                                              from :table_products
                                              where products_id = :products_id
                                            ');
      $Qproducts->bindInt(':products_id', HTML::sanitize($pID));

      $Qproducts->execute();

      $Qsuppliers = $this->db->prepare('select suppliers_id,
                                                       suppliers_name
                                                from :table_suppliers
                                                where suppliers_id = :suppliers_id
                                              ');
      $Qsuppliers->bindInt(':suppliers_id', $Qproducts->valueInt('suppliers_id'));
      $Qsuppliers->execute();

      $result = $Qsuppliers->fetchAll();

      return $result;
    } else {
      return false;
    }
  }

  /**
   * Retrieves the supplier URL based on the given supplier ID and language ID.
   *
   * @param int|null $supplier_id The ID of the supplier. Pass null to indicate that no supplier ID is specified.
   * @param int $language_id The ID of the language to retrieve the supplier URL for.
   *
   * @return string The supplier URL if found or an empty string if no supplier ID is provided or no URL is found.
   */
  public function getSupplierUrl( int|null $supplier_id, int $language_id): string
  {
    if (!is_null($supplier_id)) {
      $Qsuppliers = $this->db->prepare('select suppliers_url
                                           from :table_suppliers_info
                                           where suppliers_id = :suppliers_id
                                           and languages_id = :language_id
                                         ');
      $Qsuppliers->bindInt(':suppliers_id', $supplier_id);
      $Qsuppliers->bindInt(':language_id', $language_id);

      $Qsuppliers->execute();

      return $Qsuppliers->value('suppliers_url');
    } else {
      return '';
    }
  }

  /**
   * Retrieves the supplier ID based on the provided supplier name.
   *
   * @param string|null $supplier_name The name of the supplier to retrieve the ID for. If null, a default ID will be returned.
   * @return int|string Returns the supplier ID if the supplier name is provided and found; otherwise, returns 0.
   */
  public function getSupplierId(?string $supplier_name = null): int|string
  {
    if (!is_null($supplier_name)) {
      $Qsuppliers = $this->db->prepare('select suppliers_id
                                          from :table_suppliers
                                          where suppliers_name = :suppliers_name
                                          limit 1
                                       ');
      $Qsuppliers->bindValue(':suppliers_name', $supplier_name);

      $Qsuppliers->execute();

      return $Qsuppliers->value('suppliers_id');
    } else {
      return $suppliers_id = 0;
    }
  }
}
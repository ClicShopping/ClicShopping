<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Classes\Shop;

use ClicShopping\OM\Registry;
use function is_null;
/**
 * Class ProductsAttributesShop
 * Provides methods to manage and retrieve product attributes information.
 */
class ProductsAttributesShop
{
  private mixed $lang;
  public mixed $app;
  private mixed $productsCommon;
  private mixed $customer;
  private mixed $db;

  /**
   * Constructor method for initializing the ProductsAttributesShop class.
   *
   * This method retrieves necessary components from the Registry, including Language, Database,
   * ProductsCommon, and Customer instances, and assigns them to class properties for later use.
   */
  public function __construct()
  {
    $this->lang = Registry::get('Language');
    $this->db = Registry::get('Db');
    $this->productsCommon = Registry::get('ProductsCommon');
    $this->customer = Registry::get('Customer');
  }

  /**
   * Set the count of product attributes based on provided or default product ID.
   *
   * @param int|null $id The product ID for which attributes are counted. If null, defaults to the current product ID.
   * @return float The total count of product attributes.
   */
  private function setCountProductsAttributes(mixed $id = null)
  {
    if (is_null($id)) {
      $id = $this->productsCommon->getID();
    }

    $language_id = $this->lang->getId();

    if ($this->customer->getCustomersGroupID() != 0) {
      $QproductsAttributes = $this->db->prepare('select count(*) as total
                                                   from :table_products_options popt,
                                                        :table_products_attributes patrib
                                                   where patrib.products_id = :products_id
                                                   and patrib.options_id = popt.products_options_id
                                                   and popt.language_id = :language_id
                                                   and (patrib.customers_group_id = :customers_group_id or patrib.customers_group_id = 99)
                                                   and patrib.status = 1
                                                 ');

      $QproductsAttributes->bindInt(':products_id', $id);
      $QproductsAttributes->bindInt(':language_id', $language_id);
      $QproductsAttributes->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

      $QproductsAttributes->execute();
    } else {
      $QproductsAttributes = $this->db->prepare('select count(*) as total
                                                   from :table_products_options popt,
                                                        :table_products_attributes patrib
                                                   where patrib.products_id = :products_id
                                                   and patrib.options_id = popt.products_options_id
                                                   and popt.language_id = :language_id
                                                   and (patrib.customers_group_id = 0 or patrib.customers_group_id = 99)
                                                   and patrib.status = 1
                                                  ');

      $QproductsAttributes->bindInt(':products_id', $id);
      $QproductsAttributes->bindInt(':language_id', $language_id);

      $QproductsAttributes->execute();
    }

    return $QproductsAttributes->valueInt('total');
  }

  /**
   * Get the count of products attributes
   * @param int|null $id The ID of the product to count attributes for, or null to count for all products
   * @return int
   */
  public function getCountProductsAttributes(mixed $id = null): int
  {
    return $this->setCountProductsAttributes($id);
  }

  /**
   * Determine if a product has attributes
   * @param int|null $id Product ID. If null, the ID is retrieved automatically.
   * @return bool True if the product has attributes, false otherwise.
   */
  public function getHasProductAttributes(mixed $id = null)
  {
    if (is_null($id)) {
      $id = $this->productsCommon->getID();
    }

    $Qattributes = $this->db->prepare('select products_id
                                         from :table_products_attributes
                                         where products_id = :products_id
                                         and status = 1
                                         limit 1
                                        ');
    $Qattributes->bindInt(':products_id', $id);

    $Qattributes->execute();

    return $Qattributes->fetch() !== false;
  }

  /**
   * Retrieve product attributes information based on the given parameters.
   * @param int $products_id The ID of the product to fetch attributes for.
   * @param int $option_id The ID of the option associated with the product.
   * @param int $language_id The ID of the language for localized attribute information.
   * @param int|null $options_values_id The ID of the option's value to filter attributes (optional).
   * @return object The prepared query object containing the fetched product attributes information.
   */
  public function getProductsAttributesInfo(mixed $products_id, int $option_id, int $language_id, int|null $options_values_id = null)
  {
    if (!is_null($options_values_id)) {
      if ($this->customer->getCustomersGroupID() != 0) {
        $Qattributes = $this->db->prepare('select distinct popt.products_options_name,
                                                             popt.products_options_type,
                                                             poval.products_options_values_name,
                                                             pa.options_values_price,
                                                             pa.price_prefix,
                                                             pa.products_attributes_reference,
                                                             pa.products_attributes_image
                                            from :table_products_options popt,
                                                 :table_products_options_values poval,
                                                 :table_products_attributes pa
                                            where pa.products_id = :products_id
                                            and pa.options_id = :options_id
                                            and pa.options_id = popt.products_options_id
                                            and pa.options_values_id = :options_values_id
                                            and pa.options_values_id = poval.products_options_values_id
                                            and popt.language_id = :language_id
                                            and poval.language_id = :language_id
                                            and (pa.customers_group_id = :customers_group_id or pa.customers_group_id = 99)
                                            and pa.status = 1
                                           ');
        $Qattributes->bindInt(':products_id', $products_id);
        $Qattributes->bindInt(':options_id', $option_id);
        $Qattributes->bindInt(':options_values_id', $options_values_id);
        $Qattributes->bindInt(':language_id', $language_id);
        $Qattributes->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

        $Qattributes->execute();
      } else {
        $Qattributes = $this->db->prepare('select distinct popt.products_options_name,
                                                             popt.products_options_type,
                                                             poval.products_options_values_name,
                                                             pa.options_values_price,
                                                             pa.price_prefix,
                                                             pa.products_attributes_reference,
                                                             pa.products_attributes_image
                                            from :table_products_options popt,
                                                 :table_products_options_values poval,
                                                 :table_products_attributes pa
                                            where pa.products_id = :products_id
                                            and pa.options_id = :options_id
                                            and pa.options_id = popt.products_options_id
                                            and pa.options_values_id = :options_values_id
                                            and pa.options_values_id = poval.products_options_values_id
                                            and popt.language_id = :language_id
                                            and poval.language_id = :language_id
                                            and (pa.customers_group_id = 0 or pa.customers_group_id = 99)
                                            and pa.status = 1
                                           ');
        $Qattributes->bindInt(':products_id', $products_id);
        $Qattributes->bindInt(':options_id', $option_id);
        $Qattributes->bindInt(':options_values_id', $options_values_id);
        $Qattributes->bindInt(':language_id', $language_id);

        $Qattributes->execute();
      }
    } else {
      if ($this->customer->getCustomersGroupID() != 0) {
        $Qattributes = $this->db->prepare('select distinct pov.products_options_values_id,
                                                              pov.products_options_values_name,
                                                              pa.options_values_price,
                                                              pa.price_prefix,
                                                              pa.products_attributes_reference,
                                                              pa.products_attributes_image
                                             from :table_products_attributes pa,
                                                  :table_products_options_values pov
                                             where pa.products_id = :products_id
                                             and pa.options_id = :options_id
                                             and pa.options_values_id = pov.products_options_values_id
                                             and pov.language_id = :language_id
                                             and (pa.customers_group_id = :customers_group_id or pa.customers_group_id = 99)
                                             and pa.status = 1
                                             order by pa.products_options_sort_order
                                            ');

        $Qattributes->bindInt(':products_id', $products_id);
        $Qattributes->bindInt(':options_id', $option_id);
        $Qattributes->bindInt(':language_id', $language_id);
        $Qattributes->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

        $Qattributes->execute();
      } else {
        $Qattributes = $this->db->prepare('select distinct pov.products_options_values_id,
                                                              pov.products_options_values_name,
                                                              pa.options_values_price,
                                                              pa.price_prefix,
                                                              pa.products_attributes_reference,
                                                              pa.products_attributes_image
                                             from :table_products_attributes pa,
                                                  :table_products_options_values pov
                                             where pa.products_id = :products_id
                                             and pa.options_id = :options_id
                                             and pa.options_values_id = pov.products_options_values_id
                                             and pov.language_id = :language_id
                                             and (pa.customers_group_id = 0 or pa.customers_group_id = 99)
                                             and pa.status = 1
                                             order by pa.products_options_sort_order
                                            ');

        $Qattributes->bindInt(':products_id', $products_id);
        $Qattributes->bindInt(':options_id', $option_id);
        $Qattributes->bindInt(':language_id', $language_id);
        $Qattributes->execute();
      }
    }

    return $Qattributes;
  }

  /**
   * Check the status of a product by its ID
   * @param int $id The ID of the product to check
   * @return bool
   */
  public function getCheckProductsStatus(int $id)
  {
    $Qcheck = $this->db->prepare('select products_id
                                    from :table_products
                                    where products_id = :products_id
                                    and products_status = 1
                                    and products_archive = 0
                                  ');

    $Qcheck->bindInt(':products_id', $id);
    $Qcheck->execute();

    return $Qcheck->fetch();
  }

  /**
   * Checks for product attributes based on the given product ID, option ID, and option value ID.
   * Also considers the customer's group ID to filter results accordingly.
   *
   * @param int $products_id The ID of the product whose attributes need to be checked.
   * @param int $option_id The ID of the option for the product.
   * @param int $options_values_id The ID of the option value for the specified option.
   *
   * @return array|false The fetched product attribute data or false if no match is found.
   */
  public function getCheckProductsAttributes(int $products_id, int $option_id, int $options_values_id)
  {
    if ($this->customer->getCustomersGroupID() != 0) {
      $Qcheck = $this->db->prepare('select products_attributes_id
                                      from :table_products_attributes
                                      where products_id = :products_id
                                      and options_id = :options_id
                                      and options_values_id = :options_values_id
                                      and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                      and status = 1
                                      limit 1
                                     ');

      $Qcheck->bindInt(':products_id', $products_id);
      $Qcheck->bindInt(':options_id', $option_id);
      $Qcheck->bindInt(':options_values_id', $options_values_id);
      $Qcheck->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

      $Qcheck->execute();
    } else {
      $Qcheck = $this->db->prepare('select products_attributes_id
                                      from :table_products_attributes
                                      where products_id = :products_id
                                      and options_id = :options_id
                                      and options_values_id = :options_values_id
                                      and (customers_group_id = 0 or customers_group_id = 99)
                                      and status = 1
                                      limit 1
                                     ');

      $Qcheck->bindInt(':products_id', $products_id);
      $Qcheck->bindInt(':options_id', $option_id);
      $Qcheck->bindInt(':options_values_id', $options_values_id);

      $Qcheck->execute();
    }

    return $Qcheck->fetch();
  }

//******************************************************
// Download
///******************************************************

  /**
   * Checks if a product's attributes are downloadable based on the provided product ID and option value ID,
   * taking into account the customer's group ID.
   *
   * @param int $products_id The ID of the product to check.
   * @param int $options_values_id The ID of the options value to check.
   * @return int The count of matching downloadable attributes.
   */
  public function getCheckProductsDownload(int $products_id, int $options_values_id)
  {
    if ($this->customer->getCustomersGroupID() != 0) {
      $Qcheck = $this->db->prepare('select pa.products_attributes_id
                                      from :table_products_attributes pa,
                                           :table_products_attributes_download pad
                                      where pa.products_id = :products_id
                                      and pa.options_values_id = :options_values_id
                                      and pa.products_attributes_id = pad.products_attributes_id
                                      and (pa.customers_group_id = :customers_group_id or pa.customers_group_id = 99)
                                      and pa.status = 1
                                      limit 1
                                     ');
      $Qcheck->bindInt(':products_id', $products_id);
      $Qcheck->bindInt(':options_values_id', $options_values_id);
      $Qcheck->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());
      $Qcheck->execute();

      $check = $Qcheck->rowCount();
    } else {
      $Qcheck = $this->db->prepare('select pa.products_attributes_id
                                      from :table_products_attributes pa,
                                           :table_products_attributes_download pad
                                      where pa.products_id = :products_id
                                      and pa.options_values_id = :options_values_id
                                      and pa.products_attributes_id = pad.products_attributes_id
                                      and (pa.customers_group_id = 0 or pa.customers_group_id = 99)
                                      and pa.status = 1
                                      limit 1
                                     ');
      $Qcheck->bindInt(':products_id', $products_id);
      $Qcheck->bindInt(':options_values_id', $options_values_id);
      $Qcheck->execute();

      $check = $Qcheck->rowCount();
    }

    return $check;
  }

  /**
   * Retrieves the attributes of a product, including details such as option names, option values, prices, and downloadable file information if applicable.
   * The results depend on the customer's group ID and the download option configuration.
   *
   * @param int|string $products_id The ID of the product whose attributes are being retrieved.
   * @param int $options_id The ID of the specific option associated with the product.
   * @param int $options_values_id The ID of the value for the given option.
   * @param int $language_id The language ID used to filter attributes by language.
   */
  public function getAttributesDownloaded(int|string $products_id, int $options_id, int $options_values_id, int $language_id)
  {
    if (DOWNLOAD_ENABLED == 'true') {
      if ($this->customer->getCustomersGroupID() != 0) {
        $Qattributes = $this->db->prepare('select popt.products_options_name,
                                                    poval.products_options_values_name,
                                                    pa.options_values_price,
                                                    pa.price_prefix,
                                                    pa.products_attributes_reference,
                                                    pad.products_attributes_maxdays,
                                                    pad.products_attributes_maxcount,
                                                    pad.products_attributes_filename,
                                                    pa.products_attributes_reference
                                           from :table_products_options popt,
                                                :table_products_options_values poval,
                                                :table_products_attributes pa
                                                  left join :table_products_attributes_download pad on pa.products_attributes_id = pad.products_attributes_id
                                           where pa.products_id = :products_id
                                            and pa.options_id = :options_id
                                            and pa.options_id = popt.products_options_id
                                            and pa.options_values_id = :options_values_id
                                            and pa.options_values_id = poval.products_options_values_id
                                            and popt.language_id = :language_id
                                            and popt.language_id = poval.language_id
                                            and (pa.customers_group_id = :customers_group_id or pa.customers_group_id = 99)
                                            and pa.status = 1
                                         ');

        $Qattributes->bindInt(':products_id', $products_id);
        $Qattributes->bindInt(':options_id', $options_id);
        $Qattributes->bindInt(':options_values_id', $options_values_id);
        $Qattributes->bindInt(':language_id', $language_id);
        $Qattributes->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

        $Qattributes->execute();
      } else {
        $Qattributes = $this->db->prepare('select popt.products_options_name,
                                                    poval.products_options_values_name,
                                                    pa.options_values_price,
                                                    pa.price_prefix,
                                                    pa.products_attributes_reference,
                                                    pad.products_attributes_maxdays,
                                                    pad.products_attributes_maxcount,
                                                    pad.products_attributes_filename,
                                                    pa.products_attributes_reference
                                           from :table_products_options popt,
                                                :table_products_options_values poval,
                                                :table_products_attributes pa
                                                  left join :table_products_attributes_download pad on pa.products_attributes_id = pad.products_attributes_id
                                           where pa.products_id = :products_id
                                            and pa.options_id = :options_id
                                            and pa.options_id = popt.products_options_id
                                            and pa.options_values_id = :options_values_id
                                            and pa.options_values_id = poval.products_options_values_id
                                            and popt.language_id = :language_id
                                            and popt.language_id = poval.language_id
                                            and (pa.customers_group_id = 0 or pa.customers_group_id = 99)
                                            and pa.status = 1
                                         ');

        $Qattributes->bindInt(':products_id', $products_id);
        $Qattributes->bindInt(':options_id', $options_id);
        $Qattributes->bindInt(':options_values_id', $options_values_id);
        $Qattributes->bindInt(':language_id', $language_id);

        $Qattributes->execute();
      }
    } else {
      if ($this->customer->getCustomersGroupID() != 0) {
        $Qattributes = $this->db->prepare('select popt.products_options_name,
                                                   poval.products_options_values_name,
                                                   pa.options_values_price,
                                                   pa.price_prefix,
                                                   pa.products_attributes_reference,
                                                   pa.products_attributes_image
                                              from :table_products_options popt,
                                                   :table_products_options_values poval,
                                                   :table_products_attributes pa
                                              where pa.products_id = :products_id
                                              and pa.options_id = :options_id
                                              and pa.options_id = popt.products_options_id
                                              and pa.options_values_id = :options_values_id
                                              and pa.options_values_id = poval.products_options_values_id
                                              and popt.language_id = :language_id
                                              and popt.language_id = poval.language_id
                                              and (pa.customers_group_id = :customers_group_id or pa.customers_group_id = 99)
                                              and pa.status = 1
                                            ');

        $Qattributes->bindInt(':products_id', $products_id);
        $Qattributes->bindInt(':options_id', $options_id);
        $Qattributes->bindInt(':options_values_id', $options_values_id);
        $Qattributes->bindInt(':language_id', $language_id);
        $Qattributes->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());
        $Qattributes->execute();
      } else {
        $Qattributes = $this->db->prepare('select popt.products_options_name,
                                                   poval.products_options_values_name,
                                                   pa.options_values_price,
                                                   pa.price_prefix,
                                                   pa.products_attributes_reference,
                                                   pa.products_attributes_image
                                              from :table_products_options popt,
                                                   :table_products_options_values poval,
                                                   :table_products_attributes pa
                                              where pa.products_id = :products_id
                                              and pa.options_id = :options_id
                                              and pa.options_id = popt.products_options_id
                                              and pa.options_values_id = :options_values_id
                                              and pa.options_values_id = poval.products_options_values_id
                                              and popt.language_id = :language_id
                                              and popt.language_id = poval.language_id
                                              and (pa.customers_group_id = 0 or pa.customers_group_id = 99)
                                              and pa.status = 1
                                            ');

        $Qattributes->bindInt(':products_id', $products_id);
        $Qattributes->bindInt(':options_id', $options_id);
        $Qattributes->bindInt(':options_values_id', $options_values_id);
        $Qattributes->bindInt(':language_id', $language_id);

        $Qattributes->execute();
      }
    }

    return $Qattributes;
  }
}
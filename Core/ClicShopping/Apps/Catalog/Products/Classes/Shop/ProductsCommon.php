<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\Shop;


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Products\Classes\Shop\DynamicPricingRules;

use function is_null;
use function strlen;

/**
 * Class ProductsCommon
 * This class provides functionality for managing details and properties of products,
 * including retrieving product information, performing checks, and updating product views.
 */

class ProductsCommon extends Prod
{
  public mixed $app;
  private mixed $db;
  protected mixed $language;
  protected mixed $customer;

  protected $id;
  protected $products_name;
  protected $products_short_description;
  protected $products_stock;
  protected $image_new_arrival;
  protected $products_flash_discount;
  protected $product_price;
  protected $products_quantity_unit;
  protected $min_order_quantity_products_display;
  protected $input_quantity;
  protected $button_small_view_details;
  protected $submit_button;
  protected $products_image;
  protected $products_model;
  protected $products_manufacturers;
  protected $product_price_kilo;
  protected $products_date_available;
  protected $products_only_shop;
  protected $products_only_web;
  protected $products_packaging;
  protected $products_shipping_delay;
  protected $products_shipping_delay_out_of_stock;
  protected $products_tag;
  protected $products_volume;
  protected $products_weight;
  protected $buy_button;
  protected $delete_word;
  protected $size_button;
  protected $products_dimension;
  protected $submit_button_view;
  protected $manufacturers_id;
  protected $products_weight_class_id;
  protected $infoPriceDiscountByQuantity;
  protected $saveMoney;
  protected $dynamicPricingRules;
  protected string $button;

  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->customer = Registry::get('Customer');
    $this->language = Registry::get('Language');

    Registry::set('DynamicPricingRules',new DynamicPricingRules());
    $this->dynamicPricingRules = Registry::get('DynamicPricingRules');
  }

  /**
   * Retrieves the ID if it is valid, numeric, and not empty.
   *
   * @return int|false Returns the ID if valid, otherwise returns false.
   */
  public function getID(): mixed
  {
    if (parent::getID() === null || !is_numeric(parent::getID()) || empty(parent::getID())) {
      return false;
    } else {
      $id = parent::getID();
    }

    return $id;
  }

  /**
   * Sets and retrieves product data based on the customer's group ID.
   *
   * If the customer belongs to a specific group, it fetches product details
   * along with group-specific pricing and visibility. Otherwise, it retrieves
   * general product details and ensures it is viewable under valid categories.
   *
   * @return mixed $result Returns an array of product data if successful, or false otherwise.
   */
  private function setData()
  {
    if ($this->customer->getCustomersGroupID() != 0) {
      $Qproducts = $this->db->prepare('select p.products_id,
                                                p.products_tax_class_id,
                                                p.orders_view,
                                                p.products_view,
                                                p.products_archive,
                                                p.products_quantity,
                                                g.customers_group_price,
                                                g.price_group_view,
                                                g.orders_group_view,
                                                g.products_group_view
                                        from :table_products p left join :table_products_groups g on p.products_id = g.products_id
                                        where p.products_status = 1
                                        and g.customers_group_id = :customers_group_id
                                        and g.products_group_view = 1
                                        and p.products_id = :products_id
                                       ');

      $Qproducts->bindInt(':products_id', $this->getID());
      $Qproducts->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());
      $Qproducts->execute();
      $result = $Qproducts->toArray();
    } else {
      $Qproducts = $this->db->prepare('select p.products_id,
                                                p.products_tax_class_id,
                                                p.products_quantity,
                                                p.orders_view,
                                                p.products_view,
                                                p.products_archive,
                                                p.products_tax_class_id
                                        from :table_products p,
                                             :table_products_to_categories p2c,
                                             :table_categories c
                                        where p.products_status = 1
                                        and p.products_view = 1
                                        and p.products_id = :products_id
                                        and p.products_id = p2c.products_id
                                        and p2c.categories_id = c.categories_id
                                        and c.status = 1
                                       ');

      $Qproducts->bindInt(':products_id', $this->getID());
      $Qproducts->execute();
      $result = $Qproducts->toArray();
    }

    if ($result === false) return false;

    return $result;
  }

  /**
   * Retrieve and return the processed data
   *
   * @return mixed Result of the setData method
   */
  public function getData()
  {
    return $this->setData();
  }

  /**
   * Retrieve specific object data or all available data.
   * @param mixed|null $obj The key to fetch specific data from the array, or null to fetch all data.
   * @return mixed Returns the specific object data if the key exists, otherwise returns the full data array.
   */
  public function get(mixed $obj = null)
  {
    $array_data = $this->getData();

    if (isset($array_data[$obj])) {
      return $array_data[$obj];
    }

    return $array_data;
  }

  /**
   * Retrieves the group view of the products.
   *
   * @return mixed The products group view data.
   */
  public function getProductsGroupView()
  {
    return $this->get('products_group_view');
  }

  /**
   * Retrieves the value of the 'products_view' property.
   * @return mixed The value of the 'products_view' property.
   */
  public function getProductsView()
  {
    return $this->get('products_view');
  }

  /**
   * Retrieve the archived products.
   * @return mixed The archived products data.
   */
  public function getProductsArchive()
  {
    return $this->get('products_archive');
  }

  /**
   * Retrieve the quantity of products.
   * @return mixed The quantity of products.
   */
  public function getProductsQuantity()
  {
    return $this->get('products_quantity');
  }

  /**
   * Retrieve the tax class ID of a product.
   * @return mixed $products_tax_class_id, the tax class ID of the product
   */
  public function getProductsTaxClassId()
  {
    return $this->get('products_tax_class_id');
  }

  /**
   * Retrieve the orders group view.
   * @return mixed Returns the orders group view data.
   */
  public function getOrdersGroupView()
  {
    return $this->get('orders_group_view');
  }

  /**
   * Retrieve the products orders view.
   * @return mixed The result of fetching the orders view.
   */
  public function getProductsOrdersView()
  {
    return $this->get('orders_view');
  }

  /**
   * Retrieves the value of the price group view.
   *
   * @return mixed The price group view value.
   */
  public function getPriceGroupView()
  {
    return $this->get('price_group_view');
  }

  /**
   * Retrieve the customer's group price.
   *
   * @return mixed The value of the customer's group price.
   */
  private function getCustomersGroupPrice()
  {
    return $this->get('customers_group_price');
  }

  /**
   * Validate the provided ID format and check it against the session name
   *
   * @param mixed $id The ID to be validated
   * @return bool $result True if the ID is valid and not equal to the session name, otherwise false
   */
  public function checkID($id)
  {
    $CLICSHOPPING_Session = Registry::get('Session');

    $result = (preg_match('/^[0-9]+(#?([0-9]+:?[0-9]+)+(;?([0-9]+:?[0-9]+)+)*)*$/', $id) || preg_match('/^[a-zA-Z0-9 -_]*$/', $id)) && ($id != $CLICSHOPPING_Session->getName());

    return $result;
  }

  /**
   * Checks if a product entry exists and is viewable based on the provided ID and customer group conditions.
   *
   * @param mixed $id The product ID or keyword to be checked. Can be numeric or string.
   * @return bool Returns true if the product entry exists and meets the visibility criteria, otherwise false.
   */
  public function checkEntry($id)
  {
    if ($this->checkID($id) === false) {
      return false;
    } else {

      if ($this->customer->getCustomersGroupID() != 0) {
        $sql_query = 'select p.products_id
                     from :table_products p left join :table_products_groups g on p.products_id = g.products_id';

        if (is_numeric($id)) {
          $sql_query .= ' where p.products_id = :products_id';
        } else {
          $sql_query .= ', :table_products_description pd
                          where pd.products_keyword = :products_keyword
                          and pd.products_id = p.products_id';
        }

        $sql_query .= ' and p.products_status = 1
                        and p.products_archive = 0
                        and g.customers_group_id = ' . $this->customer->getCustomersGroupID() . '
                        and g.products_group_view = 1
                        limit 1
                    ';
      } else {
        $sql_query = 'select p.products_id from :table_products p';

        if (is_numeric($id)) {
          $sql_query .= ' where p.products_id = :products_id';
        } else {
          $sql_query .= ', :table_products_description pd
                          where pd.products_keyword = :products_keyword
                          and pd.products_id = p.products_id';
        }

        $sql_query .= ' and p.products_status = 1
                         and p.products_archive = 0
                         and p.products_view = 1
                         limit 1
                      ';
      }

      $Qproduct = $this->db->prepare($sql_query);

      if (is_numeric($id)) {
        $Qproduct->bindInt(':products_id', $id);
      } else {
        $Qproduct->bindValue(':products_keyword', $id);
      }

      $Qproduct->execute();

      $result = $Qproduct->fetch();
    }

    return (($result !== false) && (count($result) === 1));
  }

  /**
   * Increment the product view count in the database.
   * Updates the products_viewed field for a specific product ID and language ID.
   *
   * @return bool True if the query executes successfully, otherwise false.
   */
  public function countUpdateProductsView()
  {
    $Qupdate = $this->db->prepare('update :table_products_description
                                      set products_viewed = products_viewed+1
                                      where products_id = :products_id
                                      and language_id =:language_id
                                     ');
    $Qupdate->bindInt(':products_id', $this->getID());
    $Qupdate->bindInt(':language_id', $this->language->getId());

    return $Qupdate->execute();
  }

  /**
   * Get the total count of active products for a specific product ID and language.
   *
   * @return float $total, the count of matching active products.
   */
  public function getProductsCount(): float
  {
    $QproductCheck = $this->db->prepare('select count(*) as total
                                          from :table_products p,
                                               :table_products_description pd
                                          where p.products_status = 1
                                          and p.products_id = :products_id
                                          and pd.products_id = p.products_id
                                          and pd.language_id = :language_id
                                         ');

    $QproductCheck->bindInt(':products_id', $this->getID());
    $QproductCheck->bindInt(':language_id', $this->language->getId());

    $QproductCheck->execute();

    return $QproductCheck->valueInt('total');
  }

  /**
   * Retrieve the name of a product based on its ID.
   * @param int|null $id The ID of the product. If null, the current ID is used.
   * @return string $products_name The sanitized name of the product.
   */
  public function getProductsName( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $Qproducts = $this->db->prepare('select pd.products_name
                                      from :table_products p,
                                           :table_products_description pd
                                      where p.products_status = 1
                                      and p.products_id = :products_id
                                      and pd.products_id = p.products_id
                                      and pd.language_id = :language_id
                                     ');

    $Qproducts->bindInt(':products_id', $id);
    $Qproducts->bindInt(':language_id', $this->language->getId());

    $Qproducts->execute();

    $products_name = HTML::output($Qproducts->value('products_name'));

    return $products_name;
  }

  /**
   * Set and retrieve the product image.
   * @param int|null $id Product ID, defaults to null, which uses the object's ID.
   * @return string $products_image The protected product image retrieved from the database.
   */
  private function setProductsImage( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_image'], $array);

    $products_image = HTML::outputProtected($Qproducts->value('products_image'));

    return $products_image;
  }

  /**
   * Retrieve the product image for the specified product ID.
   * @param int|null $id The product ID to retrieve the image for. If null, retrieves the default or previously set product ID.
   * @return string|false Returns the product image as a string if successful, or false if the image could not be set.
   */
  public function getProductsImage( int|null $id = null)
  {
    if (is_null($this->setProductsImage($id))) {
      return false;
    } else {
      return $this->setProductsImage($id);
    }
}

  /**
   * Sets the medium image of a product.
   * @param int|null $id The ID of the product. If null, the current ID is used.
   * @return string $products_image_medium The medium-sized image of the product in a protected format.
   */
  private function setProductsImageMedium( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_image_medium'], $array);

    $products_image_medium = HTML::outputProtected($Qproducts->value('products_image_medium'));

    return $products_image_medium;
  }

  /**
   * Retrieves and sets the medium-sized product image.
   * @param int|null $id The ID of the product.
   * @return string|false Returns the medium-sized product image as a string or false on failure.
   */
  public function getProductsImageMedium( int|null $id = null)
  {
    if (is_null($this->setProductsImageMedium($id))) {
      return false;
    } else {
      return $this->setProductsImageMedium($id);
    }
}

  /**
   * Retrieve the availability date of a product
   * @param int|null $id The product ID. If null, the default ID will be used.
   * @return string $products_date_available The date when the product is available.
   */
  public function getProductsDateAvailable( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_date_available'], $array);

    $products_date_available = HTML::outputProtected($Qproducts->value('products_date_available'));

    return $products_date_available;
  }

  /**
   * Retrieve the EAN (European Article Number) of the product
   *
   * @return string $products_ean, the EAN of the product
   */
  public function getProductsEAN(): string
  {
    $array = [
      'products_status' => 1,
      'products_id' => (int)$this->getID()
    ];

    $Qproducts = $this->db->get('products', ['products_ean'], $array);

    $products_ean = HTML::outputProtected($Qproducts->value('products_ean'));

    return $products_ean;
  }

  /**
   * Get the SKU of a product.
   * @param int|null $id Product ID. If null, the method fetches the default ID.
   * @return string $products_sku The sanitized SKU of the product.
   */
  public function getProductsSKU( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_sku'], $array);

    $products_sku = HTML::outputProtected($Qproducts->value('products_sku'));

    return $products_sku;
  }

  /**
   * Retrieve the JAN code of a product
   * @param int|null $id The ID of the product. If null, the method may utilize a default ID.
   * @return string $products_jan The JAN code of the specified product
   */
  public function getProductsJAN( int|null $id = null): string
  {
    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_jan'], $array);

    $products_jan = HTML::outputProtected($Qproducts->value('products_jan'));

    return $products_jan;
  }

  /**
   * Retrieve the ISBN of a product.
   * @param int|null $id The product ID. If null, the current product ID is used.
   * @return string The ISBN of the product, sanitized for output.
   */
  public function getProductsISBN( int|null $id = null): string
  {
    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_isbn'], $array);

    $products_isbn = HTML::outputProtected($Qproducts->value('products_isbn'));

    return $products_isbn;
  }

  /**
   * Get the Manufacturer Part Number (MPN) of a product.
   *
   * @param int|null $id The ID of the product. If null, default behavior is applied.
   * @return string $products_mpn The sanitized MPN of the product.
   */
  public function getProductsMNP( int|null $id = null): string
  {
    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_mpn'], $array);

    $products_mpn = HTML::outputProtected($Qproducts->value('products_mpn'));

    return $products_mpn;
  }

  /**
   * Retrieve the UPC code of a product
   * @param int|null $id Product ID
   * @return string $products_upc The UPC code of the product
   */
  public function getProductsUPC( int|null $id = null): string
  {
    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_upc'], $array);

    $products_upc = HTML::outputProtected($Qproducts->value('products_upc'));

    return $products_upc;
  }

  /**
   * Retrieve the barcode of the product
   * @return string $products_barcode, barcode of the product
   */
  public function getProductsBarCode(): string
  {
    $array = [
      'products_status' => 1,
      'products_id' => (int)$this->getID()
    ];

    $Qproducts = $this->db->get('products', ['products_barcode'], $array);

    $products_barcode = HTML::outputProtected($Qproducts->value('products_barcode'));

    return $products_barcode;
  }

  /**
   * Retrieves the description of a product.
   *
   * @param int|null $id The ID of the product. If null, the method will use the default ID retrieved internally.
   * @return string The description of the specified product.
   */
  public function getProductsDescription( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $Qproducts = $this->db->prepare('select pd.products_description
                                        from :table_products p,
                                             :table_products_description pd
                                        where p.products_status = 1
                                        and p.products_id = :products_id
                                        and pd.products_id = p.products_id
                                        and pd.language_id = :language_id
                                       ');

    $Qproducts->bindInt(':products_id', $id);
    $Qproducts->bindInt(':language_id', $this->language->getId());

    $Qproducts->execute();

    return $Qproducts->value('products_description');
  }

  /**
   * Retrieves a short description of a product.
   *
   * @param int|null $id The product ID. If null, the method will use the default ID.
   * @param int $delete_word The number of characters to skip at the beginning of the description.
   * @param int $products_short_description_number The maximum length of the short description to retrieve.
   * @return string The processed short description of the product.
   */
  public function getProductsShortDescription( int|null $id = null, int $delete_word = 0, int $products_short_description_number = 0): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $delete_word = HTML::sanitize($delete_word);
    $products_short_description_number = HTML::sanitize($products_short_description_number);

    $Qproducts = $this->db->prepare('select pd.products_description_summary
                                        from :table_products p,
                                             :table_products_description pd
                                        where p.products_status = 1
                                        and p.products_id = :products_id
                                        and pd.products_id = p.products_id
                                        and pd.language_id = :language_id
                                       ');

    $Qproducts->bindInt(':products_id', $id);
    $Qproducts->bindInt(':language_id', $this->language->getId());

    $Qproducts->execute();

    $description_summary = $Qproducts->value('products_description_summary');

    if ($products_short_description_number > 0) {
      $short_description = substr($description_summary, (int)$delete_word, (int)$products_short_description_number);
      $description_summary = HTML::breakString(HTML::outputProtected($short_description), $products_short_description_number, '-<br />') . ((strlen($description_summary) >= $products_short_description_number - 1) ? ' ...' : '');
    } else {
      $description_summary = '';
    }

    return $description_summary;
  }

  /**
   * Retrieve the dimensions of a product.
   *
   * @param int|null $id The ID of the product. If null, it will use the current product ID.
   * @param string $separator The separator used to format the dimensions, default is ' x '.
   * @return string The formatted dimensions of the product including their unit type, or false if dimensions are not available.
   */
  public function getProductsDimension( int|null $id = null, string $separator = ' x '): string
  {
    $CLICSHOPPING_ProductsLength = Registry::get('ProductsLength');

    if (is_null($id)) {
      $id = $this->getID();
    }

    $Qproducts = $this->db->get('products', [
      'products_dimension_width',
      'products_dimension_height',
      'products_dimension_depth',
      'products_weight_class_id'
    ],
      ['products_status' => 1,
        'products_id' => (int)$id
      ]
    );

    $products_length_class_id = $Qproducts->valueInt('products_weight_class_id');
    $products_dimension_width = $Qproducts->valueDecimal('products_dimension_width');
    $products_dimension_height = $Qproducts->valueDecimal('products_dimension_height');
    $products_dimension_depth = $Qproducts->valueDecimal('products_dimension_depth');

    $products_type = $CLICSHOPPING_ProductsLength->getUnit($products_length_class_id, $this->language->getId());

    if (($products_dimension_width == 0 || empty($products_dimension_width)) ||
      ($products_dimension_height == 0 || empty($products_dimension_height)) ||
      ($products_dimension_depth == 0 || empty($products_dimension_depth))
    ) {
      return false;
    } else {
      $products_dimension = HTML::outputProtected($products_dimension_width . $separator . $products_dimension_height . $separator . $products_dimension_depth . ' ' . $products_type);
    }

    return $products_dimension;
  }

  /**
   * Retrieve the manufacturer's name for a given product.
   * @param int|null $id The ID of the product. If null, defaults to the current product's ID.
   * @return string The manufacturer's name associated with the product.
   */
  public function getProductsManufacturer( int|null $id = null)
  {
    $manufacturer_search = '';

    if (is_null($id)) {
      $id = $this->getID();
    }

    $Qproducts = $this->db->prepare('select manufacturers_id
                                        from :table_products
                                        where  products_id = :products_id
                                        limit 1
                                       ');

    $Qproducts->bindInt(':products_id', $id);
    $Qproducts->execute();

    $Qmanufacturer = $this->db->prepare('select m.manufacturers_name
                                          from :table_manufacturers m,
                                               :table_products p
                                          where m.manufacturers_id = :manufacturers_id
                                          and p.products_id = :products_id
                                        ');
    $Qmanufacturer->bindInt(':manufacturers_id', $Qproducts->valueInt('manufacturers_id'));
    $Qmanufacturer->bindInt(':products_id', $id);

    $Qmanufacturer->execute();

    if ($Qmanufacturer->fetch()) {
      $manufacturer_search = $Qmanufacturer->value('manufacturers_name');
    }

    return $manufacturer_search;
  }

  /**
   * Sanitizes and returns the size button value.
   *
   * @param mixed $size_button The size button value to be sanitized.
   * @return string The sanitized size button value.
   */
  public function getSizeButton($size_button): string
  {
    $size_button = HTML::sanitize($size_button);

    return $size_button;
  }

  /**
   * Generate and return a "new arrival" button for products based on their added date.
   *
   * @param int|null $id The product ID. If null, the method retrieves the ID internally.
   * @param mixed|null $size_button Optional size parameter for styling the button.
   * @return string The HTML button markup for new arrival products or an empty string if the product is not a new arrival.
   */
  public function getProductsNewArrival( int|null $id = null, $size_button = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $size_button = $this->getSizeButton($size_button);

    $Qproducts = $this->db->get('products', ['products_date_added'],
      ['products_status' => 1,
        'products_id' => (int)$id
      ]
    );

    $products_date_added = $Qproducts->value('products_date_added');

    if (!empty($size_button)) {
      $size_button = $this->getSizeButton($size_button);
    }

//  2592000 = 30 days in the unix timestamp format
    $day_new_products = 86400 * (\defined('DAY_NEW_PRODUCTS_ARRIVAL') ? (int)DAY_NEW_PRODUCTS_ARRIVAL : 0);
    $today_time = time();

    if (($today_time - strtotime($products_date_added)) < $day_new_products) {
      $product_button_new_arrival = HTML::button(CLICSHOPPING::getDef('button_new_product'), null, null, 'new', null, $size_button);

      $icon_new_arrival_products = '&nbsp' . $product_button_new_arrival;

      return $icon_new_arrival_products;
    } else {
      return '';
    }
}

  /**
   * Retrieves the information about whether the product is available only in the shop.
   *
   * @param int|null $id The ID of the product to check. If null, the method will retrieve the ID internally.
   * @return string Returns the value indicating if the product is available only in the shop.
   */
  public function getProductsOnlyTheShop( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_only_shop'], $array);

    $products_only_shop = $Qproducts->value('products_only_shop');

    return $products_only_shop;
  }

  /**
   * Retrieves the "products_only_online" status for a specific product from the database.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return string The "products_only_online" value for the product.
   */
  public function getProductsOnlyOnTheWebSite( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_only_online'], $array);

    $products_only_online = $Qproducts->value('products_only_online');

    return $products_only_online;
  }

  /**
   * Retrieves the packaging details of a product based on its ID.
   *
   * @param int|null $id The ID of the product. If null, the method will use a default ID.
   * @return string The packaging details of the specified product.
   */
  public function getProductsPackaging( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_packaging'], $array);

    $products_packaging = HTML::outputProtected($Qproducts->value('products_packaging'));

    return $products_packaging;
  }

  /**
   * Retrieves the date a product was added to the database.
   *
   * @param int|null $id The ID of the product. Defaults to null. If null, the method retrieves the ID using the getID() method.
   * @return string The date the product was added, as a string.
   */
  public function getProductsDateAdded( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $Qproducts = $this->db->prepare('select products_date_added
                                       from :table_products
                                       where products_status = 1
                                       and products_id = :products_id
                                       order by products_last_modified desc
                                       limit 1
                                     ');
    $Qproducts->bindInt(':products_id', $id);

    $Qproducts->execute();

    return $Qproducts->value('products_date_added');
  }

  /**
   * Retrieves the quantity unit type title for a specific product from the database.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return string The quantity unit type title for the product.
   */
  public function getProductQuantityUnitType( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $language_id = $this->language->getId();

    $QproductsUnitType = $this->db->prepare('select pq.products_quantity_unit_title
                                              from :table_products p,
                                                   :table_products_quantity_unit pq
                                              where pq.products_quantity_unit_id = p.products_quantity_unit_id
                                              and p.products_id = :products_id
                                              and language_id = :language_id
                                              and p.products_status = 1
                                             ');
    $QproductsUnitType->bindInt(':products_id', $id);
    $QproductsUnitType->bindInt(':language_id', (int)$language_id);

    $QproductsUnitType->execute();

    return $QproductsUnitType->value('products_quantity_unit_title');
  }

  /**
   * Retrieves the shipping delay information for a specific product from the database.
   *
   * @return string The shipping delay value for the product. If the shipping delay is not set, a default protected value is returned.
   */
  public function getProductsShippingDelay(): string
  {
    $language_id = $this->language->getId();

    $Qproducts = $this->db->prepare('select pd.products_shipping_delay
                                        from :table_products p,
                                             :table_products_description pd
                                        where p.products_status = 1
                                        and p.products_id = :products_id
                                        and pd.products_id = p.products_id
                                        and pd.language_id = :language_id
                                       ');

    $Qproducts->bindInt(':products_id', $this->getID());
    $Qproducts->bindInt(':language_id', (int)$language_id);
    $Qproducts->execute();

    $products_shipping_delay = $Qproducts->value('products_shipping_delay');

    if (empty($products['products_shipping_delay'])) {
      $products_shipping_delay = HTML::outputProtected(\defined('DISPLAY_SHIPPING_DELAY') ? DISPLAY_SHIPPING_DELAY : '');
    }

    return $products_shipping_delay;
  }

  /**
   * Retrieves the "products_shipping_delay_out_of_stock" value for a specific product from the database.
   * If the value is empty, a default value is returned.
   *
   * @return string The shipping delay information for out-of-stock products.
   */
  public function getProductsShippingDelayOutOfStock(): string
  {
    $language_id = $this->language->getId();

    $Qproducts = $this->db->prepare('select pd.products_shipping_delay_out_of_stock
                                        from :table_products p,
                                             :table_products_description pd
                                        where p.products_status = 1
                                        and p.products_id = :products_id
                                        and pd.products_id = p.products_id
                                        and pd.language_id = :language_id
                                       ');

    $Qproducts->bindInt(':products_id', $this->getID());
    $Qproducts->bindInt(':language_id', (int)$language_id);
    $Qproducts->execute();

    $products_shipping_delay_out_of_stock = $Qproducts->value('products_shipping_delay_out_of_stock');

    if (empty($products['products_shipping_delay_out_of_stock'])) {
      $products_shipping_delay_out_of_stock = HTML::outputProtected(\defined('DISPLAY_SHIPPING_DELAY_OUT_OF_STOCK') ? DISPLAY_SHIPPING_DELAY_OUT_OF_STOCK : '');
    }

    return $products_shipping_delay_out_of_stock;
  }

  /**
   * Retrieves the "products_head_tag" value for a specific product and language from the database.
   *
   * @return string The sanitized "products_head_tag" value for the specified product.
   */
  public function getProductsHeadTag(): string
  {
    $language_id = $this->language->getId();

    $Qproducts = $this->db->prepare('select pd.products_head_tag
                                      from :table_products p,
                                           :table_products_description pd
                                      where p.products_status = 1
                                      and p.products_id = :products_id
                                      and pd.products_id = p.products_id
                                      and pd.language_id = :language_id
                                     ');

    $Qproducts->bindInt(':products_id', $this->getID());
    $Qproducts->bindInt(':language_id', (int)$language_id);

    $Qproducts->execute();

    $products_head_tag = HTML::outputProtected($Qproducts->value('products_head_tag'));

    return $products_head_tag;
  }

  /**
   * Retrieves the product URL for the manufacturer in the current language from the database.
   *
   * @return string The URL of the product for the manufacturer in the specified language.
   */
  public function getProductsURLManufacturer(): string
  {
    $language_id = $this->language->getId();

    $Qproducts = $this->db->prepare('select pd.products_url
                                      from :table_products p,
                                           :table_products_description pd
                                      where p.products_status = 1
                                      and p.products_id = :products_id
                                      and pd.products_id = p.products_id
                                      and pd.language_id = :language_id
                                     ');

    $Qproducts->bindInt(':products_id', $this->getID());
    $Qproducts->bindInt(':language_id', (int)$language_id);

    $Qproducts->execute();

    return $Qproducts->value('products_url');
  }

  /**
   * Retrieves the status of a product indicating if it is available both in-store and online.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return string An empty string if the product is available both in-store and online.
   */
  public function getProductsWebAndShop( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_only_shop', 'products_only_online'], $array);

    if ($Qproducts->value('products_only_shop') != 1 && $Qproducts->value('products_only_online') != 1) {
      $products_web = '';
    }

    return $products_web;
  }

  /**
   * Retrieves the "products_volume" value for a specific product from the database.
   *
   * @return string The sanitized "products_volume" value for the product.
   */
  public function getProductsVolume(): string
  {
    $array = [
      'products_status' => 1,
      'products_id' => (int)$this->getID()
    ];

    $Qproducts = $this->db->get('products', ['products_volume'], $array);

    $products_volume = HTML::outputProtected($Qproducts->value('products_volume'));

    return $products_volume;
  }

  /**
   * Sets the weight of a specific product after retrieving and converting it from the database.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return string The converted weight of the product. Returns an empty string if the weight is zero.
   */
  private function setProductsWeight( int|null $id = null)
  {
    $CLICSHOPPING_Weight = Registry::get('Weight');

    if (is_null($id)) {
      $id = $this->getID();
    }

    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_weight', 'products_weight_class_id'], $array);

    $products_weight = $Qproducts->value('products_weight');
    $products_weight_class_id = $Qproducts->value('products_weight_class_id');

    $products_weight = $CLICSHOPPING_Weight->convert($products_weight, $products_weight_class_id, \defined('SHIPPING_WEIGHT_UNIT') ? SHIPPING_WEIGHT_UNIT : '');

    if ($products_weight == '0.00') {
      $products_weight = '';
    }

    return $products_weight;
  }

  /**
   * Retrieves the weight of a specific product.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return mixed The weight of the product as determined by the setProductsWeight method.
   */
  public function getProductsWeight( int|null $id = null)
  {
    return $this->setProductsWeight($id);
  }

  /**
   * Sets and calculates the product price per weight unit based on various conditions, such as customer group,
   * tax rates, product weight, and system configuration settings.
   *
   * @param string|null $id The ID of the product. If null, the method will use the current product ID.
   * @return string The calculated product price per weight unit, formatted for display. Returns an empty string if the price should not be displayed based on the system's conditions.
   */
  private function setProductsPriceByWeight(?string $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_Tax = Registry::get('Tax');

    $array = [
      'products_status' => 1,
      'products_id' => (int)$id
    ];

    $Qproducts = $this->db->get('products', ['products_price', 'products_price_kilo'], $array);

    if ($this->customer->getCustomersGroupID() != 0) {
      $products_price = $this->getCustomersGroupPrice();
    } else {
      $products_price = $Qproducts->value('products_price');
    }

    $products_price_kilo = $Qproducts->value('products_price_kilo');
    $products_weight = $this->setProductsWeight();

    if ($products_price_kilo == 1 && $products_weight != '') {
      $product_price_kilo_display = $CLICSHOPPING_Currencies->displayPrice(round($products_price / $products_weight, 2), $CLICSHOPPING_Tax->getTaxRate($this->getProductsTaxClassId()));
    } else {
      $product_price_kilo_display = '';
    }

    if ((\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'true') && !$this->customer->isLoggedOn()) {
      $product_price_kilo_display = '';
    }

    if (\defined('NOT_DISPLAY_PRICE_ZERO') && NOT_DISPLAY_PRICE_ZERO == 'false' && $products_price == 0) {
      $product_price_kilo_display = '';
    }

    if ($this->getPriceGroupView() == 0 && $this->customer->getCustomersGroupID() != 0) {
      $product_price_kilo_display = '';
    }

    return $product_price_kilo_display;
  }

  /**
   * Retrieves the price of a product based on its weight.
   *
   * @param string|null $id The ID of the product. If null, a default ID may be used.
   * @return mixed The price of the product calculated by weight.
   */
  public function getProductsPriceByWeight(?string $id = null):mixed
  {
    return $this->setProductsPriceByWeight($id);
  }

  /**
   * Retrieves the "products_quantity_unit_title" for a specific product from the database.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return string The "products_quantity_unit_title" value for the product.
   */
  public function getProductsQuantityByUnit(mixed $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $language_id = $this->language->getId();

    $QproductsQuantityUnit = $this->db->prepare('select pq.products_quantity_unit_title
                                                    from :table_products_quantity_unit pq,
                                                         :table_products p
                                                    where pq.products_quantity_unit_id = p.products_quantity_unit_id
                                                    and p.products_id = :products_id
                                                    and language_id = :language_id
                                                    ');
    $QproductsQuantityUnit->bindInt(':language_id', (int)$language_id);
    $QproductsQuantityUnit->bindInt(':products_id', $id);
    $QproductsQuantityUnit->execute();

    return $QproductsQuantityUnit->value('products_quantity_unit_title');
  }

// ---------------------------------------------------------------------------------------------------------------------------------------
// B2B
// ---------------------------------------------------------------------------------------------------------------------------------------

  /**
   * Sets and retrieves the product model for a specific product based on the customer's group ID and product ID.
   *
   * @param int|null $id The ID of the product. If null, the current ID will be used.
   * @return string The product model, either specific to the customer's group or a default model.
   */

  private function setProductsModel( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    if ($this->customer->getCustomersGroupID() != 0) {
      $Qproducts = $this->db->prepare('select g.products_model_group
                                          from :table_products p left join :table_products_groups g on p.products_id = g.products_id
                                          where p.products_status = 1
                                          and g.customers_group_id = :customers_group_id
                                          and g.products_group_view = 1
                                          and p.products_id = :products_id
                                         ');

      $Qproducts->bindInt(':products_id', $id);
      $Qproducts->bindInt(':customers_group_id', (int)$this->customer->getCustomersGroupID());

      $Qproducts->execute();
    } else {
      $Qproducts = $this->db->prepare('select products_model
                                        from :table_products
                                        where products_status = 1
                                        and products_view = 1
                                        and products_id = :products_id
                                       ');
      $Qproducts->bindInt(':products_id', $id);

      $Qproducts->execute();
    }

// display the good producs_model
    if ($this->customer->getCustomersGroupID() != 0 && !is_null($Qproducts->value('products_model_group'))) {
      $products_model = HTML::outputProtected($Qproducts->value('products_model_group'));

      if (is_null($Qproducts->value('products_model_group'))) {
        $products_model = HTML::outputProtected($Qproducts->value('products_model'));
      }
} else {
      $products_model = HTML::outputProtected($Qproducts->value('products_model'));
    }

    return $products_model;
  }

  /**
   * Retrieves the product model for a specific product.
   *
   * @param int|null $id The ID of the product. If null, the current ID will be used.
   * @return string The product model associated with the specified product.
   */
  public function getProductsModel( int|null $id = null): string
  {
    return $this->setProductsModel($id);
  }

  /**
   * Sets the flash discount for a specific product based on the customer's group and current time.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return string The flash discount duration for the product in a formatted string if conditions are met, otherwise an empty string.
   */
  private function setProductsFlashDiscount( int|null $id = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $date_format = 'Y-m-d H:i:s';

    if ($this->customer->getCustomersGroupID() != 0) {
      $QflashDiscount = $this->db->prepare('select specials_id,
                                                      products_id,
                                                      scheduled_date,
                                                      expires_date
                                                from :table_specials
                                                where products_id = :products_id
                                                and customers_group_id > 0
                                                and status = 1
                                                and flash_discount = 1
                                                and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                              ');
      $QflashDiscount->bindInt(':products_id', $id);
      $QflashDiscount->bindInt(':customers_group_id', (int)$this->customer->getCustomersGroupID());

    } else {
      $QflashDiscount = $this->db->prepare('select specials_id,
                                                      products_id,
                                                      scheduled_date,
                                                      expires_date,
                                                      flash_discount
                                                from :table_specials
                                                where products_id = :products_id
                                                and (customers_group_id = 0 or customers_group_id = 99)
                                                and status = 1
                                                and flash_discount = 1
                                               ');
      $QflashDiscount->bindInt(':products_id', $id);
    }

    $QflashDiscount->execute();

    $products_flash_discount = '';

    if (DateTime::getNow($date_format) >= $QflashDiscount->value('scheduled_date') && DateTime::getNow($date_format) <= $QflashDiscount->value('expires_date')) {
      $month = DateTime::getIntervalDate(DateTime::getNow($date_format), $QflashDiscount->value('expires_date'), '%M');
      $days = DateTime::getIntervalDate(DateTime::getNow($date_format), $QflashDiscount->value('expires_date'), '%D');
      $hours = DateTime::getIntervalDate(DateTime::getNow($date_format), $QflashDiscount->value('expires_date'), '%H');
      $minutes = DateTime::getIntervalDate(DateTime::getNow($date_format), $QflashDiscount->value('expires_date'), '%i');
      $secondes = DateTime::getIntervalDate(DateTime::getNow($date_format), $QflashDiscount->value('expires_date'), '%S');

      if ($month > 1) {
        $products_flash_discount = $month . ' ' . CLICSHOPPING::getDef('month') . ' ' . $days . ' ' . CLICSHOPPING::getDef('days') . ' ' . $hours . ' ' . CLICSHOPPING::getDef('hours') . ' ' . $minutes . CLICSHOPPING::getDef('minutes') . ' ' . $secondes . ' ' . CLICSHOPPING::getDef('secondes');
      } elseif ($days > 1) {
        $products_flash_discount = $days . ' ' . CLICSHOPPING::getDef('days') . ' ' . $hours . ' ' . CLICSHOPPING::getDef('hours') . ' ' . $minutes . ' ' . CLICSHOPPING::getDef('minutes') . ' ' . $secondes . ' ' . CLICSHOPPING::getDef('secondes');
      } elseif ($hours > 1) {
        $products_flash_discount = $hours . ' ' . CLICSHOPPING::getDef('hours') . ' ' . $minutes . ' ' . CLICSHOPPING::getDef('minutes') . ' ' . $secondes . ' ' . CLICSHOPPING::getDef('secondes');
      } elseif ($minutes > 1) {
        $products_flash_discount = $minutes . ' ' . CLICSHOPPING::getDef('minutes') . ' ' . $secondes . ' ' . CLICSHOPPING::getDef('secondes');
      } else {
        $products_flash_discount = $secondes . ' ' . CLICSHOPPING::getDef('secondes');
      }

      $products_flash_discount = HTML::outputProtected($products_flash_discount);
    }

    return $products_flash_discount;
  }

  /**
   * Retrieves and sets the flash discount status for a specific product.
   *
   * @param int|null $id The ID of the product. If null, a default ID or logic will be applied.
   * @return mixed The result of the setProductsFlashDiscount method.
   */
  public function getProductsFlashDiscount( int|null $id = null)
  {
    return $this->setProductsFlashDiscount($id);
  }

//----------------------------------------------------------------------------------------------------------------------------
// Quantity
//----------------------------------------------------------------------------------------------------------------------------

  /**
   * Sets and retrieves the product quantity unit type and title based on the customer's group.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return string The formatted quantity unit title associated with the customer's group for the product.
   */

  private function setProductQuantityUnitTypeCustomersGroup( int|null $id = null)
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    if ($this->customer->getCustomersGroupID() != 0) {
      $QcustomerGroupPrice = $this->db->prepare('select products_quantity_unit_id_group,
                                                           products_quantity_fixed_group
                                                    from :table_products_groups
                                                    where products_id = :products_id
                                                    and customers_group_id = :customers_group_id
                                                   ');

      $QcustomerGroupPrice->bindInt(':customers_group_id', (int)$this->customer->getCustomersGroupID());
      $QcustomerGroupPrice->bindInt(':products_id', $id);
      $QcustomerGroupPrice->execute();

      $products_quantity_unit_id_group = $QcustomerGroupPrice->valueInt('products_quantity_unit_id_group');
      $products_quantity_fixed_group = $QcustomerGroupPrice->valueInt('products_quantity_fixed_group');

    } else {
      $products_quantity_unit_id_group = '';
      $products_quantity_fixed_group = '';
    }

    if ($this->customer->getCustomersGroupID() != 0) {

      $language_id = $this->language->getId();

      $QproductsQuantityUnit = $this->db->prepare('select products_quantity_unit_id,
                                                            language_id,
                                                            products_quantity_unit_title
                                                      from :table_products_quantity_unit
                                                      where products_quantity_unit_id = :products_quantity_unit_id
                                                      and language_id = :language_id
                                                    ');

      $QproductsQuantityUnit->bindInt(':products_quantity_unit_id', (int)$products_quantity_unit_id_group);
      $QproductsQuantityUnit->bindInt(':language_id', (int)$language_id);
      $QproductsQuantityUnit->execute();

      $products_quantity_unit = $QproductsQuantityUnit->fetch();

      $products_group_quantity_unit_title = '';

      if ($products_quantity_unit_id_group == 0) {
        $products_group_quantity_unit_title = '';
      } else {
        $products_group_quantity_unit_title = HTML::outputProtected($products_quantity_fixed_group) . ' ' . $products_quantity_unit['products_quantity_unit_title'];
      }
} else {
      $products_group_quantity_unit_title = '';
    }

    return $products_group_quantity_unit_title;
  }

  /**
   * Retrieves the product quantity unit type specific to a customer's group.
   *
   * @param mixed $id The ID of the product or null if the default behavior should be applied.
   * @return mixed The product quantity unit type for the customer's group.
   */
  public function getProductQuantityUnitTypeCustomersGroup($id = null)
  {
    return $this->setProductQuantityUnitTypeCustomersGroup($id);
  }

  /**
   * Sets the minimum quantity of a product that can be ordered based on the product's or customer's group configuration.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current product ID.
   * @return int The determined minimum quantity that can be ordered for the product.
   */
  private function setProductsMinimumQuantity($id = null)
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $customers_group_id = $this->customer->getCustomersGroupID();

    if ($customers_group_id == 0) {
      $QproductMinOrder = $this->db->get('products', ['products_min_qty_order'], ['products_id' => (int)$id]);

      if ($QproductMinOrder->valueInt('products_min_qty_order') > 0.1) {
        $min_quantity_order = $QproductMinOrder->valueInt('products_min_qty_order');
      } else {
        $min_quantity_order = \defined('MAX_MIN_IN_CART') ? (int)MAX_MIN_IN_CART : 0;
        if (\defined('MAX_MIN_IN_CART') && (int)MAX_MIN_IN_CART > \defined('MAX_QTY_IN_CART') ? (int)MAX_QTY_IN_CART : 0) {
          $min_quantity_order = \defined('MAX_QTY_IN_CART') ? (int)MAX_QTY_IN_CART : 0;
        }
}
    } else {
      $QcustomersGroupMinOrder = $this->db->get('customers_groups', ['customers_group_quantity_default'], ['customers_group_id' => (int)$customers_group_id]);

      $QcustomersProductsGroupMinOrder = $this->db->get('products_groups', ['products_quantity_fixed_group'], ['customers_group_id' => (int)$customers_group_id]);

      if ($QcustomersProductsGroupMinOrder->valueInt('products_quantity_fixed_group') > 1) {
        $min_quantity_order = $QcustomersProductsGroupMinOrder->valueInt('products_quantity_fixed_group');
      } elseif ($QcustomersGroupMinOrder->valueInt('customers_group_quantity_default') > 1) {
        $min_quantity_order = $QcustomersGroupMinOrder->valueInt('customers_group_quantity_default');
      } else {
        $min_quantity_order = 1;
      }
}

    return $min_quantity_order;
  }

  /**
   * Retrieves the minimum quantity for a specific product.
   *
   * @param int|null $id The ID of the product. If null, a default action or value will be used.
   * @return mixed The minimum quantity value for the product.
   */
  public function getProductsMinimumQuantity($id = null)
  {
    return $this->setProductsMinimumQuantity($id);
  }

  /*
    * Minimum quantity take an order for the client
    * @param string $min_order_quantity_products_display, the min of order product qty
    * @access private
  */
  /**
   * Sets the minimum quantity of a product required to place an order based on various conditions.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current product ID.
   * @return ?string|?int The minimum order quantity display value or an empty string if conditions are not met.
   */
  private function setProductsMinimumQuantityToTakeAnOrder($id = null)
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    if ($this->getProductsMinimumQuantity($id) >= 1) {
      if ($this->getOrdersGroupView() != 0 && $this->customer->getCustomersGroupID() != 0) {
        $min_order_quantity_products_display = $this->getProductsMinimumQuantity($id);
      } elseif ($this->getProductsOrdersView() != 0 && $this->customer->getCustomersGroupID() == 0) {
        $min_order_quantity_products_display = $this->getProductsMinimumQuantity($id);
      } else {
        $min_order_quantity_products_display = '';
      }
} else {
      $min_order_quantity_products_display = '';
    }

    if (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'true' && !$this->customer->isLoggedOn()) {
      $min_order_quantity_products_display = '';
    }

    return $min_order_quantity_products_display;
  }

  /*
    * display Minimum quantity take an order for the client
    * @param string $min_order_quantity_products_display, the min of order product qty
  */
  /**
   * Retrieves the minimum quantity required to place an order for a specific product.
   *
   * @param int|null $id The ID of the product. If null, the method will apply to the current product ID.
   * @return mixed The minimum quantity required to take an order for the given product.
   */
  public function getProductsMinimumQuantityToTakeAnOrder($id = null)
  {
    return $this->setProductsMinimumQuantityToTakeAnOrder($id);
  }

  /**
   * Generates an HTML input field for specifying product quantity based on various customer and product conditions.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current product ID.
   * @return string The generated HTML input field for specifying the product quantity, or an empty string if conditions are not met.
   */

  public function setProductsAllowingToInsertQuantity($id = null)
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    if ($this->customer->getCustomersGroupID() != 0 && $this->getOrdersGroupView() != 0) {
      $input_quantity = '<label for="Quantity' . $id . '" class="visually-hidden"></label>';
      $input_quantity .= HTML::inputField('cart_quantity', (int)$this->setProductsMinimumQuantityToTakeAnOrder($id), 'id="Quantity' . $id . '" placeholder="qty" class="input-small" maxlength="4" size="4" min="1"') . '&nbsp;&nbsp;';
    } else {
      $input_quantity = '';
    }

    if ($this->customer->getCustomersGroupID() == 0 && $this->getProductsOrdersView() != 0) {
      if (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'false') {
        $input_quantity = '<label for="Quantity' . $id . '" class="visually-hidden"></label>';
        $input_quantity .= HTML::inputField('cart_quantity', (int)$this->setProductsMinimumQuantityToTakeAnOrder($id), 'id="Quantity' . $id . '" placeholder="qty" class="input-small" maxlength="4" size="4" min="1"') . '&nbsp;&nbsp;';
      } elseif (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'true' && $this->customer->isLoggedOn()) {
        $input_quantity = '<label for="Quantity' . $id . '" class="visually-hidden"></label>';
        $input_quantity .= HTML::inputField('cart_quantity', (int)$this->setProductsMinimumQuantityToTakeAnOrder($id), 'id="Quantity' . $id . '" placeholder="qty" class="input-small" maxlength="4" size="4" min="1"') . '&nbsp;&nbsp;';
      } else {
        $input_quantity = '';
      }
}

    if ($this->setProductsMinimumQuantityToTakeAnOrder($id) == 0 && \defined('MAX_MIN_IN_CART') && MAX_MIN_IN_CART == 0) {
      $input_quantity = '';
    }

    if ($this->getPriceGroupView() == 0 && $this->customer->getCustomersGroupID() != 0) {
      $input_quantity = '';
    }

    return $input_quantity;
  }

  /**
   * Retrieves the products allowing to insert quantity.
   *
   * @param mixed $id The identifier of the product. If null, the method will handle it accordingly.
   * @return mixed The result of setting products that allow quantity insertion.
   */
  public function getProductsAllowingToInsertQuantity($id = null)
  {
    return $this->setProductsAllowingToInsertQuantity($id);
  }

// ------------------------------------------------------------------------------------------------------
// Message && button
// ------------------------------------------------------------------------------------------------------

  /**
   * Generates a message indicating whether ordering is allowed based on customer group and product order view settings.
   *
   * @return string A formatted message indicating whether the customer can place an order, or an empty string if no restrictions apply.
   */
  public function getProductsAllowingTakeAnOrderMessage(): string
  {
    $submit_button_view = '';

    if (($this->customer->getCustomersGroupID() != 0) && ($this->getOrdersGroupView() != 1)) {
      $submit_button_view = '<div class="submitButtonView">' . CLICSHOPPING::getDef('no_orders_group') . '</div>';
    } elseif (($this->customer->getCustomersGroupID() == 0) && ($this->getProductsOrdersView() != 1)) {
      $submit_button_view = '<div class="submitButtonView">' . CLICSHOPPING::getDef('no_orders_public') . '</div>';
    }

    return $submit_button_view;
  }

  /**
   * Sets and retrieves the buy button.
   *
   * @param mixed $button The buy button to be set and returned.
   * @return mixed The specified buy button.
   */
  public function getBuyButton(mixed $button): mixed
  {
    $this->button = $button;

    return $button;
  }

  /**
   * Determines and returns the appropriate "buy button" status for a product
   * based on customer login state, customer group, and product/view settings.
   *
   * @return string The finalized "buy button" status for the product.
   */

  public function setProductsBuyButton(): string
  {
    $buy_button = $this->button;

    if ((\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'true' && !$this->customer->isLoggedOn())) {
      $buy_button = '';
    } elseif ($this->getProductsOrdersView() == 0 && $this->customer->getCustomersGroupID() == 0) {
      $buy_button = '';
    }

    if (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'true' && $this->customer->isLoggedOn()) {
      if ($this->getProductsOrdersView() == 0 && $this->customer->getCustomersGroupID() == 0) {
        $buy_button = '';
      } elseif ($this->getOrdersGroupView() == 0 && $this->customer->getCustomersGroupID() != 0) {
        $buy_button = '';
      }
} elseif (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'false' && $this->customer->isLoggedOn()) {
      if ($this->getProductsOrdersView() == 0 && $this->customer->getCustomersGroupID() == 0) {
        $buy_button = '';
      } elseif ($this->getOrdersGroupView() == 0 && $this->customer->getCustomersGroupID() != 0) {
        $buy_button = '';
      }
}

    if ($this->getPriceGroupView() == 0 && $this->customer->getCustomersGroupID() != 0) {
      $buy_button = '';
    }

    return $buy_button;
  }

  /**
   * Retrieves the buy button configuration for a product.
   *
   * @return string The buy button value for the product.
   */
  public function getProductsBuyButton(): string
  {
    return $this->setProductsBuyButton();
  }

  /**
   * Generates the HTML for a "sold out" button based on the specified or default button type.
   *
   * @param string|null $button_type Optional CSS classes for the button. Defaults to 'btn-warning btn-sm' if not provided.
   * @return string The HTML string for the "sold out" button. Returns an empty string if "PRE_ORDER_AUTORISATION" is set to 'false'.
   */
  private function getProductButtonSoldOut($button_type = null): string
  {
    $product_button_sold_out = '';

    if (is_null($button_type)) {
      $button_type = 'btn-warning btn-sm';
    }

    if (\defined('PRE_ORDER_AUTORISATION') && PRE_ORDER_AUTORISATION == 'false') {
      $product_button_sold_out = '<button type="button" class="btn ' . $button_type . '">' . CLICSHOPPING::getDef('button_sold_out') . '</button>';
    }

    return $product_button_sold_out;
  }

  /**
   * Determines the "sold out" status of a product based on stock level and configuration settings.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @param string|null $button_type Optional button type to be used when generating the "sold out" display.
   * @return string The appropriate "sold out" representation or an empty string if no action is required.
   */

  private function setProductsSoldOut($id, $button_type = null): string
  {
    if (is_null($id)) {
      $id = $this->getID();
    }
    $product_sold_out = '';

    $QproductSoldOut = $this->db->prepare('select products_quantity
                                              from :table_products
                                              where products_id = :products_id
                                              and products_quantity < 1
                                             ');

    $QproductSoldOut->bindInt(':products_id', $id);
    $QproductSoldOut->execute();

    if ($QproductSoldOut->fetch()) {
      if (\defined('STOCK_CHECK') && STOCK_CHECK == 'true' && \defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false' && \defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'false') {
        $product_sold_out = $this->getProductButtonSoldOut($button_type);
      } elseif (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'true' && $this->customer->getCustomersGroupID() == 0 && !$this->customer->isLoggedOn() && \defined('STOCK_CHECK') && STOCK_CHECK == 'true' && \defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false') {
        $product_sold_out = ' ';
      } elseif (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'true' && $this->customer->getCustomersGroupID() != 0 && \defined('STOCK_CHECK') && STOCK_CHECK == 'true' && \defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false') {
        $product_sold_out = $this->getProductButtonSoldOut($button_type);
      } elseif (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'true' && $this->customer->getCustomersGroupID() == 0 && $this->customer->isLoggedOn() && \defined('STOCK_CHECK') && STOCK_CHECK == 'true' && \defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false') {
        $product_sold_out = $this->getProductButtonSoldOut($button_type);
      }
}

    return $product_sold_out;
  }

  /**
   * Retrieves the status of products that are sold out, based on the provided product ID and button type.
   *
   * @param int|null $id The ID of the product. If null, the method may handle this internally.
   * @param string|null $button_type The type of button related to the product. If null, a default type may be handled.
   * @return string The status or information related to products that are sold out.
   */
  public function getProductsSoldOut($id = null, $button_type = null): string
  {
    return $this->setProductsSoldOut($id, $button_type);
  }

// =======================================================================================================================================================
// Price & tax
//=======================================================================================================================================================

  /**
   * Sets the customer's price for a specific product based on various conditions.
   *
   * @param int|null $id The ID of the product. If null, a default process might be used to calculate the price.
   * @return string The calculated or assigned product price for the customer.
   */

  private function setCustomersPrice($id = null)
  {

    if (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'false') {
      $product_price = $this->setCalculPrice($id);
    }

    if (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'true' && (!$this->customer->isLoggedOn())) {
      $product_price = HTML::link(CLICSHOPPING::link(null, 'Account&LogIn'), CLICSHOPPING::getDef('prices_logged_in_text')) . '&nbsp;';
    } else {
      $product_price = $this->setCalculPrice($id);
    }

    if ($this->getPriceGroupView() == 0 && $this->customer->getCustomersGroupID() != 0) {
      $product_price = '';
    }

    return $product_price;
  }

  /**
   * Retrieves the price for a specific customer.
   *
   * @param int|null $id The ID of the customer. If null, a default or current ID may be used.
   * @return mixed The price associated with the customer.
   */
  public function getCustomersPrice($id = null)
  {
    return $this->setCustomersPrice($id);
  }

  /**
   * Sets the special price group for a specific product based on the customer's group ID.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current product ID.
   * @return float The special price for the product based on the customer's group ID.
   */

  private function setSpecialPriceGroup($id = null)
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    if ($this->customer->getCustomersGroupID() != 0) {
      $Qproducts = $this->db->prepare('select distinct specials_new_products_price
                                         from :table_specials
                                         where products_id = :products_id
                                         and status = 1
                                         and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                        ');
      $Qproducts->bindInt(':products_id', $id);
      $Qproducts->bindInt(':customers_group_id', (int)$this->customer->getCustomersGroupID());

    } else {
      $Qproducts = $this->db->prepare('select distinct specials_new_products_price
                                         from :table_specials
                                         where products_id = :products_id
                                         and status = 1
                                         and (customers_group_id = 0 or customers_group_id = 99)
                                        ');

      $Qproducts->bindInt(':products_id', $id);
    }

    $Qproducts->execute();

    $specials_price = $Qproducts->valueDecimal('specials_new_products_price');

    //Si DynamicPricing Rules special option ok
    $specials_price = $this->getDynamicPricing($id, $specials_price, $this->customer->getCustomersGroupID());

    return $specials_price;

  }

  /**
   * Sets and retrieves the price for a specific product from the database, considering customer group pricing if applicable.
   * Now includes dynamic pricing rules.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return float The price of the product after considering customer group pricing and dynamic rules.
   */
  private function setPrice($id = null)
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $Qproduct = $this->db->prepare('select products_id,
                                             products_price
                                       from :table_products
                                       where products_status = 1
                                       and products_id = :products_id
                                       and products_archive = 0
                                     ');
    $Qproduct->bindInt(':products_id', $id);
    $Qproduct->execute();

    // Apply customer group pricing if applicable
    if ($this->customer->getCustomersGroupID() != 0) {
      $QcustomerGroupPrice = $this->db->prepare('select customers_group_price,
                                                          price_group_view
                                                  from :table_products_groups
                                                  where products_id = :products_id
                                                  and customers_group_id =  :customers_group_id
                                                  ');
      $QcustomerGroupPrice->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());
      $QcustomerGroupPrice->bindInt(':products_id', $Qproduct->valueInt('products_id'));
      $QcustomerGroupPrice->execute();

      if ($QcustomerGroupPrice->fetch()) {
        if ($QcustomerGroupPrice->valueInt('price_group_view') == 1) {
          $products_price = $QcustomerGroupPrice->valueDecimal('customers_group_price');
        } else {
          $products_price = $Qproduct->valueDecimal('products_price');
        }
} else {
        $products_price = $Qproduct->valueDecimal('products_price');
      }
} else {
      $products_price = $Qproduct->valueDecimal('products_price');
    }

    //dynamic pricing rules
    $products_price = $this->getDynamicPricing($id, $products_price, $this->customer->getCustomersGroupID());

    return $products_price;
  }

  /**
   * Sets and formats the display price for a specific product.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current product ID.
   * @return string The formatted product price including tax rates.
   */
  public function setDisplayPriceGroup($id = null)
  {
    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_Tax = Registry::get('Tax');

    if (is_null($id)) {
      $id = $this->getID();
    }

    $products_price = $CLICSHOPPING_Currencies->displayPrice($this->setPrice($id), $CLICSHOPPING_Tax->getTaxRate($this->getProductsTaxClassId()));

    return $products_price;
  }

  /**
   * Retrieves the display price for a specific product group without including currency conversions.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return float The price of the product group without currency adjustments.
   */
  public function getDisplayPriceGroupWithoutCurrencies(?int $id = null): float
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $products_price = $this->setPrice($id);

    return $products_price;
  }

  /**
   * Calculates and formats the price for a product based on group settings, special prices, dynamic pricing, and tax rates.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return string The formatted price for the product, including all pricing rules.
   */
  private function setCalculPrice(?int $id = null)
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_Tax = Registry::get('Tax');

    $normal_price = 0;

    if ($this->customer->getCustomersGroupID() != 0) {
      $Qproducts = $this->db->prepare('select g.price_group_view
                                          from :table_products p left join :table_products_groups g on p.products_id = g.products_id
                                          where p.products_status = 1
                                          and g.customers_group_id = :customers_group_id
                                          and g.products_group_view = 1
                                          and p.products_id = :products_id
                                          order by p.products_date_added DESC
                                         ');

      $Qproducts->bindInt(':products_id', $id);
      $Qproducts->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

      $Qproducts->execute();

      $price_group_view = $Qproducts->valueInt('price_group_view');

      // Check for special price first, then apply dynamic pricing
      if ($new_price = $this->setSpecialPriceGroup($id)) {
        if ($price_group_view == 1) {
          $products_price = '<span class="normalPrice"><del>' . $this->setDisplayPriceGroup($id) . '</del></span><span class="specialPrice">' . $CLICSHOPPING_Currencies->displayPrice($new_price, $CLICSHOPPING_Tax->getTaxRate($this->getProductsTaxClassId())) . '</span>';
        } else {
          $products_price = $this->setDisplayPriceGroup($id);
        }
} else {
        $products_price = $this->setDisplayPriceGroup($id);
      }
} else {
      $normal_price = 1; // Arret du mode Grand public pour refus d'afficher le prix groupe B2B
    }

    if (($this->customer->getCustomersGroupID() == 0) || ($normal_price == 1)) {
      // Check for special price first, then apply dynamic pricing
      if ($new_price = $this->setSpecialPriceGroup($id)) {
        $products_price = '<span class="normalPrice"><del>' . $this->setDisplayPriceGroup($id) . '</del></span><span class="specialPrice">' . $CLICSHOPPING_Currencies->displayPrice($new_price, $CLICSHOPPING_Tax->getTaxRate($this->getProductsTaxClassId())) . '</span>';
      } else {
        $products_price = $this->setDisplayPriceGroup($id);
      }
}

    return $products_price;
  }

  /**
   * Retrieves the calculated price for a specific item or entity.
   *
   * @param mixed|null $id The ID of the entity for which the calculated price is required. If null, a default value may be used.
   * @return mixed The calculated price value.
   */
  public function getCalculPrice(?int $id = null)
  {
    return $this->setCalculPrice($id);
  }

  /**
   * Retrieves the stock quantity of a specific product from the database.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return int The stock quantity of the product.
   */

  public function getProductsStock(int|string|null $id = null): int
  {
    $CLICSHOPPING_Prod = Registry::get('Prod');

    if (is_null($id)) {
      $id = $CLICSHOPPING_Prod::getProductID($this->getID());
    } else {
      $id = $CLICSHOPPING_Prod::getProductID($id);
    }

    $Qproduct = $this->db->get('products', ['products_quantity'], ['products_id' => (int)$id]);

    return $Qproduct->valueInt('products_quantity');
  }

  /**
   * Checks the stock availability for a product and returns the appropriate out-of-stock message.
   *
   * @param int|string $id The ID of the product.
   * @param int $products_quantity The quantity of the product to be checked against the stock.
   * @return string The out-of-stock message if the stock is insufficient; returns an empty string if stock is sufficient.
   */

  public function getCheckStock($id, $products_quantity): string
  {
    $stock_left = $this->getProductsStock($id) - $products_quantity;
    $out_of_stock = '';

    if ($stock_left < 0) {
      if (\defined('PRE_ORDER_AUTORISATION') && PRE_ORDER_AUTORISATION == 'true') {
        $out_of_stock = '<span class="markProductOutOfStock" id="markProductOutOfStock">' . CLICSHOPPING::getDef('text_out_of_stock_pre_order') . '</span>';
      } elseif (\defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'true') {
        $out_of_stock = '<span class="markProductOutOfStock" id="markProductOutOfStock">' . CLICSHOPPING::getDef('text_out_of_stock_allow_checkout') . '</span>';
      } else {
        $out_of_stock = '<span class="markProductOutOfStock" id="markProductOutOfStock">' . CLICSHOPPING::getDef('text_out_of_stock') . '</span>';
      }
}

    return $out_of_stock;
  }

  /**
   * Retrieves and formats the stock level display for a specified product.
   *
   * @param int $id The ID of the product for which the stock level is being retrieved.
   * @return string A formatted stock level display, including an appropriate ticker image and status message.
   */
  public function getDisplayProductsStock($id): string
  {
    $display_products_stock = $this->getProductsStock($id);

    if ($display_products_stock > \defined('STOCK_REORDER_LEVEL') ? STOCK_REORDER_LEVEL : 0) {
      $display_stock_values = HTML::tickerImage(CLICSHOPPING::getDef('text_in_stock'), 'ModulesTickerBootstrapTickerStockGood', true);
    } elseif ($display_products_stock <= \defined('STOCK_REORDER_LEVEL') ? STOCK_REORDER_LEVEL : 0  && $display_products_stock > 0) {
      $display_stock_values = HTML::tickerImage(CLICSHOPPING::getDef('text_alert_stock'), 'ModulesTickerBootstrapTickerStockWarning', true);
    } else {
      $display_stock_values = HTML::tickerImage(CLICSHOPPING::getDef('text_out_of_stock'), 'ModulesTickerBootstrapTickerStockDanger', true);
    }

    return $display_stock_values;
  }

  /**
   * Calculates the total count of active product attributes for a specific product and returns the count.
   *
   * @param int|null $id The ID of the product. If null, the method will retrieve the current product ID.
   * @return int The total count of active attributes associated with the product.
   */
  private function setCountProductsAttributes(?int $id = null)
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $language_id = $this->language->getId();

    $QproductsAttributes = $this->db->prepare('select count(*) as total
                                                from :table_products_options popt,
                                                     :table_products_attributes patrib
                                                where patrib.products_id = :products_id
                                                and patrib.options_id = popt.products_options_id
                                                and popt.language_id = :language_id
                                                and patrib.status = 1
                                               ');
    $QproductsAttributes->bindInt(':products_id', $id);
    $QproductsAttributes->bindInt(':language_id', (int)$language_id);

    $QproductsAttributes->execute();

    $products_attributes = $QproductsAttributes->fetch();

    return $products_attributes['total'];
  }

  /**
   * Retrieves the count of product attributes for a specific product.
   *
   * @param int|null $id The ID of the product. If null, the method will determine the ID using internal logic.
   * @return int The count of attributes associated with the product.
   */
  public function getCountProductsAttributes(?int $id = null)
  {
    return $this->SetCountProductsAttributes($id);
  }

  /**
   * Checks whether a product has active attributes in the database.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return bool True if the product has active attributes, false otherwise.
   */
  public function getHasProductAttributes(?int $id = null)
  {
    if (is_null($id)) {
      $id = $this->getID();
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
   * Sets the special price for a specific product based on the customer's group ID.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return float|null The "specials_new_products_price" value for the product if available, or null if no special price is set.
   */
  private function setProductsSpecialPrice(?int $id = null)
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $Qproduct = $this->db->prepare('select distinct customers_group_id,
                                                      specials_new_products_price
                                     from :table_specials
                                     where products_id = :products_id
                                     and status = 1
                                     and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                   ');
    $Qproduct->bindInt(':products_id', $id);
    $Qproduct->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

    $Qproduct->execute();

    if ($Qproduct->fetch() !== false) {
      $result = $Qproduct->valueDecimal('specials_new_products_price');

      return $result;
    }
}

  /**
   * Retrieves the special price for a specific product by delegating to the setProductsSpecialPrice method.
   *
   * @param int|null $id The ID of the product. If null, the method may use a default behavior to determine the ID.
   * @return mixed The retrieved special price value for the product.
   */
  public function getProductsSpecialPrice(?int $id = null)
  {
    return $this->setProductsSpecialPrice($id);
  }

//===================================================================================================================================

  /**
   * Sets and returns the manufacturer's name from the database based on the provided manufacturer ID and language ID.
   *
   * @return string The name of the manufacturer.
   */
  private function setManufacturersName(): string
  {
    $language_id = $this->language->getId();

    $Qmanufacturers = $this->db->prepare('select manufacturer_name
                                             from :table_manufacturers_info
                                             where manufacturers_id = :manufacturers_id
                                             and languages_id = :languages_id
                                           ');
    $Qmanufacturers->bindInt(':manufacturers_id', (int)$_GET['manufacturersId']);
    $Qmanufacturers->bindInt(':languages_id', (int)$language_id);

    $Qmanufacturers->execute();

    return $Qmanufacturers->value('manufacturer_name');
  }

  /**
   * Retrieves the list of manufacturers' names after initializing or setting them.
   *
   * @return array An array containing the manufacturers' names.
   */
  public function getManufacturersName(): array
  {
    $this->setManufacturersName();
  }

  /**
   * Sets and retrieves the "manufacturer_description" for a specific manufacturer from the database.
   *
   * @param int $id The ID of the manufacturer whose description needs to be retrieved.
   * @return string The "manufacturer_description" for the specified manufacturer.
   */
  private function setManufacturersDescription($id): string
  {
    $language_id = $this->language->getId();

    $Qmanufacturers = $this->db->prepare('select manufacturer_description
                                           from :table_manufacturers_info
                                           where manufacturers_id = :manufacturers_id
                                           and languages_id = :languages_id
                                         ');
    $Qmanufacturers->bindInt(':manufacturers_id', (int)$id);
    $Qmanufacturers->bindInt(':languages_id', (int)$language_id);

    $Qmanufacturers->execute();

    return $Qmanufacturers->value('manufacturer_description');
  }

  /**
   * Retrieves the description of a manufacturer based on the provided manufacturer ID.
   *
   * @return string The description of the manufacturer.
   */
  public function getManufacturersDescription(): string
  {
    $id = HTML::sanitize($_GET['manufacturersId']);

    return $this->setManufacturersDescription($id);
  }

  /**
   * Retrieves the image associated with a specific manufacturer from the database.
   *
   * @return string The manufacturer image filename or path.
   */
  private function setManufacturersImage(): string
  {
    $Qmanufacturers = $this->db->prepare('select manufacturer_image
                                             from :table_manufacturers
                                             where manufacturers_id = :manufacturers_id
                                           ');
    $Qmanufacturers->bindInt(':manufacturers_id', (int)$_GET['manufacturersId']);

    $Qmanufacturers->execute();

    return $Qmanufacturers->value('manufacturer_image');
  }

  /**
   * Retrieves the manufacturer's image by invoking the corresponding setter method.
   *
   * @return string The manufacturer's image.
   */
  public function getManufacturersImage(): string
  {
    return $this->setManufacturersImage();
  }

  /**
   * Generates and retrieves a dropdown array of manufacturers.
   *
   * The method includes an option for all manufacturers and fetches active manufacturers
   * from the database, ordering them by name.
   *
   * @return array An array of manufacturers with 'id' and 'text' keys.
   */

  public function setManufacturersDropDown(): array
  {
    $manufacturers_array = [];

    $manufacturers_array[] = [
      'id' => '',
      'text' => CLICSHOPPING::getDef('modules_advanced_search_manufacturers_text_all_manufacturers')
    ];

    $Qmanufacturers = $this->db->prepare('select manufacturers_id,
                                                    manufacturers_name
                                             from :table_manufacturers
                                             where manufacturers_status = :manufacturers_status
                                             order by manufacturers_name
                                           ');

    $Qmanufacturers->bindValue(':manufacturers_status', 0);
    $Qmanufacturers->execute();

    while ($Qmanufacturers->fetch()) {
      $manufacturers_array[] = [
        'id' => $Qmanufacturers->valueInt('manufacturersId'),
        'text' => $Qmanufacturers->value('manufacturers_name')
      ];
    }

    return $manufacturers_array;
  }

  /**
   * Retrieves a dropdown array of manufacturers.
   *
   * @return array The array containing manufacturers dropdown data.
   */
  public function getManufacturersDropDown(): array
  {
    return $this->setManufacturersDropDown();
  }

  /**
   * Determines whether a product qualifies as a "new product" based on its addition date.
   *
   * @param int|null $id The ID of the product to evaluate. If null, the method will use the current ID.
   * @return bool True if the product is considered new; otherwise, false.
   */
  private function setProductsTickerProductsNew(?int $id = null): bool
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $Qproducts = $this->db->prepare('select products_id,
                                             products_date_added
                                      from :table_products
                                      where products_status = 1
                                      and products_view = 1
                                      and products_id = :products_id
                                      and products_archive = 0
                                     ');

    $Qproducts->bindInt(':products_id', $id);

    $Qproducts->execute();

// 2592000 = 30 days in the unix timestamp format
    $day_new_products = 86400 * (int)DAY_NEW_PRODUCTS_ARRIVAL;
    $today_time = time();

    if (($today_time - strtotime($Qproducts->value('products_date_added'))) < $day_new_products) {
      $ticker = true;
    } else {
      $ticker = false;
    }

    return $ticker;
  }

  /**
   * Retrieves and sets the "products_ticker_products_new" status for a specific product.
   *
   * @param int|null $id The ID of the product. If null, the method will use a default value.
   * @return bool The result of the operation to set the "products_ticker_products_new" status.
   */
  public function getProductsTickerProductsNew(?int $id = null): bool
  {
    return $this->setProductsTickerProductsNew($id);
  }

  /**
   * Determines if a product qualifies as a "ticker special" based on its special addition date.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return bool True if the product is considered a "ticker special," false otherwise.
   */
  private function setProductsTickerSpecials(?int $id = null): bool
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    if ($this->customer->getCustomersGroupID() != 0) {
      $Qproducts = $this->db->prepare('select distinct specials_date_added
                                          from  :table_specials
                                          where status = 1
                                          and products_id = :products_id
                                          and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                         ');
      $Qproducts->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());
    } else {
      $Qproducts = $this->db->prepare('select distinct specials_date_added
                                          from  :table_specials
                                          where status = 1
                                          and products_id = :products_id
                                          and (customers_group_id = 0 or customers_group_id = 99)
                                        ');
    }

    $Qproducts->bindInt(':products_id', $id);

    $Qproducts->execute();

// 2592000 = 30 days in the unix timestamp format
    $day_new_products = 86400 *  \defined('DAY_NEW_PRODUCTS_ARRIVAL') ? (int)DAY_NEW_PRODUCTS_ARRIVAL : 0;
    $today_time = time();

    if (($today_time - strtotime($Qproducts->value('specials_date_added'))) < $day_new_products) {
      $ticker = true;
    } else {
      $ticker = false;
    }

    return $ticker;
  }

  /**
   * Retrieves and sets the "ticker specials" for a specific product.
   *
   * @param int|null $id The ID of the product. If null, a default value may be used by the method logic.
   * @return mixed The result of setting the "ticker specials" for the product.
   */
  public function getProductsTickerSpecials(?int $id = null)
  {
    return $this->setProductsTickerSpecials($id);
  }

  /**
   * Calculates the discount percentage for a product based on its special price group and regular price.
   *
   * @param int|string $id The ID of the product.
   * @param string $tag A string to append to the calculated percentage, defaulting to ' %'.
   * @return bool|string The calculated discount percentage as a string with the appended tag,
   *                     or an empty string if the special price group or regular price is not set.
   */
  public function getProductsTickerSpecialsPourcentage($id, string $tag = ' %'): bool|string
  {
    if ($this->setSpecialPriceGroup($id) != 0 && $this->setPrice($id) != 0) {
      $pourcentage_price = (round((($this->setSpecialPriceGroup($id) / $this->setPrice($id))), 2));
      $pourcentage_price = ((1 - $pourcentage_price) * (-100)) . $tag;

      return $pourcentage_price;
    } else {
      return '';
    }
}

  /**
   * Determines if a product is marked as favorite within a specific time period.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return bool True if the product is marked as favorite within the defined time period, otherwise false.
   */
  private function setProductsTickerFavorites(?int $id = null): bool
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $Qproducts = $this->db->prepare('select distinct products_favorites_date_added
                                       from :table_products_favorites
                                       where status = 1
                                      and products_id = :products_id
                                     ');

    $Qproducts->bindInt(':products_id', $id);
    $Qproducts->execute();

// 2592000 = 30 days in the unix timestamp format
    $day_new_products = 86400 * \defined('DAY_NEW_PRODUCTS_ARRIVAL') ? (int)DAY_NEW_PRODUCTS_ARRIVAL : 0;
    $today_time = time();

    if (($today_time - strtotime($Qproducts->value('products_favorites_date_added'))) < $day_new_products) {
      $ticker = true;
    } else {
      $ticker = false;
    }

    return $ticker;
  }

  /**
   * Determines if a product should be featured on the ticker based on its "featured date added" status and the predefined time period.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return bool True if the product is eligible to be featured on the ticker, false otherwise.
   */
  private function setProductsTickerFeatured(?int $id = null): bool
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $Qproducts = $this->db->prepare('select distinct products_featured_date_added
                                      from  :table_products_featured
                                      where status = 1
                                      and products_id = :products_id
                                     ');

    $Qproducts->bindInt(':products_id', $id);

    $Qproducts->execute();

// 2592000 = 30 days in the unix timestamp format
    $day_new_products = 86400 * \defined('DAY_NEW_PRODUCTS_ARRIVAL') ? (int)DAY_NEW_PRODUCTS_ARRIVAL : 0;
    $today_time = time();

    if (($today_time - strtotime($Qproducts->value('products_featured_date_added'))) < $day_new_products) {
      $ticker = true;
    } else {
      $ticker = false;
    }

    return $ticker;
  }

  /**
   * Sets the "ticker recommendation" status for a specific product based on its recent addition
   * and a minimum recommendation score.
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @return bool True if the product qualifies for the ticker recommendation, false otherwise.
   */
  private function setProductsTickerRecommendations(?int $id = null): bool
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $Qproducts = $this->db->prepare('select distinct pr.id,
                                                       p.products_id 
                                        from  :table_products_recommendations pr,
                                              :table_products p
                                        where p.products_status = 1
                                        and pr.score > ' . (float)CLICSHOPPING_APP_RECOMMENDATIONS_PR_MIN_SCORE . ' 
                                        and p.products_id = :products_id
                                        and p.products_id = pr.products_id
                                       ');

    $Qproducts->bindInt(':products_id', $id);

    $Qproducts->execute();

// 2592000 = 30 days in the unix timestamp format
    $day_new_products = 86400 * \defined('DAY_NEW_PRODUCTS_ARRIVAL') ? (int)DAY_NEW_PRODUCTS_ARRIVAL : 0;
    $today_time = time();

    if (($today_time - strtotime($Qproducts->value('date_added'))) < $day_new_products) {
      $ticker = true;
    } else {
      $ticker = false;
    }

    return $ticker;
  }

  /**
   * Retrieves the ticker favorites for a specific product.
   *
   * @param ?int $id The ID of the product. If null, a default behavior or ID will be used within the method.
   * @return mixed The result of setting the product's ticker favorites.
   */
  public function getProductsTickerFavorites(?int $id = null)
  {
    return $this->setProductsTickerFavorites($id);
  }

  /**
   * Retrieves the featured ticker information for a specific product.
   *
   * @param ?int|null $id The ID of the product. If null, the method may use a default or current ID.
   * @return mixed The result of setting the featured ticker for the product.
   */
  public function getProductsTickerFeatured(?int $id = null)
  {
    return $this->setProductsTickerFeatured($id);
  }

  /**
   * Retrieves ticker recommendations for a specific product.
   *
   * @param mixed $id The ID of the product. If null, the method may use a default or current ID.
   * @return mixed The result of setting the product's ticker recommendations.
   */
  public function getProductsTickerRecommendations($id = null)
  {
    return $this->setProductsTickerRecommendations($id);
  }

  /*
  * display Save Money by the customer
  * @param string
  * @return string $save_money, the difference between real price and  specials
  * @access private
  */
  /**
   * Calculates and sets the save money value for a customer based on special price and standard price.
   *
   * @param int|string $id The ID of the product or a unique product identifier.
   * @return float|null The amount of money saved, or null if calculation cannot be performed.
   */
  private function setProductsSaveMoneyCustomer(int|string $id): ?float
  {
    $savemoney = 0;

    if ($this->setSpecialPriceGroup($id) != 0 && $this->setPrice($id) != 0) {
      $savemoney = (round((($this->setPrice($id) - $this->setSpecialPriceGroup($id))), 4));
    }

    return $savemoney;
  }

  /*
  * display a save Money by the customer
  * @param int|string
  * @return string $save_money, the difference between real price and  specials
  */
  /**
   * Retrieves the savings amount for a customer-specific product.
   *
   * @param int|string $id The ID of the product, which can be an integer or a string.
   * @return float|null The amount of money the customer saves on the product, or null if unavailable.
   */
  public function getProductsSaveMoneyCustomer(int|string $id): ?float
  {
    return $this->setProductsSaveMoneyCustomer($id);
  }

  /**
   * Calculates the new price of a product based on quantity discounts applied to the product.
   *
   * @param int|null $id The ID of the product. If null, product-specific discount handling might not occur correctly.
   * @param int|null $qty The quantity of the product being purchased. This determines which discount, if any, is applied.
   * @param float|null $products_price The original price of the product before applying any discounts.
   * @return float|bool The discounted price of the product if applicable. Returns false if no discount applies or calculation fails.
   */
  public function getProductsNewPriceByDiscountByQuantity($id = null, $qty = null, $products_price = null)
  {
    $QprodutsQuantityDiscount = $this->db->prepare('select discount_quantity,
                                                            discount_customer
                                                      from :table_products_discount_quantity
                                                      where products_id = :products_id
                                                      and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                                      and discount_quantity <> 0
                                                    ');
    $QprodutsQuantityDiscount->bindInt(':products_id', $id);
    $QprodutsQuantityDiscount->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

    $QprodutsQuantityDiscount->execute();

    $discount_quantity = [];
    $discount_customer = [];

    while ($QprodutsQuantityDiscount->fetch()) {
      $discount_quantity[] = $QprodutsQuantityDiscount->valueInt('discount_quantity');
      $discount_customer[] = $QprodutsQuantityDiscount->valueDecimal('discount_customer');
    }

    $nb_discount = $QprodutsQuantityDiscount->rowCount(); // dans ton exemple 3 discounts

    $new_discount_price = null;

    $i = $nb_discount - 1; // 0,1,2 pour les indices des tableaux de ton example

    for ($i; $i > -1; $i--) {
      if ($qty >= $discount_quantity[$i]) {
        $new_discount_price = ($products_price - ($products_price * ($discount_customer[$i] / 100)));
        $_SESSION['ProductsID'] = $id;
      }
}

    if (!is_null($new_discount_price) || !empty($new_discount_price)) {
      return $new_discount_price;
    } else {
      return false;
    }
}

  /**
   * Calculates the total discount for a product based on its quantity in the shopping cart.
   *
   * @param int|null $id The ID of the product. If null, the method may use an alternative way to identify the product.
   * @param int|null $qty The quantity of the product in the shopping cart. If null, no specific quantity is used.
   * @param float|null $products_price The original price of the product. If null, the price must be determined elsewhere.
   * @return float The total discount amount for the specified product and quantity.
   */
  public function getInfoPriceDiscountByQuantityShoppingCart($id = null, $qty = null, $products_price = null)
  {
    $QprodutsQuantityDiscount = $this->db->prepare('select discount_quantity,
                                                            discount_customer
                                                      from :table_products_discount_quantity
                                                      where products_id = :products_id
                                                      and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                                      and discount_quantity <> 0
                                                    ');
    $QprodutsQuantityDiscount->bindInt(':products_id', $id);
    $QprodutsQuantityDiscount->bindInt(':customers_group_id', $this->customer->getCustomersGroupID());

    $QprodutsQuantityDiscount->execute();

    while ($QprodutsQuantityDiscount->fetch()) {
      $discount_quantity[] = $QprodutsQuantityDiscount->valueInt('discount_quantity');
      $discount_customer[] = $QprodutsQuantityDiscount->valueDecimal('discount_customer');
    }

    $nb_discount = $QprodutsQuantityDiscount->rowCount(); // dans ton exemple 3 discounts
    $discount = 0;

    $i = $nb_discount - 1; // 0,1,2 pour les indices des tableaux de ton exemple

    for ($i; $i > -1; $i--) {
      if ($qty >= $discount_quantity[$i]) {
        $new_discount_price = ($products_price - ($products_price * ($discount_customer[$i] / 100)));

        $_SESSION['ProductsID'] = $id;

        $discount = ($products_price - $new_discount_price) * $qty;
      }
}

    return $discount;
  }

  /*
  * Return table heading with sorting capabilities
  */
  /**
   * Constructs a sortable heading link for products, allowing for dynamic sorting and filtering based on user inputs.
   *
   * @param string|null $sortby Specifies the current sorting order and column.
   *                            If null, the default sorting behavior is applied.
   * @param string $column The column by which the data will be sorted.
   * @param string $heading The heading text to be displayed within the link.
   * @return string The HTML markup for the sortable heading link.
   */
  public function createSortHeading($sortby, $column, $heading)
  {
    if (isset($_POST['keywords'])) {
      $keywords = HTML::sanitize($_POST['keywords']);
    } elseif (isset($_GET['keywords'])) {
      $keywords = HTML::sanitize($_GET['keywords']);
    } else {
      $keywords = '';
    }

    if (isset($sortby)) {
      if (isset($_POST['keywords']) || isset($_GET['keywords'])) {
        $sort_prefix = '<a href="' . CLICSHOPPING::link(null, CLICSHOPPING::getAllGET(array('page', 'info', 'sort')) . '&keywords=' . $keywords . '&page=1&sort=' . $column . ($sortby == $column . 'a' ? 'd' : 'a')) . '" title="' . HTML::output(CLICSHOPPING::getDef('text_sort_products') . ($sortby == $column . 'd' || substr($sortby, 0, 1) != $column ? CLICSHOPPING::getDef('text_ascendingly') : CLICSHOPPING::getDef('text_descendingly')) . CLICSHOPPING::getDef('text_by') . $heading) . '" class="productListing-heading">';
        $sort_suffix = ' ' . (substr($sortby, 0, 1) == $column ? (substr($sortby, 1, 1) == 'a' ? '+' : '-') : '') . '</a>';
      } else {
        $sort_prefix = '<a href="' . CLICSHOPPING::link(null, CLICSHOPPING::getAllGET(array('page', 'info', 'sort')) . '&page=1&sort=' . $column . ($sortby == $column . 'a' ? 'd' : 'a')) . '" title="' . HTML::output(CLICSHOPPING::getDef('text_sort_products') . ($sortby == $column . 'd' || substr($sortby, 0, 1) != $column ? CLICSHOPPING::getDef('text_ascendingly') : CLICSHOPPING::getDef('text_descendingly')) . CLICSHOPPING::getDef('text_by') . $heading) . '" class="productListing-heading">';
        $sort_suffix = ' ' . (substr($sortby, 0, 1) == $column ? (substr($sortby, 1, 1) == 'a' ? '+' : '-') : '') . '</a>';
      }
} else {
      $sort_prefix = '<a href="' . CLICSHOPPING::link(null, CLICSHOPPING::getAllGET(array('page', 'info', 'sort')) . '&keywords=' . $keywords . '&page=1&sort=' . $column . ($sortby == $column . 'a' ? 'd' : 'a')) . '" title="' . HTML::output(CLICSHOPPING::getDef('text_sort_products') . ($sortby == $column . 'd' || substr($sortby, 0, 1) != $column ? CLICSHOPPING::getDef('text_ascendingly') : CLICSHOPPING::getDef('text_descendingly')) . CLICSHOPPING::getDef('text_by') . $heading) . '" class="productListing-heading">';
      $sort_suffix = ' ' . (substr($sortby, 0, 1) == $column ? (substr($sortby, 1, 1) == 'a' ? '+' : '-') : '') . '</a>';
    }

    return $sort_prefix . $heading . $sort_suffix;
  }

  /*
  * Return the class_id of the product
  * @param Int $id, id product
  * @return Int products_weight_class_id, Id of the weight class
  */
  /**
   * Sets and retrieves the "products_weight_class_id" for a specific product from the database.
   *
   * @param int $id The ID of the product to retrieve the weight class ID for.
   * @return int|null The "products_weight_class_id" value for the product, or null if not found.
   */
  private function setWeightClassIdByProducts(int $id): ?int
  {
    $QweightClass = $this->db->prepare('select products_weight_class_id
                                           from :table_products
                                           where products_id = :products_id
                                         ');
    $QweightClass->bindInt(':products_id', $id);
    $QweightClass->execute();

    return $QweightClass->value('products_weight_class_id');
  }

  /*
  * Display the class_id of the product
  * @param Int $id, id product
  * @return Int products_weight_class_id, Id of the weight class
  */
  /**
   * Retrieves the weight class ID associated with a specific product.
   *
   * @param mixed $id The ID of the product, which will be sanitized before use.
   * @return mixed The weight class ID of the specified product.
   */
  public function getWeightClassIdByProducts(int $id): ?int
  {
    $id = HTML::sanitize($id);

    return $this->setWeightClassIdByProducts($id);
  }

  /**
   * Sets and retrieves the "weight_class_key" for a specific weight class from the database.
   *
   * @param int $weight_class_id The ID of the weight class to fetch the corresponding weight class key.
   * @return string The "weight_class_key" value associated with the specified weight class ID.
   */
  private function setSymbolWeightByProducts(int $weight_class_id): string
  {
    $QweightSymbol = $this->db->prepare('select weight_class_key
                                           from :table_weight_classes
                                           where weight_class_id = :products_weight_class_id
                                           and language_id = :language_id
                                         ');
    $QweightSymbol->bindInt(':products_weight_class_id', $weight_class_id);
    $QweightSymbol->bindInt(':language_id', $this->language->getId());
    $QweightSymbol->execute();

    return $QweightSymbol->value('weight_class_key');
  }

  /**
   * Retrieves the symbol weight for products based on the given weight class ID.
   *
   * @param int $weight_class_id The ID of the weight class to retrieve the symbol weight for.
   * @return mixed The symbol weight associated with the specified weight class.
   */
  public function getSymbolWeightByProducts(int $weight_class_id): mixed
  {
    $weight_class_id = HTML::sanitize($weight_class_id);

    return $this->setSymbolWeightByProducts($weight_class_id);
  }
  
   /**
   * Gets the percentage change due to dynamic pricing
   *
   * @param int|null $id The ID of the product. If null, the method will use the current ID.
   * @param $base_price
   * @return float The percentage change (positive for increase, negative for decrease)
   */
  public function getDynamicPricing(int|string|null $id , float $base_price, int $customers_group)
  {
    if (is_null($id)) {
      $id = $this->getID();
    }

    $dynamic_price = $this->dynamicPricingRules->apply((int)$id, $base_price, $customers_group);

    return $dynamic_price;
  }
} // end class

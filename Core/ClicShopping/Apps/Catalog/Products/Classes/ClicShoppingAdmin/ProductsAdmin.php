<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin;


use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Upload;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\SubProductAdmin\ProductSaveDataBuilder;
use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\SubProductAdmin\ProductDescriptionSaver;
use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\SubProductAdmin\ProductCloner;
use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\SubProductAdmin\ProductRemover;
use function call_user_func;
use function count;
use function is_array;
use function is_null;

/**
 * ProductsAdmin class provides methods for managing products within the admin panel,
 * including retrieving product information, saving product descriptions,
 * and managing various product-specific details like model, SKU, EAN, and packaging.
 */

class ProductsAdmin
{
  private mixed $db;
  private mixed $template;
  private mixed $hooks;
  private mixed $lang;
  private mixed $image;

  /**
   * ProductsAdmin class provides methods for managing products within the admin panel,
   * including retrieving product information, saving product descriptions,
   * and managing various product-specific details like model, SKU, EAN, and packaging.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->template = Registry::get('TemplateAdmin');
    $this->hooks = Registry::get('Hooks');
    $this->lang = Registry::get('Language');
    $this->image = Registry::get('Image');
  }

  /**
   * Retrieves product information and description for a given product ID.
   * @param int $id The product ID to retrieve data for.
   * @return array An associative array containing the product details and description.
   */
  public function get(int $id): array
  {
    $Qproducts = $this->db->prepare('select p.*,
                                              date_format(p.products_date_available, \'%Y-%m-%d\') as products_date_available,
                                              pd.*
                                      from :table_products p,
                                           :table_products_description pd
                                      where p.products_id = :products_id
                                      and p.products_id = pd.products_id
                                      and pd.language_id = :language_id'
    );

    $Qproducts->bindInt(':products_id', $id);
    $Qproducts->bindInt(':language_id', $this->lang->getId());
    $Qproducts->execute();

    $data = $Qproducts->toArray();

    return $data;
  }

  /**
   * Retrieves the product model from the input or generates a random one if not provided.
   * @return string The sanitized or generated product model.
   */
  public function getProductModel(): string
  {
    return (new ProductSaveDataBuilder())->getProductModel();
  }

  /**
   * Retrieves the SKU (Stock Keeping Unit) of the product based on user input or default model value.
   * @return string The product SKU
   */
  public function getProductSKU(): string
  {
    return (new ProductSaveDataBuilder())->getProductSKU();
  }

  /**
   * Retrieve the EAN (European Article Number) of the product.
   * If no EAN is provided, uses the product SKU as the fallback.
   * Sanitizes the provided EAN if it differs from the product SKU.
   *
   * @return string The EAN of the product.
   */
  public function getProductEAN(): string
  {
    return (new ProductSaveDataBuilder())->getProductEAN();
  }

  /**
   * Save the uploaded file for a product download, if valid.
   * @return string|null Returns the sanitized filename of the uploaded file if successful, otherwise null.
   */
  private function saveFileUpload(): ?string
  {
    $array_extension = ['zip', 'doc', 'pdf', 'odf', 'xls', 'mp3', 'mp4', 'avi', 'png', 'jpg', 'gif'];

    $upload_file = new Upload('products_download_filename', $this->template->getPathDownloadShopDirectory(), null, $array_extension);

    if ($upload_file->check() && $upload_file->save()) {
      $products_download_filename = $upload_file->getFilename();
      $file = HTML::removeFileAccents($products_download_filename);
    } else {
      $file = null;
    }

    return $file;
  }

  /**
   * Retrieve image information including source, alt text, width, and height
   *
   * @param mixed $image - The image file name or path
   * @param mixed $alt - The alternate text for the image
   * @param string $width - The width of the image, default is '130'
   * @param string $height - The height of the image, default is '130'
   * @return string The HTML string containing the image element
   */
  public function getInfoImage($image, $alt, string $width = '130', string $height = '130'): string
  {
    if (!empty($image) && (file_exists($this->template->getDirectoryPathTemplateShopImages() . $image))) {
      $image = HTML::image($this->template->getDirectoryShopTemplateImages() . $image, $alt, $width, $height);
    } else {
      $image = HTML::image(HTTP::getShopUrlDomain() . 'images/nophoto.png', CLICSHOPPING::getDef('text_image_nonexistent'), $width, $height);
    }

    return $image;
  }

  /**
   * Retrieves the packaging information for a product based on its ID.
   * @param int $id - The ID of the product.
   * @return string - The packaging status of the product (e.g., 'New product', 'Product repackaged', 'Product used').
   */
  public function getproductPackaging(int $id): string
  {
    if (!is_null($_SESSION['ProductAdminId'])) {
      $id = $_SESSION['ProductAdminId'];

      $QproductAdmin = $this->db->prepare('select products_packaging
                                             from :table_products
                                             where products_id = :products_id
                                            ');
      $QproductAdmin->bindInt(':products_id', $id);
      $QproductAdmin->execute();

      $packaging = $QproductAdmin->valueInt('products_packaging');
    } else {
      $QproductAdmin = $this->db->prepare('select products_packaging
                                             from :table_products
                                             where products_id = :products_id
                                            ');
      $QproductAdmin->bindInt(':products_id', $id);
      $QproductAdmin->execute();

      $packaging = $QproductAdmin->valueInt('products_packaging');
    }

    if ($packaging == 1) {
      $product_packaging = 'New product';
    } elseif ($packaging == 2) {
      $product_packaging = 'Product repackaged';
    } else {
      $product_packaging = 'Product used';
    }

    return $product_packaging;
  }

  /**
   * Retrieves the title of the specified products quantity unit based on the provided ID and language ID.
   *
   * @param $products_quantity_unit_id - the ID of the products quantity unit
   * @param $language_id - optional language ID to retrieve the title for; defaults to current language ID if not provided
   * @return string|null - the title of the products quantity unit or null if not found
   */
  public function getProductsQuantityUnitTitle($products_quantity_unit_id = '', $language_id = '')
  {

    if (!$language_id) $language_id = $this->lang->getId();

    $QproductsQuantityUnitTitle = $this->db->prepare('select products_quantity_unit_title
                                                        from :table_products_quantity_unit
                                                        where products_quantity_unit_id = :products_quantity_unit_id
                                                        and language_id = :language_id
                                                      ');

    $QproductsQuantityUnitTitle->bindInt(':products_quantity_unit_id', $products_quantity_unit_id);
    $QproductsQuantityUnitTitle->bindInt(':language_id', $language_id);

    $QproductsQuantityUnitTitle->execute();

    return $QproductsQuantityUnitTitle->value('products_quantity_unit_title');
  }

  /**
   * Retrieve the product model name for the given product ID.
   * @param $id - ID of the product (optional)
   * @return string - The product model name
   */
  public function getProductsModel($id = ''): string
  {
    $QproductsModel = $this->db->prepare('select products_model
                                            from :table_products
                                            where products_id = :products_id
                                           ');

    $QproductsModel->bindInt(':products_id', $id);

    $QproductsModel->execute();

    return $QproductsModel->value('products_model');
  }

  /**
   * Retrieve the shipping delay for a specific product based on its ID and language.
   * @param string|int|null $id - The ID of the product. Can be null if no product ID is provided.
   * @param int|null $language_id - The ID of the language for the product description.
   * @return string|bool - Returns the shipping delay as a string if the product and language exist, or false if no product ID is provided.
   */
  public function getProductsShippingDelay(string|int|null $id = null, int|null $language_id = null): string|bool
  {
    if (!$language_id) $language_id = $this->lang->getId();

    if (!is_null($id)) {
      $Qproduct = $this->db->prepare('select products_shipping_delay
                                       from :table_products_description
                                       where products_id = :products_id
                                       and language_id = :language_id
                                     ');
      $Qproduct->bindInt(':products_id', $id);
      $Qproduct->bindInt(':language_id', $language_id);

      $Qproduct->execute();

      return $Qproduct->value('products_shipping_delay');
    } else {
      return false;
    }
}

  /**
   * Retrieve the shipping delay information for out-of-stock products.
   *
   * @param string|int|null $id - The ID of the product. If null, the method returns false.
   * @param int|null $language_id - The ID of the language in which the information is retrieved.
   * @return string|bool - Returns the shipping delay information as a string if the product exists, otherwise returns false.
   */
  public function getProductsShippingDelayOutOfStock(string|int|null $id = null, int|null $language_id = null): string|bool
  {
    if (!$language_id) $language_id = $this->lang->getId();

    if (!is_null($id)) {
      $Qproduct = $this->db->prepare('select products_shipping_delay_out_of_stock
                                       from :table_products_description
                                       where products_id = :products_id
                                       and language_id = :language_id
                                     ');
      $Qproduct->bindInt(':products_id', $id);
      $Qproduct->bindInt(':language_id', $language_id);

      $Qproduct->execute();

      return $Qproduct->value('products_shipping_delay_out_of_stock');
    } else {
      return false;
    }
}

  /**
   * Retrieves the summary of the product description for a specific product and language.
   *
   * @param string|int|null $product_id - The ID of the product whose description summary is being retrieved. Can be null if a product ID is not provided.
   * @param int|null $language_id - The language ID for the description summary. If not provided, the default language ID will be used.
   * @return mixed - The product description summary if available, or null otherwise.
   */
  public function getProductsDescriptionSummary(string|int|null $product_id, int|null $language_id = null)
  {
    if (!$language_id) $language_id = $this->lang->getId();

    if (!is_null($product_id)) {
      if (!$language_id) $language_id = $this->lang->getId();

      $Qproduct = $this->db->prepare('select products_description_summary
                                       from :table_products_description
                                       where products_id = :products_id
                                       and language_id = :language_id
                                    ');
      $Qproduct->bindInt(':products_id', $product_id);
      $Qproduct->bindInt(':language_id', $language_id);

      $Qproduct->execute();

      return $Qproduct->value('products_description_summary');
    }
  }

  /**
   * Retrieve the image of a product based on the provided product ID.
   * @param string $product_id The ID of the product to retrieve the image for. Defaults to an empty string.
   * @return string The file name of the product image.
   */
  public function getProductsImage(string $product_id = ''): string
  {
    $Qproduct = Registry::get('Db')->get('products', 'products_image', ['products_id' => (int)$product_id]);

    return $Qproduct->value('products_image');
  }

  /**
   * Retrieves a list of product directories within the specified images/products directory.
   *
   * Filters out unwanted entries and formats the remaining directory names into an array structure.
   *
   * @return array An array of directories with 'id' and 'text' keys representing the directory structure.
   */

  public function getDirectoryProducts(): array
  {
    $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/images/products/';

    $weeds = ['.', '..', '_notes'];

    $directories = array_diff(scandir($template_directory), $weeds);
    $directory_array = [];

    $directory_array[0] = [
      'id' => '',
      'text' => CLICSHOPPING::getDef('select_datas')
    ];

    foreach ($directories as $directory) {
      if (is_dir($template_directory . $directory)) {
        $directory_array[] = [
          'id' => $directory,
          'text' => $directory
        ];
      }
    }

    return $directory_array;
  }

  /**
   * Retrieves the name of a product based on the provided product ID and language ID.
   * If no language ID is provided, the default language ID will be used.
   *
   * @param $product_id - the ID of the product (optional)
   * @param $language_id - the ID of the language (default is 0, which will use the default language ID)
   * @return string - returns the name of the product
   */
  public function getProductsName($product_id = '', int $language_id = 0): string
  {
    if ($language_id == 0) $language_id = $this->lang->getId();

    $array = [
      'products_id' => (int)$product_id,
      'language_id' => (int)$language_id
    ];

    $Qproduct = Registry::get('Db')->get('products_description', 'products_name', $array);

    return $Qproduct->value('products_name');
  }

  /**
   * Retrieve the description of a product for a specific language.
   *
   * @param string|int|null $product_id - The ID of the product. Can be a string, integer, or null.
   * @param int|null $language_id - The ID of the language in which the product description is needed.
   * @return string|bool - Returns the product description as a string on success, or false if the product ID is null or the operation fails.
   */
  public function getProductsDescription(string|int|null $product_id, int|null $language_id = null): string|bool
  {
    if (!$language_id) $language_id = $this->lang->getId();

    if (!is_null($product_id)) {

      if ($language_id == 0) $language_id = $this->lang->getId();

      $sql_array = [
        'products_id' => (int)$product_id,
        'language_id' => (int)$language_id
      ];

      $Qproduct = Registry::get('Db')->get('products_description', 'products_description', $sql_array);

      return $Qproduct->value('products_description');
    } else {
      return false;
    }
  }

  /**
   * Generate a dropdown list of suppliers with their IDs and names.
   * Retrieves supplier data from the database and formats it for use in dropdown menus.
   *
   * @return array - An array of suppliers, each containing an 'id' and 'text' key
   */
  public function supplierDropDown(): array
  {
    $supplier = [
      [
        'id' => '',
        'text' => CLICSHOPPING::getDef('text_none')
      ]
    ];

    $Qsupplier = $this->db->prepare('select suppliers_id,
                                              suppliers_name
                                       from :table_suppliers
                                       order by suppliers_name
                                      ');
    $Qsupplier->execute();

    while ($Qsupplier->fetch() !== false) {
      $supplier[] = [
        'id' => $Qsupplier->valueInt('suppliers_id'),
        'text' => $Qsupplier->value('suppliers_name')
      ];
    }

    return $supplier;
  }

  /**
   * Retrieve product images and related details from the database.
   * @param int $id - The ID of the product.
   * @return mixed - The result set containing product images and details or null if no data is found.
   */
  public function getImage(int $id): mixed
  {
    $Qimage = $this->db->prepare('select products_image,
                                          products_image_zoom,
                                          products_image_medium,
                                          products_image_small,
                                          products_model,
                                          products_ean
                                   from :table_products
                                   where products_id = :products_id
                                  ');
    $Qimage->bindInt(':products_id', $id);
    $Qimage->execute();

    $result = $Qimage->fetch();

    return $result;
  }

  /**
   * Removes a product and all its associated data. Delegates to ProductRemover;
   * the product image row (getImage, kept here for its many callers) is resolved
   * once and passed in.
   *
   * @param int $id The product id to remove
   * @return void
   */
  public function removeProduct(int $id): void
  {
    (new ProductRemover())->remove($id, $this->getImage($id));
  }

  /**
   * Retrieves the URL of a product based on the given product ID and language ID.
   *
   * @param int|string $product_id The ID of the product for which the URL is to be retrieved.
   * @param int|null $language_id The language ID to fetch the product URL. If 0 or null, the default language ID will be used.
   *
   * @return string|bool Returns the product URL as a string if found, otherwise returns false.
   */
  public function getProductsUrl(int|string $product_id, int|null $language_id = null): string|bool
  {
    if ($language_id === null) $language_id = $this->lang->getId();

    if (((is_null($language_id)) || $language_id == 0) && !is_null($product_id)) {
      $language_id = $this->lang->getId();

      $Qproduct = Registry::get('Db')->get('products_description', 'products_url', ['products_id' => (int)$product_id, 'language_id' => (int)$language_id]);

      return $Qproduct->value('products_url');
    } else {
      return false;
    }
  }

  /**
   * Retrieves the URL associated with a manufacturer based on the provided manufacturer ID and language ID.
   *
   * @param string|int|null $manufacturer_id The identifier of the manufacturer. Can be a string, integer, or null.
   * @param int|null $language_id The language ID used to fetch the manufacturer URL. If set to 0, the default language ID is used.
   * @return string|bool Returns the manufacturer's URL as a string if found, or false if the manufacturer ID is null or the URL does not exist.
   */
  public function getManufacturerUrl(string|int|null $manufacturer_id, int $language_id): string|bool
  {
    if ($language_id == 0) $language_id = $this->lang->getId();

    if (!is_null($manufacturer_id)) {
        $Qmanufacturer = Registry::get('Db')->get('manufacturers_info', 'manufacturers_url', ['manufacturers_id' => (int)$manufacturer_id, 'languages_id' => (int)$language_id]);

      return $Qmanufacturer->value('manufacturers_url');
    } else {
      return false;
    }
  }

  /**
   * Retrieves the count of products associated with a specific category.
   *
   * @param int $id The ID of the product.
   * @param int $categories_id The ID of the category.
   * @return int The total count of products associated with the given category.
   */
  public function getCountProductsToCategory(int $id, int $categories_id): int
  {
    $Qcheck = $this->db->prepare('select count(*) as total
                                           from :table_products_to_categories
                                           where products_id = :products_id
                                           and categories_id = :categories_id
                                          ');
    $Qcheck->bindInt(':products_id', $id);
    $Qcheck->bindInt(':categories_id', $categories_id);
    $Qcheck->execute();

    return $Qcheck->valueInt('total');
  }

  /**
   * Clones a product into another category (or categories), including its images,
   * descriptions and customer-group pricing. Delegates to ProductCloner.
   *
   * @param int $id The product id to clone
   * @param mixed $new_categories_id Target category id(s)
   * @return void
   */
  public function cloneProductsInOtherCategory(int $id, mixed $new_categories_id): void
  {
    (new ProductCloner())->cloneToCategory($id, $new_categories_id);
  }

  /**
   * Retrieves the search results for products based on the provided keywords or category.
   *
   * @param string|null $keywords The search keywords for filtering products. If null or empty, the category is used instead.
   * @param int $current_category_id The ID of the current category to filter products if no
   */

  public function getSearch(?string $keywords = null, int|string $current_category_id = 0)
  {
    if (isset($keywords) && !empty($keywords)) {
      $keywords = HTML::sanitize($keywords);

      $Qproducts = $this->db->prepare('select SQL_CALC_FOUND_ROWS  p.products_id,
                                                                     pd.products_name,
                                                                     p.products_model,
                                                                     p.products_ean,
                                                                     p.products_sku,
                                                                     p.products_mpn,
                                                                     p.products_isbn,
                                                                     p.products_upc,
                                                                     p.products_jan,
                                                                     p.products_quantity,
                                                                     p.products_image,
                                                                     p.products_price,
                                                                     p.products_date_added,
                                                                     p.products_last_modified,
                                                                     p.products_date_available,
                                                                     p.products_status,
                                                                     p.admin_user_name,
                                                                     p.products_quantity_unit_id,
                                                                     p2c.categories_id,
                                                                     p.products_sort_order,
                                                                     p.products_download_filename
                                         from :table_products p,
                                              :table_products_description pd,
                                              :table_products_to_categories p2c
                                         where p.products_id = pd.products_id
                                         and pd.language_id = :language_id
                                         and p.products_id = p2c.products_id
                                         and p.products_archive = 0
                                         and (pd.products_name like :search
                                              or  p.products_model like :search
                                              or p.products_ean like :search
                                             )
                                         order by pd.products_name
                                      ');

      $Qproducts->bindInt(':language_id', $this->lang->getId());
      $Qproducts->bindValue(':search', '%' . $keywords . '%');

      $Qproducts->execute();
    } else {
      $Qproducts = $this->db->prepare('select SQL_CALC_FOUND_ROWS p.products_id,
                                                                     pd.products_name,
                                                                     p.products_model,
                                                                     p.products_ean,
                                                                     p.products_sku,
                                                                     p.products_mpn,
                                                                     p.products_isbn,
                                                                     p.products_upc,
                                                                     p.products_jan,
                                                                     p.products_quantity,
                                                                     p.products_image,
                                                                     p.products_price,
                                                                     p.products_date_added,
                                                                     p.products_last_modified,
                                                                     p.products_date_available,
                                                                     p.products_status,
                                                                     p.admin_user_name,
                                                                     p.products_sort_order,
                                                                     p.products_download_filename,
                                                                     p2c.categories_id
                                           from :table_products p,
                                                :table_products_description pd,
                                                :table_products_to_categories p2c
                                           where p.products_id = pd.products_id
                                           and pd.language_id = :language_id
                                           and p.products_id = p2c.products_id
                                           and p2c.categories_id = :categories_id
                                           and p.products_archive = 0
                                           order by pd.products_name
                                           limit :page_set_offset, :page_set_max_results
                                        ');

      $Qproducts->bindInt(':categories_id', (int)$current_category_id);
      $Qproducts->bindInt(':language_id', $this->lang->getId());
      $Qproducts->setPageSet(\defined('MAX_DISPLAY_SEARCH_RESULTS_ADMIN') ? (int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN : 0);
      $Qproducts->execute();
    }

    return $Qproducts;
  }

  /**
   * Saves product data to the database, handling both insert and update operations.
   *
   * @param string|int|null $id The ID of the product to update. Pass null for a new product.
   * @param mixed $action The action to be performed, typically 'Update' or for insertion.
   *
   * @return void
   */
  public function save(string|int|null $id, $action)
  {
    $sql_data_array = (new ProductSaveDataBuilder())->build();

// Download file
    $sql_data_array['products_download_filename'] = $this->saveFileUpload();
// image
    $this->image->getImage();

    $sql_data_array['products_image'] = $this->image->productsImage();
    $sql_data_array['products_image_medium'] = $this->image->productsImageMedium();
    $sql_data_array['products_image_zoom'] = $this->image->productsImageZoom();
    $sql_data_array['products_image_small'] = $this->image->productsSmallImage();
//---------------------------------------------------------------------------------------------
//  Save Data
//---------------------------------------------------------------------------------------------
//update
    if (is_numeric($id) && !is_null($id) && $action == 'Update') {
      $update_sql_data = ['products_last_modified' => 'now()'];
      $sql_data_array = array_merge($sql_data_array, $update_sql_data);

      $this->db->save('products', $sql_data_array, ['products_id' => $id]);
    } else {
//insert
      $insert_sql_data = ['products_date_added' => 'now()'];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $this->db->save('products', $sql_data_array);

      $id = $this->db->lastInsertId();
    }

    $this->image->saveGalleryImage($id);
    (new ProductDescriptionSaver())->save($id, $action);

    if (isset($_POST['clone_categories_id_to'])) {
      $categories_id = $_POST['clone_categories_id_to'];
      (new ProductCloner())->prepareClone($id, $categories_id);
    }

    $this->hooks->call('Products', 'Save', ['products_id' => $id]);
  }

  /**
   * Gets the total count of products within a category, optionally including deactivated products.
   *
   * @param int $products_id The ID of the category whose products should be counted.
   * @param bool $include_deactivated Whether to include deactivated products in the count. Defaults to false.
   *
   * @return int The total number of products in the specified category.
   */
  public function getProductsInCategoryCount(int $products_id, bool $include_deactivated = false): int
  {
    if ($include_deactivated) {
      $Qproducts = $this->db->get([
        'products p',
        'products_to_products p2c'
      ], [
        'count(*) as total'
      ], [
          'p.products_id' => [
            'rel' => 'p2c.products_id'
          ],
          'p2c.products_id' => $products_id
        ]
      );
    } else {
      $Qproducts = $this->db->get([
        'products p',
        'products_to_products p2c'
      ], [
        'count(*) as total'
      ], [
          'p.products_id' => [
            'rel' => 'p2c.products_id'
          ],
          'p.products_status' => '1',
          'p2c.products_id' => $products_id
        ]
      );
    }

    $products_count = $Qproducts->valueInt('total');

    $Qchildren = $this->db->get('products', 'products_id', ['parent_id' => $products_id]);

    while ($Qchildren->fetch() !== false) {
      $products_count += call_user_func(__METHOD__, $Qchildren->valueInt('products_id'), $include_deactivated);
    }

    return $products_count;
  }
}
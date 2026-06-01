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
   * Save the description details of a product for multiple languages.
   * @param int $id - The unique identifier of the product.
   * @param string $action - The action to be performed, either 'Insert' or 'Update'.
   * @return void
   */
  private function saveProductsDescription(int $id, string $action)
  {
    $languages = $this->lang->getLanguages();

    for ($i = 0, $n = count($languages); $i < $n; $i++) {
      $language_id = $languages[$i]['id'];

      $sql_data_array = [
        'products_name' => HTML::sanitize($_POST['products_name'][$language_id]),
        'products_description' => $_POST['products_description'][$language_id] ?? '',
        'products_seo_url' => isset($_POST['products_seo_url'][$language_id]) ? HTML::sanitize(strip_tags($_POST['products_seo_url'][$language_id])) : '',
        'products_head_title_tag' => isset($_POST['products_head_title_tag'][$language_id]) ? HTML::sanitize(strip_tags($_POST['products_head_title_tag'][$language_id])) : '',
        'products_head_desc_tag' => isset($_POST['products_head_desc_tag'][$language_id]) ? HTML::sanitize($_POST['products_head_desc_tag'][$language_id]) : '',
        'products_head_keywords_tag' => isset($_POST['products_head_keywords_tag'][$language_id]) ? HTML::sanitize(strip_tags($_POST['products_head_keywords_tag'][$language_id])) : '',
        'products_url' => isset($_POST['products_url'][$language_id]) ? HTML::sanitize($_POST['products_url'][$language_id]) : '',
        'products_head_tag' => isset($_POST['products_head_tag'][$language_id]) ? HTML::sanitize(strip_tags($_POST['products_head_tag'][$language_id])) : '',
        'products_shipping_delay' => isset($_POST['products_shipping_delay'][$language_id]) ? HTML::sanitize($_POST['products_shipping_delay'][$language_id]) : '',
        'products_shipping_delay_out_of_stock' => isset($_POST['products_shipping_delay_out_of_stock'][$language_id]) ? HTML::sanitize($_POST['products_shipping_delay_out_of_stock'][$language_id]) : '',
        'products_description_summary' => isset($_POST['products_description_summary'][$language_id]) ? HTML::sanitize(strip_tags($_POST['products_description_summary'][$language_id])) : '',
      ];

      if (is_numeric($id) && $action == 'Insert') {
        $insert_sql_data = [
          'products_id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $this->db->save('products_description', $sql_data_array);
//update products
      } else {
        $update_sql_data = [
          'products_id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $this->db->save('products_description', $sql_data_array, $update_sql_data);
      } // end action
    } //end for
  }

  /**
   * Retrieves the product model from the input or generates a random one if not provided.
   * @return string The sanitized or generated product model.
   */
  public function getProductModel(): string
  {
    if (empty($_POST['products_model'])) {
      $model = HTML::generateRandomNumber(9); //create a random number

      $products_model = \defined('CONFIGURATION_PREFIX_MODEL') ? CONFIGURATION_PREFIX_MODEL . $model : '';
    } else {
      $products_model = HTML::sanitize($_POST['products_model']);
    }

    return $products_model;
  }

  /**
   * Retrieves the SKU (Stock Keeping Unit) of the product based on user input or default model value.
   * @return string The product SKU
   */
  public function getProductSKU(): string
  {
    if (empty($_POST['products_sku'])) {
      $products_sku = $this->getProductModel();
    } elseif ($_POST['products_sku'] != $this->getProductModel()) {
      $products_sku = HTML::sanitize($_POST['products_sku']);
    } else {
      $products_sku = $this->getProductModel();
    }

    return $products_sku;
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
    if (empty($_POST['products_ean'])) {
      $products_ean = $this->getProductSKU();
    } elseif ($_POST['products_ean'] != $this->getProductSKU()) {
      $products_ean = HTML::sanitize($_POST['products_ean']);
    } else {
      $products_ean = $this->getProductSKU();
    }

    return $products_ean;
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
   * Check for duplicate product images in the database
   * @param $id - product id of the product
   * @return int - total count of duplicate images
   */
  public function checkProductImage(int $id): int
  {
    $Qimage = $this->getImage($id);

    $QduplicateImage = $this->db->prepare('select count(*) as total
                                           from :table_products
                                           where products_image = :products_image
                                           or products_image_zoom = :products_image_zoom
                                           or products_image_medium = :products_image_medium
                                           or products_image_small = :products_image_small
                                          ');
    $QduplicateImage->bindValue(':products_image', $Qimage['products_image']);
    $QduplicateImage->bindValue(':products_image_zoom', $Qimage['products_image_zoom']);
    $QduplicateImage->bindValue(':products_image_medium', $Qimage['products_image_medium']);
    $QduplicateImage->bindValue(':products_image_small', $Qimage['products_image_small']);

    $QduplicateImage->execute();

    return $QduplicateImage->valueInt('total');
  }

  /**
   * Checks and counts the number of categories using the same product image.
   * @param $id - the ID of the product
   * @return int - the total count of categories sharing the same image
   */
  public function checkCategoriesImage(int $id): int
  {
    $Qimage = $this->getImage($id);

    $Qchek = $this->db->prepare('select count(*) as total
                                 from :table_categories
                                 where categories_image = :products_image
                                 or categories_image = :products_image_zoom
                                 or categories_image = :products_image_medium
                                 or categories_image = :products_image_small
                                ');
    $Qchek->bindValue(':products_image', $Qimage['products_image']);
    $Qchek->bindValue(':products_image_zoom', $Qimage['products_image_zoom']);
    $Qchek->bindValue(':products_image_medium', $Qimage['products_image_medium']);
    $Qchek->bindValue(':products_image_small', $Qimage['products_image_small']);

    $Qchek->execute();

    return $Qchek->valueint('total');
  }

  /**
   * Check for duplicate image descriptions in the products description table
   *
   * @param $id - The ID of the product whose image descriptions are being checked
   * @return int - The total count of duplicate occurrences found
   */
  public function checkImagesDescription($id): int
  {
    $Qimage = $this->getImage($id);

    $Qchek = $this->db->prepare('select count(*) as total
                                                               from :table_products_description
                                                               where products_description like :products_description
                                                               or products_description like :products_description1
                                                               or products_description like :products_description2
                                                               or products_description like :products_description3
                                                              ');
    $Qchek->bindValue(':products_description', '%' . $Qimage['products_image'] . '%');
    $Qchek->bindValue(':products_description1', '%' . $Qimage['products_image_zoom'] . '%');
    $Qchek->bindValue(':products_description2', '%' . $Qimage['products_image_medium'] . '%');
    $Qchek->bindValue(':products_description3', '%' . $Qimage['products_image_small'] . '%');

    $Qchek->execute();

    return $Qchek->valueInt('total');
  }

  /**
   * Checks for duplicate banner images associated with a specified product.
   * @param $id - ID of the product
   * @return int - Count of duplicate banner images
   */
  public function checkBannerImages($id): int
  {
    $Qimage = $this->getImage($id);

    $Qchek = $this->db->prepare('select count(*) as total
                                                     from :table_banners
                                                     where banners_image = :products_image
                                                     or banners_image = :products_image_zoom
                                                     or banners_image = :products_image_medium
                                                     or banners_image = :products_image_small
                                                    ');

    $Qchek->bindValue(':products_image', $Qimage['products_image']);
    $Qchek->bindValue(':products_image_zoom', $Qimage['products_image_zoom']);
    $Qchek->bindValue(':products_image_medium', $Qimage['products_image_medium']);
    $Qchek->bindValue(':products_image_small', $Qimage['products_image_small']);

    $Qchek->execute();

    return $Qchek->valueInt('total');
  }

  /**
   * Checks for duplicate manufacturer images in the database
   *
   * @param $id - The ID of the product to check associated manufacturer images
   * @return int - Returns the count of manufacturers that have a duplicate image
   */
  public function checkManufacturerImages($id): int
  {
    $Qimage = $this->getImage($id);

    $Qchek = $this->db->prepare('select count(*) as total
                                                         from :table_manufacturers
                                                         where manufacturers_image = :products_image
                                                         or manufacturers_image = :products_image_zoom
                                                         or manufacturers_image = :products_image_medium
                                                         or manufacturers_image = :products_image_small
                                                        ');
    $Qchek->bindValue(':products_image', $Qimage['products_image']);
    $Qchek->bindValue(':products_image_zoom', $Qimage['products_image_zoom']);
    $Qchek->bindValue(':products_image_medium', $Qimage['products_image_medium']);
    $Qchek->bindValue(':products_image_small', $Qimage['products_image_small']);

    $Qchek->execute();

    return $Qchek->valueInt('total');
  }

  /**
   * Checks for duplicate supplier images in the database.
   *
   * @param mixed $id The identifier of the supplier whose images are being checked.
   * @return int The count of duplicate images found in the suppliers table.
   */
  public function checkSupplierImages($id): int
  {
    $Qimage = $this->getImage($id);

    $Qchek = $this->db->prepare('select count(*) as total
                                                     from :table_suppliers
                                                     where suppliers_image  = :products_image
                                                     or suppliers_image  = :products_image_zoom
                                                     or suppliers_image  = :products_image_medium
                                                     or suppliers_image  = :products_image_small
                                                    ');
    $Qchek->bindValue(':products_image', $Qimage['products_image']);
    $Qchek->bindValue(':products_image_zoom', $Qimage['products_image_zoom']);
    $Qchek->bindValue(':products_image_medium', $Qimage['products_image_medium']);
    $Qchek->bindValue(':products_image_small', $Qimage['products_image_small']);

    $Qchek->execute();

    return $Qchek->valueInt('total');
  }

  /**
   * Removes a product and its associated data from the system.
   *
   * @param int $id The unique identifier of the product to be removed.
   * @return void
   */
  public function removeProduct(int $id): void
  {
    $Qimage = $this->getImage($id);

    $check_image_total = $this->checkProductImage($id);
    $check_image_categories_total = $this->checkCategoriesImage($id);
    $check_image_product_description_total = $this->checkImagesDescription($id);
    $check_image_banners_total = $this->checkBannerImages($id);
    $check_image_manufacturers_total = $this->checkManufacturerImages($id);
    $check_image_suppliers_total = $this->checkSupplierImages($id);

    if (($check_image_total < 2) &&
      ($check_image_categories_total == 0) &&
      ($check_image_product_description_total == 0) &&
      ($check_image_banners_total == 0) &&
      ($check_image_manufacturers_total == 0) &&
      ($check_image_suppliers_total == 0)) {

      if (file_exists($this->template->getDirectoryPathTemplateShopImages() . $Qimage['products_image'])) {
        unlink($this->template->getDirectoryPathTemplateShopImages() . $Qimage['products_image']);
      }

      if (file_exists($this->template->getDirectoryPathTemplateShopImages() . $Qimage['products_image_zoom'])) {
        unlink($this->template->getDirectoryPathTemplateShopImages() . $Qimage['products_image_zoom']);
      }

      if (file_exists($this->template->getDirectoryPathTemplateShopImages() . $Qimage['products_image_medium'])) {
        unlink($this->template->getDirectoryPathTemplateShopImages() . $Qimage['products_image_medium']);
      }

      if (file_exists($this->template->getDirectoryPathTemplateShopImages() . $Qimage['products_image_small'])) {
        unlink($this->template->getDirectoryPathTemplateShopImages() . $Qimage['products_image_small']);
      }
    }

    $Qimages = $this->db->get('products_images', 'image', ['products_id' => $id]);

    if ($Qimages->fetch() !== false) {
      do {
        $sql_array = [
          'image' => $Qimages->value('image'),
          'products_id' => [
            'op' => '!=',
            'val' => (int)$id
          ]
        ];

        $QcheckImage = $this->db->get('products_images', 'id', $sql_array, null, 1);

        if ($QcheckImage->fetch() === false) {
          if (file_exists($this->template->getDirectoryPathTemplateShopImages() . $Qimages->value('image'))) {
            unlink($this->template->getDirectoryPathTemplateShopImages() . $Qimages->value('image'));
          }
        }
      } while ($Qimages->fetch());

      $this->db->delete('products_images', ['products_id' => $id]);
    }

    $this->db->delete('products', ['products_id' => $id]);
    $this->db->delete('products_description', ['products_id' => $id]);
    $this->db->delete('products_to_categories', ['products_id' => $id]);
    $this->db->delete('products_notifications', ['products_id' => $id]);

    $Qdelete = $this->db->prepare('delete
                                   from :table_customers_basket
                                   where products_id = :products_id
                                   or products_id like :products_id_att
                                ');
    $Qdelete->bindInt(':products_id', $id);
    $Qdelete->bindInt(':products_id_att', $id . '{%');
    $Qdelete->execute();

    $Qdel = $this->db->prepare('delete
                                from :table_customers_basket_attributes
                                where products_id = :products_id
                                or products_id like :products_id_att
                               ');
    $Qdel->bindInt(':products_id', $id);
    $Qdel->bindInt(':products_id_att', $id . '{%');
    $Qdel->execute();

    $this->hooks->call('Products', 'RemoveProduct', ['products_id' => $id]);

    Cache::clear('categories');
    Cache::clear('products-also_purchased');
    Cache::clear('upcoming');
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
   * Prepares the cloning of products into other categories.
   *
   * @param int $id The ID of the product to be cloned.
   * @param int $categories_id The ID of the category or categories where the product will be cloned.
   *
   * @return void
   */
  private function prepareCloneProducts(int $id, int $categories_id): void
  {
    $new_category = $categories_id;

    if (is_array($new_category) && isset($new_category)) {
      foreach ($new_category as $value_id) {
        $this->cloneProductsInOtherCategory($id, $value_id);
      }
    }
  }

  /**
   * Clones a product into a specified category or multiple categories, including associated data such as attributes,
   * images, and descriptions. This ensures that the product is replicated in the desired category with all its
   * properties preserved.
   *
   * @param int $id The ID of the product to be cloned.
   * @param mixed $new_categories_id The ID of the category (or categories) where the product will be cloned. Can be an integer or string.
   *
   * @return void
   */
  public function cloneProductsInOtherCategory(int $id, mixed $new_categories_id): void
  {
    if (!is_numeric($new_categories_id)) {
      $new_categories_id = 0;
    }

    $multi_clone_categories_id_to = [];

    $multi_clone_categories_id_to[] = $new_categories_id;

    $Qproducts = $this->db->prepare('select *
                                      from :table_products
                                      where products_id = :products_id
                                     ');
    $Qproducts->bindInt(':products_id', $id);

    $Qproducts->execute();

    for ($i = 0, $iMax = count($multi_clone_categories_id_to); $i < $iMax; $i++) {
      $clone_categories_id_to = $multi_clone_categories_id_to[$i];

      $sql_array = [
        'parent_id' => (int)$Qproducts->valueInt('parent_id'),
        'has_children' => (int)$Qproducts->valueInt('has_children'),
        'products_quantity' => (int)$Qproducts->valueInt('products_quantity'),
        'products_model' => $Qproducts->value('products_model'),
        'products_ean' => $Qproducts->value('products_ean'),
        'products_sku' => $Qproducts->value('products_sku'),
        'products_jan' => $Qproducts->value('products_jan'),
        'products_isbn' => $Qproducts->value('products_isbn'),
        'products_mpn' => $Qproducts->value('products_mpn'),
        'products_upc' => $Qproducts->value('products_upc'),
        'products_image' => $Qproducts->value('products_image'),
        'products_image_zoom' => $Qproducts->value('products_image_zoom'),
        'products_price' => (float)$Qproducts->value('products_price'),
        'products_date_added' => 'now()',
        'products_date_available' => (empty($Qproducts->value('products_date_available')) ? "null" : "'" . $Qproducts->value('products_date_available') . "'"),
        'products_weight' => (float)$Qproducts->value('products_weight'),
        'products_price_kilo' => (float)$Qproducts->value('products_price_kilo'),
        'products_status' => $Qproducts->value('products_status'),
        'products_tax_class_id' => (int)$Qproducts->valueInt('products_tax_class_id'),
        'products_view' => (int)$Qproducts->valueInt('products_view'),
        'orders_view' => (int)$Qproducts->valueInt('orders_view'),
        'products_min_qty_order' => (int)$Qproducts->valueInt('products_min_qty_order'),
        'admin_user_name' => AdministratorAdmin::getUserAdmin(),
        'products_only_online' => (int)$Qproducts->valueInt('products_only_online'),
        'products_image_medium' => $Qproducts->value('products_image_medium'),
        'products_cost' => (float)$Qproducts->value('products_cost'),
        'products_handling' => (int)$Qproducts->value('products_handling'),
        'products_packaging' => (int)$Qproducts->valueInt('products_packaging'),
        'products_sort_order' => (int)$Qproducts->valueInt('products_sort_order'),
        'products_quantity_alert' => (int)$Qproducts->valueInt('products_quantity_alert'),
        'products_image_small' => $Qproducts->value('products_image_small'),
        'products_type' => $Qproducts->value('products_type')
      ];

// copy du produit
      $this->db->save('products', $sql_array);
      $dup_products_id = $this->db->lastInsertId();

      // ---------------------
      // gallery
      // ----------------------
      $QproductImage = $this->db->prepare('select *
                                            from :table_products_images
                                            where products_id = :products_id
                                          ');
      $QproductImage->bindInt(':products_id', $id);

      $QproductImage->execute();

      while ($QproductImage->fetch()) {
        $sql_array = [
          'products_id' => (int)$dup_products_id,
          'image' => $QproductImage->value('image'),
          'htmlcontent' => $QproductImage->value('htmlcontent'),
          'sort_order' => $QproductImage->valueInt('sort_order')
        ];

        $this->db->save('products_images', $sql_array);
      }

      // ---------------------
      // Description clonage
      // ----------------------
      $Qdescription = $this->db->prepare('select language_id,
                                                    products_name,
                                                    products_description,
                                                    products_description_summary,
                                                    products_seo_url,
                                                    products_head_title_tag,
                                                    products_head_desc_tag,
                                                    products_head_keywords_tag,
                                                    products_url,
                                                    products_head_tag,
                                                    products_shipping_delay,
                                                    products_shipping_delay_out_of_stock
                                             from :table_products_description
                                             where products_id = :products_id
                                            ');
      $Qdescription->bindInt(':products_id', $id);

      $Qdescription->execute();

      while ($Qdescription->fetch()) {
        $sql_array = [
          'products_id' => (int)$dup_products_id,
          'language_id' => (int)$Qdescription->valueInt('language_id'),
          'products_name' => $Qdescription->value('products_name'),
          'products_description' => $Qdescription->value('products_description'),
          'products_seo_url' => $Qdescription->value('products_seo_url'),
          'products_head_title_tag' => $Qdescription->value('products_head_title_tag'),
          'products_head_desc_tag' => $Qdescription->value('products_head_desc_tag'),
          'products_head_keywords_tag' => $Qdescription->value('products_head_keywords_tag'),
          'products_url' => $Qdescription->value('products_url'),
          'products_viewed' => 0,
          'products_head_tag' => $Qdescription->value('products_head_tag'),
          'products_shipping_delay' => $Qdescription->value('products_shipping_delay'),
          'products_shipping_delay_out_of_stock' => $Qdescription->value('products_shipping_delay_out_of_stock'),
          'products_description_summary' => $Qdescription->value('products_description_summary')
        ];

        $this->db->save('products_description', $sql_array);
      }

      // ---------------------
      // insertion table
      // ----------------------
      $sql_array = [
        'products_id' => (int)$dup_products_id,
        'categories_id' => (int)$clone_categories_id_to
      ];

      $this->db->save('products_to_categories', $sql_array);

      $clone_products_id = $dup_products_id;

      // ---------------------
      // groupe client clonage
      // ----------------------
      $QcustomersGroup = $this->db->prepare('select distinct customers_group_id,
                                                               customers_group_name,
                                                               customers_group_discount
                                               from :table_customers_groups
                                               where customers_group_id >  0
                                               order by customers_group_id
                                              ');
      $QcustomersGroup->execute();

      while ($QcustomersGroup->fetch()) {
        $Qattributes = $this->db->prepare('select g.customers_group_id,
                                                     g.customers_group_price,
                                                     p.products_price
                                              from :table_products_groups g,
                                                   :table_products p
                                              where p.products_id = :products_id
                                              and p.products_id =g.products_id
                                              and g.customers_group_id = :customers_group_id
                                              order by g.customers_group_id
                                            ');
        $Qattributes->bindInt(':products_id', (int)$clone_products_id);
        $Qattributes->bindInt(':customers_group_id', (int)$QcustomersGroup->valueInt('customers_group_id'));

        $Qattributes->execute();

        if ($Qattributes->rowCount() > 0) {
            // Definir la position 0 ou 1 pour --> Affichage Prix public + Affichage Produit + Autorisation Commande
            // L'Affichage des produits, autorisation de commander et affichage des prix mis par defaut en valeur 1 dans la cas de la B2B desactive.
          if (MODE_B2B_B2C == 'True') {
            if (HTML::sanitize($_POST['price_group_view' . $QcustomersGroup->valueInt('customers_group_id')]) == 1) {
              $price_group_view = 1;
            } else {
              $price_group_view = 0;
            }

            if (HTML::sanitize($_POST['products_group_view' . $QcustomersGroup->valueInt('customers_group_id')]) == 1) {
              $products_group_view = 1;
            } else {
              $products_group_view = 0;
            }

            if (HTML::sanitize($_POST['orders_group_view' . $QcustomersGroup->valueInt('customers_group_id')]) == 1) {
              $orders_group_view = 1;
            } else {
              $orders_group_view = 0;
            }

            $products_quantity_unit_id_group = HTML::sanitize($_POST['products_quantity_unit_id_group' . $QcustomersGroup->valueInt('customers_group_id')]);
            $products_model_group = HTML::sanitize($_POST['products_model_group' . $QcustomersGroup->valueInt('customers_group_id')]);
            $products_quantity_fixed_group = HTML::sanitize($_POST['products_quantity_fixed_group' . $QcustomersGroup->valueInt('customers_group_id')]);
          } else {
            $price_group_view = 1;
            $products_group_view = 1;
            $orders_group_view = 1;
            $products_quantity_unit_id_group = 0;
            $products_model_group = '';
            $products_quantity_fixed_group = 1;
          }

          $Qupdate = $this->db->prepare('update :table_products_groups
                                            set price_group_view = :price_group_view,
                                                products_group_view = :products_group_view,
                                                orders_group_view = :orders_group_view,
                                                products_quantity_unit_id_group = :products_quantity_unit_id_group,
                                                products_model_group = :products_model_group,
                                                products_quantity_fixed_group = :products_quantity_fixed_group
                                            where customers_group_id = :customers_group_id
                                            and products_id = :products_id
                                            ');
          $Qupdate->bindInt(':price_group_view', $price_group_view);
          $Qupdate->bindInt(':products_group_view', $products_group_view);
          $Qupdate->bindInt(':orders_group_view', $orders_group_view);
          $Qupdate->bindInt(':products_quantity_unit_id_group', $products_quantity_unit_id_group);
          $Qupdate->bindValue(':products_model_group', $products_model_group);
          $Qupdate->bindValue(':products_quantity_fixed_group', $products_quantity_fixed_group);
          $Qupdate->bindInt(':customers_group_id', (int)$Qattributes->valueInt('customers_group_id'));
          $Qupdate->bindInt(':products_id', (int)$clone_products_id);

          $Qupdate->execute();

          // Prix TTC B2B ----------
          if ($_POST['price' . $QcustomersGroup->valueInt('customers_group_id')] <> $Qattributes->valueDecimal('customers_group_price') && $Qattributes->valueInt('customers_group_id') == $QcustomersGroup->valueInt('customers_group_id')) {
            $Qupdate = $this->db->prepare('update :table_products_groups
                                             set customers_group_price = :customers_group_price,
                                                 products_price = :products_price
                                             where customers_group_id = :customers_group_id
                                             and products_id = :products_id
                                          ');
            $Qupdate->bindInt(':customers_group_price', $_POST['price' . $QcustomersGroup->valueInt('customers_group_id')]);
            $Qupdate->bindInt(':products_price', $_POST['products_price']);
            $Qupdate->bindInt(':customers_group_id', (int)$Qattributes->valueInt('customers_group_id'));
            $Qupdate->bindInt(':products_id', (int)$clone_products_id);

            $Qupdate->execute();
          } elseif ($_POST['price' . $QcustomersGroup->valueInt('customers_group_id')] == $Qattributes->valueInt('customers_group_id')) {
            //              $attributes = $Qattributes->fetch();
          }
        // Prix + Afficher Prix public + Afficher Produit + Autoriser Commande
        } elseif (is_array($_POST['price' . $QcustomersGroup->valueInt('customers_group_id')])) {
          if ($_POST['price' . $QcustomersGroup->valueInt('customers_group_id')] != '') {
            $sql_array = [
              'products_id' => (int)$clone_products_id,
              'products_price' => (float)$_POST['products_price'],
              'customers_group_id' => (int)$QcustomersGroup->valueInt('customers_group_id'),
              'customers_group_price' => (float)$_POST['price' . $QcustomersGroup->valueInt('customers_group_id')],
              'price_group_view' => (int)$_POST['price_group_view' . $QcustomersGroup->valueInt('customers_group_id')],
              'products_group_view' => (int)$_POST['products_group_view' . $QcustomersGroup->valueInt('customers_group_id')],
              'orders_group_view' => (int)$_POST['orders_group_view' . $QcustomersGroup->valueInt('customers_group_id')],
              'products_quantity_unit_id_group' => (int)$_POST['products_quantity_unit_id_group' . $QcustomersGroup->valueInt('customers_group_id')],
              'products_model_group' => $_POST['products_model_group' . $QcustomersGroup->valueInt('customers_group_id')],
              'products_quantity_fixed_group' => (int)$_POST['products_quantity_fixed_group' . $QcustomersGroup->valueInt('customers_group_id')],
            ];

            $this->db->save('products_groups', $sql_array);
          }
        }
      } // end while

      $this->hooks->call('Products', 'CloneProducts', ['clone_products_id' => $clone_products_id]);
    } //End for
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
    if (isset($_POST['products_date_available']) && !empty($_POST['products_date_available'])) {
      $products_date_available = HTML::sanitize($_POST['products_date_available']);
      $products_date_available = (date('Y-m-d') < $products_date_available) ? $products_date_available : 'null';
    } else {
      $products_date_available = null;
    }

    if (isset($_POST['products_view']) && HTML::sanitize($_POST['products_view']) == 1) {
      $products_view = 1;
    } else {
      $products_view = 0;
    }

    if (isset($_POST['orders_view']) && HTML::sanitize($_POST['orders_view']) == 1) {
      $orders_view = 1;
    } else {
      $orders_view = 0;
    }

// display price / kg
    if (isset($_POST['products_price_kilo']) && HTML::sanitize($_POST['products_price_kilo']) == 1) {
      $products_price_kilo = 1;
    } else {
      $products_price_kilo = 0;
    }

// display products online
    if (isset($_POST['products_only_online']) && HTML::sanitize($_POST['products_only_online']) == 1) {
      $products_only_online = 1;
    } else {
      $products_only_online = 0;
    }

// display products store (physical)
    if (isset($_POST['products_only_shop']) && HTML::sanitize($_POST['products_only_shop']) == 1) {
      $products_only_shop = 1;
    } else {
      $products_only_shop = 0;
    }

// display products file public or private
    if (isset($_POST['products_download_public']) && HTML::sanitize($_POST['products_download_public']) == 1) {
      $products_download_public = 1;
    } else {
      $products_download_public = 0;
    }

// manual price B2B
    if (isset($_POST['products_percentage']) && $_POST['products_percentage'] == 'on') {
      $products_percentage = 0;
    } else {
      $products_percentage = 1;
    }

    if (MODE_B2B_B2C == 'False') {
      $products_view = 1;
      $orders_view = 1;
      $products_percentage = 1;
    }

    $products_model = $this->getProductModel();

    $products_sku = $this->getProductSKU();
    $products_ean = $this->getProductEAN();

    if (isset($_POST['products_status'])) {
      $products_status = HTML::sanitize($_POST['products_status']);
    } else {
      $products_status = 0;
    }

    $sql_data_array = [
      'products_quantity' => (int)HTML::sanitize($_POST['products_quantity'] ?? ''),
      'products_ean' => HTML::sanitize($products_ean),
      'products_model' => HTML::sanitize($products_model),
      'products_sku' => HTML::sanitize($products_sku),
      'products_price' => (float)HTML::sanitize($_POST['products_price'] ?? ''),
      'products_date_available' => $products_date_available,
      'products_weight' => (float)HTML::sanitize($_POST['products_weight'] ?? ''),
      'products_price_kilo' => HTML::sanitize($products_price_kilo),
      'products_status' => (int)HTML::sanitize($products_status),
      'products_percentage' => (int)$products_percentage,
      'products_view' => (int)$products_view,
      'orders_view' => (int)$orders_view,
      'products_tax_class_id' => (int)HTML::sanitize($_POST['products_tax_class_id'] ?? ''),
      'products_min_qty_order' => (int)($_POST['products_min_qty_order'] ?? ''),
      'admin_user_name' => AdministratorAdmin::getUserAdmin(),
      'products_only_online' => (int)HTML::sanitize($products_only_online),
      'products_cost' => (float)HTML::sanitize($_POST['products_cost'] ?? ''),
      'products_handling' => (float)HTML::sanitize($_POST['products_handling'] ?? ''),
      'products_packaging' => (int)HTML::sanitize($_POST['products_packaging'] ?? ''),
      'products_sort_order' => (int)HTML::sanitize($_POST['products_sort_order'] ?? ''),
      'products_quantity_alert' => (int)HTML::sanitize($_POST['products_quantity_alert'] ?? ''),
      'products_only_shop' => (int)HTML::sanitize($products_only_shop),
      'products_download_public' => (int)HTML::sanitize($products_download_public),
      'products_type' => HTML::sanitize($_POST['products_type'] ?? ''),
      'products_jan' => HTML::sanitize($_POST['products_jan'] ?? ''),
      'products_isbn' => HTML::sanitize($_POST['products_isbn'] ?? ''),
      'products_mpn' => HTML::sanitize($_POST['products_mpn'] ?? ''),
      'products_upc' => HTML::sanitize($_POST['products_upc'] ?? '')
    ];

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
    $this->saveProductsDescription($id, $action);

    if (isset($_POST['clone_categories_id_to'])) {
      $categories_id = $_POST['clone_categories_id_to'];
      $this->prepareCloneProducts($id, $categories_id);
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
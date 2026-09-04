<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\SubProductAdmin;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

/**
 * ProductRemover Class
 *
 * Deletes a product and all its associated rows (images, descriptions, category
 * links, notifications, basket entries) and removes orphaned image files.
 * Extracted from ProductsAdmin (removeProduct + the six check*Image helpers,
 * cyclo 15) for the Products god-class decomposition. db/template/hooks come from
 * the Registry; the product image row is resolved once by the caller (getImage,
 * which stays in ProductsAdmin with its 47 callers) and passed in.
 */
class ProductRemover
{
  private mixed $db;
  private mixed $template;
  private mixed $hooks;

  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->template = Registry::get('TemplateAdmin');
    $this->hooks = Registry::get('Hooks');
  }

  /**
   * Check for duplicate product images in the database
   * @param $id - product id of the product
   * @return int - total count of duplicate images
   */
  public function checkProductImage(array $image): int
  {
    $Qimage = $image;

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
  public function checkCategoriesImage(array $image): int
  {
    $Qimage = $image;

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
  public function checkImagesDescription(array $image): int
  {
    $Qimage = $image;

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
  public function checkBannerImages(array $image): int
  {
    $Qimage = $image;

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
  public function checkManufacturerImages(array $image): int
  {
    $Qimage = $image;

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
  public function checkSupplierImages(array $image): int
  {
    $Qimage = $image;

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
  public function remove(int $id, array $image): void
  {
    // Decided before the writes, deleted after the commit: unlink cannot be rolled back, and a
    // rolled-back removal would leave the surviving rows pointing at missing files.
    $orphaned_files = $this->collectOrphanedImageFiles($id, $image);

    // Guarded: a caller may already own one.
    $owns_transaction = !$this->db->inTransaction();

    if ($owns_transaction) {
      $this->db->beginTransaction();
    }

    try {
      // Children FIRST, `products` LAST. No foreign key backs this schema: the reverse order
      // orphans every child the moment a later delete fails, and nothing would refuse them.
      $this->db->delete('products_images', ['products_id' => $id]);
      $this->db->delete('products_description', ['products_id' => $id]);
      $this->db->delete('products_to_categories', ['products_id' => $id]);
      $this->db->delete('products_notifications', ['products_id' => $id]);

      foreach (['customers_basket', 'customers_basket_attributes'] as $table) {
        $Qdelete = $this->db->prepare('delete
                                       from :table_' . $table . '
                                       where products_id = :products_id
                                       or products_id like :products_id_att
                                      ');
        $Qdelete->bindInt(':products_id', $id);
        $Qdelete->bindInt(':products_id_att', $id . '{%');
        $Qdelete->execute();
      }

      $this->db->delete('products', ['products_id' => $id]);

      if ($owns_transaction) {
        $this->db->commit();
      }
    } catch (\Throwable $e) {
      if ($owns_transaction) {
        $this->db->rollBack();
      }

      throw $e;
    }

    foreach ($orphaned_files as $file) {
      if (file_exists($file)) {
        unlink($file);
      }
    }

    $this->hooks->call('Products', 'RemoveProduct', ['products_id' => $id]);

    Cache::clear('categories');
  }

  /**
   * Image files no other row still points at, resolved BEFORE the deletes while the rows are
   * readable, and unlinked only once the removal is committed.
   *
   * @param int $id The product id being removed.
   * @param array $image The product image row, resolved by the caller.
   * @return array<int, string> Absolute paths.
   */
  private function collectOrphanedImageFiles(int $id, array $image): array
  {
    $path = $this->template->getDirectoryPathTemplateShopImages();
    $files = [];

    if (($this->checkProductImage($image) < 2) &&
      ($this->checkCategoriesImage($image) == 0) &&
      ($this->checkImagesDescription($image) == 0) &&
      ($this->checkBannerImages($image) == 0) &&
      ($this->checkManufacturerImages($image) == 0) &&
      ($this->checkSupplierImages($image) == 0)) {

      foreach (['products_image', 'products_image_zoom', 'products_image_medium', 'products_image_small'] as $key) {
        if (!empty($image[$key])) {
          $files[] = $path . $image[$key];
        }
      }
    }

    $Qimages = $this->db->get('products_images', 'image', ['products_id' => $id]);

    while ($Qimages->fetch() !== false) {
      $sql_array = [
        'image' => $Qimages->value('image'),
        'products_id' => [
          'op' => '!=',
          'val' => (int)$id
        ]
      ];

      $QcheckImage = $this->db->get('products_images', 'id', $sql_array, null, 1);

      if ($QcheckImage->fetch() === false) {
        $files[] = $path . $Qimages->value('image');
      }
    }

    return $files;
  }
}

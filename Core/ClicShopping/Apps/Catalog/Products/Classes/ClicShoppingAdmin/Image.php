<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Upload;
use function is_null;
use function strlen;

/**
 * Class Image
 *
 * Handles operations related to image processing for products in the ClicShoppingAdmin module.
 */
class Image
{
  private string $rootImagesDir;
  private mixed $db;
  private mixed $template;
  private mixed $imageResample;
  private mixed $products_image;
  private mixed $products_image_zoom;
  private mixed $products_image_medium;
  private mixed $products_image_small;

  /**
   * Initializes the class by setting up the template, database connection,
   * root image directory, and the ImageResample component.
   *
   * @return void
   */
  public function __construct()
  {
    $this->template = Registry::get('TemplateAdmin');
    $this->db = Registry::get('Db');
    $this->rootImagesDir = $this->template->getDirectoryPathTemplateShopImages() . 'products/';

    Registry::set('ImageResample', new ImageResample());
    $this->imageResample = Registry::get('ImageResample');
  }

  /**
   * Generates a random alphanumeric string of a specified length.
   *
   * @param int $length The length of the random string to be generated. Defaults to 10.
   * @return string The generated random alphanumeric string.
   */
  public function getGenerateRandomString(int $length = 10): string
  {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  /**
   * Cleans and sanitizes an image name by removing unwanted characters,
   * HTML entities, and specific directory paths.
   *
   * @param string $image_name The original image name to be cleaned and sanitized.
   * @return string The sanitized and cleaned image name.
   */
  public function cleanImageName(string $image_name): string
  {
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $products_image_name = HTML::sanitize($image_name);
    $products_image_name = htmlspecialchars($products_image_name, ENT_QUOTES | ENT_HTML5);
    $products_image_name = HTML::replaceString($CLICSHOPPING_Template->getDirectoryShopTemplateImages(), '', $products_image_name);
    $products_image_name_end = strstr($products_image_name, '&quot;');
    $products_image_name = HTML::replaceString($products_image_name_end, '', $products_image_name);
    $products_image_name = HTML::replaceString($CLICSHOPPING_Template->getDirectoryShopSources(), '', $products_image_name);

    return $products_image_name;
  }

  /**
   * Creates a new directory for product images based on supplied POST data,
   * ensuring proper sanitization and formatting of directory names.
   *
   * The method handles the creation of directories if they do not already exist,
   * modifies directory names to be lowercase and replaces spaces with underscores,
   * and combines base directories as necessary.
   *
   * @return string The path to the created or processed directory.
   */
  private function createDirectory(): string
  {
    if (isset($_POST['new_directory_products_image']) && !empty($_POST['new_directory_products_image'])) {
      $new_dir_products_image_without_accents = HTML::removeFileAccents($_POST['new_directory_products_image']);
      $new_dir_products_image = mb_strtolower($new_dir_products_image_without_accents);
      $new_dir_products_image = HTML::replaceString(' ', '_', $new_dir_products_image);

      if (!is_dir($new_dir_products_image)) {
        @mkdir($this->rootImagesDir . $new_dir_products_image, 0755, true);
        @chmod($this->rootImagesDir . $new_dir_products_image, 0755);
      }
      if (isset($_POST['directory_products_image']) && !empty($_POST['directory_products_image'])) {
        $new_dir_products_image = $new_dir_products_image . '/' . HTML::sanitize($_POST['directory_products_image']);
      }

    } else {
      $new_dir_products_image = HTML::sanitize($_POST['directory_products_image']);
    }

    if (empty($new_dir_products_image)) {
      $dir_products_image = 'products/' . $new_dir_products_image;
    } else {
      $dir_products_image = 'products/' . $new_dir_products_image . '/';
    }

    return $dir_products_image;
  }

  /**
   * Processes an image and converts its extension to WebP if configured to do so.
   * If the image is not already in WebP format, the image is resampled, saved as WebP,
   * and the original file is removed.
   *
   * @param string $image The path or name of the original image file to process.
   * @return string The image path or name with the WebP extension, if converted.
   */
  protected function getImageExtensionWebp(string $image): string
  {
    if (\defined('CONFIGURATION_CONVERT_IMAGE') && CONFIGURATION_CONVERT_IMAGE == 'true') {
      $p = pathinfo($this->template->getDirectoryPathTemplateShopImages() . $image);
      $ext = mb_strtolower($p['extension']);

      $big_image_resized_path = $this->template->getDirectoryPathTemplateShopImages() . $image;

      if ($ext != 'webp') {
        if ($img = imagecreatefromstring(file_get_contents($big_image_resized_path))) {
          $image = str_replace($ext, 'webp', $image);

          $this->imageResample->save($this->template->getDirectoryPathTemplateShopImages() . $image, $ext);
          unset($img);
        }

        if (file_exists($big_image_resized_path)) {
          unlink($big_image_resized_path);
        }
      }
    }

    return $image;
  }

  /**
   * Processes and resizes product images, generating necessary image variations
   * (zoom, medium, small, and admin sizes) for a product based on specified parameters.
   * Handles image upload, validation, resizing, saving, and cleanup of original files.
   * Updates product image data arrays or handles image deletion as per user interactions.
   *
   * @return void
   */
  public function getImage()
  {
    if (isset($_GET['pID'])) {
      $Qimages = $this->db->prepare('select products_image,
                                              products_image_medium,
                                              products_image_zoom,
                                              products_image_small
                                       from :table_products
                                       where products_id = :products_id
                                      ');
      $Qimages->bindInt(':products_id', $_GET['pID']);
      $Qimages->execute();

      $product_update_image = $Qimages->value('products_image');
      $product_update_image_zoom = $Qimages->value('products_image_zoom');
      $product_update_image_medium = $Qimages->value('products_image_medium');
      $product_update_image_small = $Qimages->value('products_image_small');
    } else {
      $product_update_image = '';
      $product_update_image_zoom = '';
      $product_update_image_medium = '';
      $product_update_image_small = '';
    }

    $dir_products_image = $this->createDirectory();
    $rand_image = $this->getGenerateRandomString();

    $error = true;
//
// load original image
//
    $image = new Upload('products_image_resize', $this->template->getDirectoryPathTemplateShopImages() . $dir_products_image, null, ['gif', 'jpg', 'png', 'jpeg', 'webp']);
    $sql_data_array = [];

    if ($image->check() && $image->save()) {
      $error = false;
    }

    if ($error === false) {
      $sql_data_array['image'] = $dir_products_image . $image->getFilename();
    } else {
      $sql_data_array['image'] = '';
    }

    if ($error === false) {
      $filename_image_name = $image->getFilename();

      $this->imageResample->load($this->template->getDirectoryPathTemplateShopImages() . $dir_products_image . $filename_image_name);

//get image type
      $ext = exif_imagetype($this->template->getDirectoryPathTemplateShopImages() . $dir_products_image . $filename_image_name);

      $image_name = HTML::removeFileAccents($filename_image_name);
      $image_name = HTML::replaceString(' ', '', $image_name);

//
// Zoom image
//
      if (empty(\defined('BIG_IMAGE_WIDTH') ? BIG_IMAGE_WIDTH : '')) {
        $big_catalg_image_with = 640;
      } else {
        $big_catalg_image_with = \defined('BIG_IMAGE_WIDTH') ? (int)BIG_IMAGE_WIDTH : '';
      }

      $this->imageResample->resizeToWidth($big_catalg_image_with);

      if (empty($big_catalg_image_with)) {
        $big_image_width = '_big';
      } else {
        $big_image_width = $big_catalg_image_with;
      }

      $big_image_resized = $dir_products_image . $big_image_width . '_' . $rand_image . '_' . $image_name;

      $this->imageResample->save($this->template->getDirectoryPathTemplateShopImages() . $big_image_resized, $ext);

      $big_image_resized = $this->getImageExtensionWebp($big_image_resized);
//
// medium image
//
      if (empty(\defined('MEDIUM_IMAGE_WIDTH') ? MEDIUM_IMAGE_WIDTH : '')) {
        $medium_catalog_image_with = 250;
      } else {
        $medium_catalog_image_with = \defined('MEDIUM_IMAGE_WIDTH') ? (int)MEDIUM_IMAGE_WIDTH : '';
      }

      $this->imageResample->resizeToWidth($medium_catalog_image_with);

      if (empty($medium_catalog_image_with)) {
        $medium_image_width = '_medium';
      } else {
        $medium_image_width = $medium_catalog_image_with;
      }

      $medium_image_resized = $dir_products_image . $medium_image_width . '_' . $rand_image . '_' . $image_name;

      $this->imageResample->save($this->template->getDirectoryPathTemplateShopImages() . $medium_image_resized, $ext);

      $medium_image_resized = $this->getImageExtensionWebp($medium_image_resized);
//
// medium image
//
      if (empty(\defined('SMALL_IMAGE_WIDTH') ? SMALL_IMAGE_WIDTH : '')) {
        $small_catalog_image_with = 130;
      } else {
        $small_catalog_image_with = \defined('SMALL_IMAGE_WIDTH') ? (int)SMALL_IMAGE_WIDTH : '';
      }

      $this->imageResample->resizeToWidth($small_catalog_image_with);

      if (empty($small_catalog_image_with)) {
        $small_image_width = '_small';
      } else {
        $small_image_width = (int)$small_catalog_image_with;
      }

      $small_image_resized = $dir_products_image . $small_image_width . '_' . $rand_image . '_' . $image_name;

      $this->imageResample->save($this->template->getDirectoryPathTemplateShopImages() . $small_image_resized, $ext);

      $small_image_resized = $this->getImageExtensionWebp($small_image_resized);

//
// Admin Image
//
      if (empty(\defined('SMALL_IMAGE_WIDTH_ADMIN') ? SMALL_IMAGE_WIDTH_ADMIN : '')) {
        $small_admin_image_with = 70;
      } else {
        $small_admin_image_with = \defined('SMALL_IMAGE_WIDTH_ADMIN') ? (int)SMALL_IMAGE_WIDTH_ADMIN : '';
      }

      $this->imageResample->resizeToWidth($small_admin_image_with);

      if (empty($small_admin_image_with)) {
        $small_image_admin_width = '_small_admin';
      } else {
        $small_image_admin_width = (int)$small_admin_image_with;
      }

      $small_image_admin_resized = $dir_products_image . $small_image_admin_width . '_' . $rand_image . '_' . $image_name;

      $this->imageResample->save($this->template->getDirectoryPathTemplateShopImages() . $small_image_admin_resized, $ext);

      $small_image_admin_resized = $this->getImageExtensionWebp($small_image_admin_resized);

//
// delete the orginal files
//
      if (file_exists($this->template->getDirectoryPathTemplateShopImages() . $dir_products_image . $filename_image_name)) {
        unlink($this->template->getDirectoryPathTemplateShopImages() . $dir_products_image . $filename_image_name);
      }
    } else {
      $big_image_resized = '';
      $medium_image_resized = '';
      $small_image_resized = '';
      $small_image_admin_resized = '';
    }

    if (isset($_POST['delete_image'])) {
      $sql_data_array['products_image'] = null;
      $this->products_image = $sql_data_array['products_image'];
      $sql_data_array['products_image_zoom'] = null;
      $this->products_image_zoom = $sql_data_array['products_image_zoom'];
      $sql_data_array['products_image_medium'] = null;
      $this->products_image_medium = $sql_data_array['products_image_medium'];
      $sql_data_array['products_image_small'] = null;
      $this->products_image_small = $sql_data_array['products_image_small'];

    } else {
      if ((isset($_POST['products_image']) && !is_null($_POST['products_image'])) || !empty($small_image_resized) || !empty($small_image_admin_resized)) {
        $products_image_name = $this->cleanImageName($_POST['products_image']);
//
// small image catalog
//
        if (!empty($small_image_resized)) {
          $this->products_image = $small_image_resized;
        } else {
          $this->products_image = $products_image_name;
        }
      } else {
        $this->products_image = $product_update_image;
      }
//
// big image
//
      if (!empty($big_image_resized)) {
        $products_image_zoom_name = $this->cleanImageName($big_image_resized);

        if (!empty($big_image_resized)) {
          $this->products_image_zoom = $big_image_resized;
        } else {
          $this->products_image_zoom = $products_image_zoom_name;
        }
      } else {
        $this->products_image_zoom = $product_update_image_zoom;
      }

//
// medium image
//
      if (!empty($medium_image_resized)) {
        $products_image_medium_name = $this->cleanImageName($medium_image_resized);

        if (!empty($medium_image_resized)) {
          $this->products_image_medium = $medium_image_resized;
        } else {
          $this->products_image_medium = $products_image_medium_name;
        }
      } else {
        $this->products_image_medium = $product_update_image_medium;
      }

//
// small admin image
//
      if (!empty($small_image_admin_resized)) {
        $products_image_small_name_name = $this->cleanImageName($small_image_admin_resized);

        if (!empty($small_image_admin_resized)) {
          $this->products_image_small = $small_image_admin_resized;
        } else {
          $this->products_image_small = $products_image_small_name_name;
        }
      } else {
        $this->products_image_small = $product_update_image_small;
      }
    }
  }

  /**
   * Retrieves the product image file name.
   *
   * @return string|null Returns the product image file name as a string, or null if not set.
   */
  public function productsImage(): ?string
  {
    return $this->products_image;
  }

  /**
   * Retrieves the medium-sized product image.
   *
   * @return string|null Returns the medium-sized product image filename or null if not set.
   */
  public function productsImageMedium(): ?string
  {
    return $this->products_image_medium;
  }

  /**
   * Retrieves the zoomed image of a product if available.
   *
   * @return string|null Returns the zoomed product image as a string, or null if not set.
   */
  public function productsImageZoom(): ?string
  {
    return $this->products_image_zoom;
  }

  /**
   * Retrieves the small image associated with a product.
   *
   * @return string|null The small image filename or null if not set.
   */
  public function productsSmallImage(): ?string
  {
    return $this->products_image_small;
  }

  /**
   * Saves a gallery image for a product by handling file uploads, creating directories,
   * storing metadata, and maintaining image relationships in the database.
   * It supports creating new product image entries and updating existing ones,
   * while also cleaning up unused images from the database and file system.
   *
   * @param int $id The ID of the product for which the gallery image is being saved.
   * @return void
   */
  public function saveGalleryImage(int $id)
  {
    $root_images_dir = $this->rootImagesDir;

    $error = true;
    $dir = 'products';
    $separator = '';

// gallery
    if (isset($_POST['new_directory']) && !empty($_POST['new_directory'])) {
      $new_dir_without_accents = HTML::removeFileAccents($_POST['new_directory']);
      $new_dir = HTML::replaceString(' ', '_', $new_dir_without_accents);
      $new_dir = mb_strtolower($new_dir);
      $dir = 'products/' . $new_dir . '/' . HTML::sanitize($_POST['directory']);
    } else {
      if (!empty($_POST['directory_products_image'])) {
        $dir = 'products/' . HTML::sanitize($_POST['directory_products_image']) . '/';
      }
    }

    if (!empty($new_dir) && !is_dir($new_dir)) {
// depend server configuration
      @mkdir($root_images_dir . $new_dir, 0755, true);
      @chmod($root_images_dir . $new_dir, 0755);

      $separator = '/';
    }

    if (isset($_POST['directory']) && !is_null($_POST['directory'])) {
      $separator = '/';
    }

    $pi_sort_order = 0;
    $piArray = [0];

    foreach ($_FILES as $key => $value) {
// Update existing large product images

      if (preg_match('/^products_image_large_([0-9]+)$/', $key, $matches)) {
        $pi_sort_order++;

        $sql_data_array = ['htmlcontent' => $_POST['products_image_htmlcontent_' . $matches[1]],
          'sort_order' => (int)$pi_sort_order
        ];

        $image = new Upload($key, $this->template->getDirectoryPathTemplateShopImages() . $dir, null, ['gif', 'jpg', 'png', 'webp']);

        if ($image->check() && $image->save()) {
          $error = false;
        }

        if ($error === false) {
          $sql_data_array['image'] = $dir . $separator . $image->getFilename();
        }

        $this->db->save('products_images', $sql_data_array, [
            'products_id' => (int)$id,
            'id' => (int)$matches[1]
          ]
        );

        $piArray[] = (int)$matches[1];

      } elseif (preg_match('/^products_image_large_new_([0-9]+)$/', $key, $matches)) {
        // Insert new large product images
        $sql_data_array = [
          'products_id' => (int)$id,
          'htmlcontent' => $_POST['products_image_htmlcontent_new_' . $matches[1]]
        ];

        $image = new Upload($key, $this->template->getDirectoryPathTemplateShopImages() . $dir, null, ['gif', 'jpg', 'jepg', 'png', 'webp']);

        if ($image->check() && $image->save()) {
          $error = false;
        }

        $pi_sort_order++;

        if ($error === false) {
          $sql_data_array['image'] = $dir . $separator . $image->getFilename();
        }

        $sql_data_array['sort_order'] = (int)$pi_sort_order;
        $this->db->save('products_images', $sql_data_array);
        $piArray[] = $this->db->lastInsertId();
      } // end preg_match
    } // end foreach


//=======================================================================================
// bug supprimer products_image automatiquement
//
//========================================================================================

    $Qimages = $this->db->prepare('select image
                                     from :table_products_images
                                     where products_id = :products_id
                                     and id not in (' . implode(', ', $piArray) . ')
                                    ');
    $Qimages->bindInt(':products_id', $id);
    $Qimages->execute();

    if ($Qimages->fetch() !== false) {
      do {
        $Qcheck = $this->db->get('products_images', 'count(*) as total', ['image' => $Qimages->value('image')]);

        if ($Qcheck->valueInt('total') < 2) {
          if (file_exists($this->template->getDirectoryPathTemplateShopImages() . $Qimages->value('image'))) {
            unlink($this->template->getDirectoryPathTemplateShopImages() . $Qimages->value('image'));
          }
        }
      } while ($Qimages->fetch());

      $Qdel = $this->db->prepare('delete from :table_products_images
                                    where products_id = :products_id
                                    and id not in (' . implode(', ', $piArray) . ')
                                   ');
      $Qdel->bindInt(':products_id', $id);
      $Qdel->execute();
    }
  }

  /**
   * Retrieves the small image for a product in the admin panel. If a specific
   * small product image is unavailable, it falls back to using the default
   * product image.
   *
   * @param int $id The ID of the product for which the small image is to be retrieved.
   * @return string The HTML code for displaying the small product image.
   */
  public function getSmallImageAdmin(int $id): string
  {
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $Qimages = $this->db->prepare('select products_image,
                                             products_image_small
                                     from :table_products
                                     where products_id = :products_id
                                    ');
    $Qimages->bindInt(':products_id', $id);
    $Qimages->execute();

    if (!empty($Qimages->value('products_image_small'))) {
      $small_image = $Qimages->value('products_image_small');
    } else {
      $small_image = $Qimages->value('products_image');
    }

    $small_image = HTML::image($CLICSHOPPING_Template->getDirectoryShopTemplateImages() . $small_image, $Qimages->value('products_name'), \defined('SMALL_IMAGE_WIDTH_ADMIN') ? (int)SMALL_IMAGE_WIDTH_ADMIN : '', \defined('SMALL_IMAGE_HEIGHT_ADMIN') ? (int)SMALL_IMAGE_HEIGHT_ADMIN : '');

    return $small_image;
  }
}
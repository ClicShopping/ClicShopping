<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\EditDesign\Classes;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function in_array;

class Listing
{
  /**
   * Retrieves a list of template file names within a specified directory, filtered by specific file extensions, and
   * returns them as an array with additional metadata.
   *
   * This method scans the directory provided via POST or a fallback default directory for template files.
   * It identifies files with a '.php' extension, sorts them naturally in a case-insensitive manner,
   * and formats the resulting array with 'id' and 'text' keys for each file.
   *
   * @return array|null Returns an array of template file names formatted with 'id' and 'text' keys if files are found,
   *                    or null if no files are located or an error occurs.
   */
  public static function getFilenameTemplateProducts(): ?array
  {
    $directory_selected = '';

    if (isset($_POST['directory_html'])) {
      if (file_exists(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/modules/' . $directory_selected . '/template_html/')) {
        $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/modules/' . $directory_selected . '/template_html/';
      } else {
        $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/modules/';
      }
    } else {
      $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/modules/';
    }


    $found = []; //initialize an array for matching files
    $fileTypes = ['php']; // Create an array of file types
    $found = []; // Traverse the folder, and add filename to $found array if type matches
    $filename_array = [];

    /* if empty error is produced : Fatal error: Uncaught exception 'RuntimeException'*/
    $file_array = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($template_directory));

    foreach ($file_array as $filename => $current) {
      $fileInfo = pathinfo($current->getFileName());

      if (array_key_exists('extension', $fileInfo) && in_array($fileInfo['extension'], $fileTypes)) {
        $found[] = $current->getFileName();
      }
    }

    if ($found) { // Check the $found array is not empty
      natcasesort($found); // Sort in natural, case-insensitive order, and populate menu

      $filename_array[0] = [
        'id' => '0',
        'text' => CLICSHOPPING::getDef('text_selected')
      ];

      foreach ($found as $filename) {
        $filename_array[] = [
          'id' => $filename,
          'text' => $filename
        ];
      }
    }

    return $filename_array;
  }

  /*
    * Template_products Directory list
    * @param string $filename : name of the file
    * @return string $directory_array, the directories name in css directory
    *
  */
  /**
   * Retrieves a list of directories within the template modules directory, excluding specified entries.
   *
   * @return array An array of directories formatted with 'id' and 'text' keys. The first entry is a default selection option.
   */
  public static function getDirectoryTemplateProducts(): array
  {
    $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/modules/';

    $exclude = [
      '..',
      '.',
      'customers_address',
      'download',
      'index.php',
      '_htaccess',
      '.htaccess',
      'modules_account_customers',
      'modules_advanced_search',
      'modules_blog_content',
      'modules_boxes',
      'modules_checkout_confirmation',
      'modules_checkout_payment',
      'modules_checkout_shipping',
      'modules_checkout_success',
      'modules_contact_us',
      'modules_create_account',
      'modules_create_account_pro',
      'modules_footer_suffix',
      'modules_login',
      'modules_products_reviews',
      'modules_shopping_cart',
      'modules_sitemap',
      'modules_tell_a_friend',
    ];

    $directories = array_diff(scandir($template_directory), $exclude);

    $directory_array[0] = [
      'id' => '0',
      'text' => CLICSHOPPING::getDef('text_selected')
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
}

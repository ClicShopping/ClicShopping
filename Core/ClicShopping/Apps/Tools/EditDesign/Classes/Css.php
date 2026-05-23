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
use ClicShopping\OM\Registry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function in_array;

class Css
{
  /**
   * Retrieves a list of CSS file names from the specified directory. It dynamically looks for the
   * directory and files based on the selected directory input ($_POST or $_GET) and language configuration.
   * The method returns an array of file names that can be used, populating options with an id and text for display.
   *
   * @return array An array of CSS file names with each entry containing an 'id' and 'text' key.
   */
  public static function getFilenameCss(): array
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    if (isset($_POST['directory_css'])) {
      $directory_selected = HTML::sanitize($_POST['directory_css']);
    } else {
      $directory_selected = HTML::sanitize($_GET['directory_css']);
    }

    $directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/css/' . $CLICSHOPPING_Language->get('directory') . '/';

    if (!is_dir($directory)) {
      $directory_selected = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/css/english/' . $directory_selected;
    } else {
      $directory_selected = $directory . '/' . $directory_selected . '/';
    }

    $found = []; //initialize an array for matching files
    $fileTypes = ['css']; // Create an array of file types
    $found = []; // Traverse the folder, and add filename to $found array if type matches

    /* if empty error is produced : Fatal error: Uncaught exception 'RuntimeException'*/
    $file_array = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory_selected));

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

  /**
   * Retrieves a list of subdirectories within a specific CSS directory for the current language or falls back to 'english'.
   *
   * The method scans the specified CSS directory (based on the current language) for subdirectories, excluding unwanted items.
   * It constructs and returns an array of available directory options including a default 'selected' option.
   *
   * @return array Returns an array of subdirectories, each containing an 'id' and 'text' key.
   */
  public static function getDirectoryCss(): array
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $directory_array = [];
    $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/css/' . $CLICSHOPPING_Language->get('directory') . '/';

    if (!is_dir($template_directory)) {
      $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/css/english/';
    }

    $exclude = ['.', '..', '_notes', 'customers_address', 'download', 'index.php', '_htaccess', '.htaccess'];

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
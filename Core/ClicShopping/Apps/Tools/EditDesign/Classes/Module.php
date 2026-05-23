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

class Module
{
  /**
   * Retrieves an array of filenames from a specified directory and formats them
   * for use in a menu or selection list.
   *
   * The method sanitizes the selected directory input, checks the existence of the
   * appropriate folder, and searches for files of specific types within that directory.
   * It populates the result with filenames sorted in a natural, case-insensitive order.
   *
   * @return array The formatted array of filenames, where each array element is an
   * associative array containing an 'id' for the filename and 'text' for display.
   */

  public static function getFilenameHtml(): array
  {
    if (isset($_POST['directory_html'])) {
      $directory_selected = HTML::sanitize($_POST['directory_html']) . '/';
    } else {
      $directory_selected = HTML::sanitize($_GET['directory_html']) . '/';
    }

    if (file_exists(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/modules/' . $directory_selected . 'content/')) {
      $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/modules/' . $directory_selected . 'content/';
    } else {
      $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/modules/';
    }

    $found = []; //initialize an array for matching files
    $fileTypes = ['php']; // Create an array of file types
    $found = []; // Traverse the folder, and add filename to $found array if type matches

    /* if empty error is produced : Fatal error: Uncaught exception 'RuntimeException'*/
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($template_directory)) as $filename => $current) {
      $fileInfo = pathinfo($current->getFileName());

      if (array_key_exists('extension', $fileInfo) && \in_array($fileInfo['extension'], $fileTypes)) {
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
   * Retrieves an array of directories from a specified template directory, excluding certain predefined entries.
   * Each directory is returned as an associative array with an 'id' and 'text' key.
   *
   * @return array Returns an array containing directory information. The first entry includes a default 'text_selected' value, followed by valid directories within the template directory.
   */

  public static function getDirectoryHtml(): array
  {
    $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/modules/';

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

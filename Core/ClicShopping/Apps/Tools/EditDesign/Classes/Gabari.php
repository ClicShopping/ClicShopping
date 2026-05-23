<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\EditDesign\Classes;

use ClicShopping\OM\CLICSHOPPING;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function in_array;

class Gabari
{
  /**
   * Retrieves the filenames of specific file types (e.g., PHP files) from a designated directory
   * and organizes them into an array for display or selection purposes.
   *
   * @return array An array of filenames, each with an 'id' and 'text' key. The 'id' represents the
   *               filename, and the 'text' contains the same filename for display.
   *               The first element in the array is a default option with 'id' as '0' and
   *               text from a predefined constant.
   */
  public static function getFilenameGabari(): array
  {
    $filename_selected = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/files/';

    $found = []; //initialize an array for matching files
    $fileTypes = ['php']; // Create an array of file types
    $found = []; // Traverse the folder, and add filename to $found array if type matches

    /* if empty error is produced : Fatal error: Uncaught exception 'RuntimeException'*/
    $file_array = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filename_selected));

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
   * Retrieves a list of directories from the specified template directory, excluding certain predefined files or directories.
   *
   * @return array The array of directories, including an initial default option with predefined text and each directory containing its 'id' and 'text'.
   */
  public static function getDirectoryGabari(): array
  {
    $directory_array = [];
    $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/' . SITE_THEMA . '/files/';

    $exclude = ['.', '..', '_notes', 'index.php', '_htaccess', '.htaccess'];

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
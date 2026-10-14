<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\HTML;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use function in_array;
use function is_array;
/**
 * Retrieves a list of all template directories and generates a dropdown menu using these directories.
 *
 * @param string $value The selected value for the dropdown menu.
 * @return string Returns the HTML dropdown menu based on the directory names.
 */
class ModuleDownload
{
  /**
   * Retrieves a list of all valid template directories and generates an HTML select menu for configuration.
   *
   * @param string $value The current selected value for the configuration dropdown menu.
   * @return string Returns the generated HTML select menu for the list of template directories.
   */

  public static function cfgPullDownAllTemplateDirectorylist($value)
  {

    $template_directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/template/';
    $weeds = array('.', '..', '_notes', 'index.php', 'Default', 'ExNewTemplate');
    $directories = array_diff(scandir($template_directory), $weeds);
    $filename_array = [];

    if (is_array($directories)) {
      foreach ($directories as $value) {
        if (is_dir($template_directory . $value)) {
          $filename_array[] = ['id' => $value,
            'text' => $value
          ];
        }
      }
    }

    return HTML::selectMenu('configuration_value', $filename_array, $value);
  }

  /**
   * Recursively copies files and directories from a source path to a destination path,
   * while excluding specific files.
   *
   * @param string $source The source directory or file to copy from.
   * @param string $dest The destination directory where the content will be copied.
   * @return void
   */
  public static function smartCopy($source, $dest)
  {
    if (FileSystem::isWritable($dest, true)) {
      if (!is_dir($dest)) {
        @mkdir($dest, 0777, true);
      }
    }

    foreach (
      $iterator = new RecursiveIteratorIterator(
        new  RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
      ) as $item
    ) {
      if ($item->isDir()) {
        @mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
      } else {
        if ($item != $source . '/README.md' && $item != $source . '/LICENSE' && $item != 'ModuleInfosJson') {
          copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
        }
      }
    }
  }

  /**
   * Removes a directory or a file from the given path.
   *
   * If the provided path is a directory, it recursively deletes all files and subdirectories.
   * If the provided path is a file or a link, it deletes the file or link directly.
   *
   * @param string $source The path of the directory or file to be removed.
   * @return bool Returns true on success or false on failure.
   */
  public static function removeDirectory($source)
  {
    if (is_dir($source) === true) {
      $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::CHILD_FIRST);

      foreach ($files as $file) {
        if (in_array($file->getBasename(), array('.', '..')) !== true) {
          if ($file->isDir() === true) {
            rmdir($file->getPathName());
          } elseif (($file->isFile() === true) || ($file->isLink() === true)) {
            unlink($file->getPathname());
          }
        }
      }

      return rmdir($source);
    } elseif ((is_file($source) === true) || (is_link($source) === true)) {
      return unlink($source);
    }

    return false;
  }
}
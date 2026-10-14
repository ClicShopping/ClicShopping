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

class Gabari
{
  /**
   * Returns the active theme and Default `files/` roots, in priority order.
   *
   * @return array<int,string> Absolute directory paths (active theme first).
   */
  private static function fileRoots(): array
  {
    $root = CLICSHOPPING::getConfig('dir_root', 'Shop');

    return [
      $root . 'sources/template/' . SITE_THEMA . '/files/',
      $root . 'sources/template/Default/files/',
    ];
  }

  /**
   * Recursively collects PHP file names (basenames) under a directory.
   *
   * @param string $dir Absolute directory path.
   * @return array<string,string> Map of basename => basename.
   */
  private static function scanPhpBasenames(string $dir): array
  {
    $names = [];

    if (!is_dir($dir)) {
      return $names;
    }

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $current) {
      if ($current->isFile() && strtolower($current->getExtension()) === 'php') {
        $names[$current->getFileName()] = $current->getFileName();
      }
    }

    return $names;
  }

  /**
   * Retrieves the merged list of `files/` template names (active theme ∪ Default).
   *
   * A Default-only file is therefore listed even when the active theme does not
   * ship it; saving it creates an override in the active theme.
   *
   * @return array An array of filenames, each with an 'id' and 'text' key.
   */
  public static function getFilenameGabari(): array
  {
    $found = [];

    foreach (self::fileRoots() as $dir) {
      $found += self::scanPhpBasenames($dir);
    }

    $filename_array = [
      0 => [
        'id' => '0',
        'text' => CLICSHOPPING::getDef('text_selected')
      ]
    ];

    if ($found) {
      natcasesort($found);

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
   * Retrieves the merged list of `files/` sub directories (active theme ∪ Default).
   *
   * @return array The array of directories, each with an 'id' and 'text' key.
   */
  public static function getDirectoryGabari(): array
  {
    $exclude = ['.', '..', '_notes', 'index.php', '_htaccess', '.htaccess'];

    $names = [];

    foreach (self::fileRoots() as $template_directory) {
      if (!is_dir($template_directory)) {
        continue;
      }

      foreach (array_diff(scandir($template_directory), $exclude) as $directory) {
        if (is_dir($template_directory . $directory)) {
          $names[$directory] = $directory;
        }
      }
    }

    natcasesort($names);

    $directory_array = [
      0 => [
        'id' => '0',
        'text' => CLICSHOPPING::getDef('text_selected')
      ]
    ];

    foreach ($names as $directory) {
      $directory_array[] = [
        'id' => $directory,
        'text' => $directory
      ];
    }

    return $directory_array;
  }
}

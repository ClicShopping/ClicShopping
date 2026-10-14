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

class Css
{
  /**
   * Builds the ordered list of CSS layer roots to inspect for the current language.
   *
   * The listing is merge-aware: it unions the active theme (override layer) with
   * the Default theme (base layer), each in the current language with an english
   * fallback. This lets the admin browse and edit every CSS file even when a
   * custom theme only ships the files it overrides.
   *
   * @param string $suffix Optional path appended to each root (e.g. a sub directory).
   * @return array<int,string> Absolute directory paths, most specific first.
   */
  private static function cssRoots(string $suffix = ''): array
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $root = CLICSHOPPING::getConfig('dir_root', 'Shop');
    $lang = $CLICSHOPPING_Language->get('directory');

    $bases = [
      'sources/template/' . SITE_THEMA . '/css/' . $lang . '/',
      'sources/template/' . SITE_THEMA . '/css/english/',
      'sources/template/Default/css/' . $lang . '/',
      'sources/template/Default/css/english/',
    ];

    $roots = [];

    foreach ($bases as $base) {
      $roots[] = $root . $base . ($suffix !== '' ? $suffix . '/' : '');
    }

    return $roots;
  }

  /**
   * Retrieves the merged list of CSS file names for the selected sub directory.
   *
   * Files are unioned across the active theme and the Default theme (current
   * language, english fallback) so that an override and the underlying base file
   * appear only once.
   *
   * @return array An array of CSS file names with each entry containing an 'id' and 'text' key.
   */
  public static function getFilenameCss(): array
  {
    $directory_selected = HTML::sanitize($_POST['directory_css'] ?? ($_GET['directory_css'] ?? ''));

    $found = [];

    foreach (self::cssRoots($directory_selected) as $dir) {
      if (!is_dir($dir)) {
        continue;
      }

      foreach (scandir($dir) as $entry) {
        if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === 'css') {
          $found[$entry] = $entry;
        }
      }
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
   * Retrieves the merged list of CSS sub directories for the current language.
   *
   * Sub directories are unioned across the active theme and the Default theme
   * (current language, english fallback), excluding unwanted items.
   *
   * @return array Returns an array of subdirectories, each containing an 'id' and 'text' key.
   */
  public static function getDirectoryCss(): array
  {
    $exclude = ['.', '..', '_notes', 'customers_address', 'download', 'index.php', '_htaccess', '.htaccess'];

    $names = [];

    foreach (self::cssRoots() as $template_directory) {
      if (!is_dir($template_directory)) {
        continue;
      }

      foreach (array_diff(scandir($template_directory), $exclude) as $entry) {
        if (is_dir($template_directory . $entry)) {
          $names[$entry] = $entry;
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

    foreach ($names as $name) {
      $directory_array[] = [
        'id' => $name,
        'text' => $name
      ];
    }

    return $directory_array;
  }
}
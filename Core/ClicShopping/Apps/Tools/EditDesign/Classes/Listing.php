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

class Listing
{
  /**
   * Returns the active theme and Default `modules/` roots, in priority order.
   *
   * @param string $suffix Optional path appended to each root.
   * @return array<int,string> Absolute directory paths (active theme first).
   */
  private static function moduleRoots(string $suffix = ''): array
  {
    $root = CLICSHOPPING::getConfig('dir_root', 'Shop');

    return [
      $root . 'sources/template/' . SITE_THEMA . '/modules/' . $suffix,
      $root . 'sources/template/Default/modules/' . $suffix,
    ];
  }

  /**
   * Collects PHP file names (basenames) directly inside a directory.
   *
   * @param string $dir Absolute directory path.
   * @return array<string,string> Map of basename => basename.
   */
  private static function scanPhp(string $dir): array
  {
    $names = [];

    if (!is_dir($dir)) {
      return $names;
    }

    foreach (scandir($dir) as $entry) {
      if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) === 'php') {
        $names[$entry] = $entry;
      }
    }

    return $names;
  }

  /**
   * Retrieves the merged list of module `template_html/` listing names (active theme ∪ Default).
   *
   * A Default-only listing template is therefore listed even when the active theme
   * does not ship it; saving it creates an override in the active theme.
   *
   * @return array An array of template file names, each with an 'id' and 'text' key.
   */
  public static function getFilenameTemplateProducts(): array
  {
    $directory_selected = HTML::sanitize($_POST['directory_html'] ?? ($_GET['directory_html'] ?? ''));

    $found = [];

    foreach (self::moduleRoots($directory_selected . '/template_html/') as $dir) {
      $found += self::scanPhp($dir);
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
   * Retrieves the merged list of module sub directories that can hold a listing
   * (active theme ∪ Default), excluding modules that never expose a listing.
   *
   * @return array An array of directories, each with an 'id' and 'text' key.
   */
  public static function getDirectoryTemplateProducts(): array
  {
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

    $names = [];

    foreach (self::moduleRoots() as $template_directory) {
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

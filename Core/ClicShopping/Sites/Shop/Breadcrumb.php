<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function array_slice;
use function count;
use function is_null;

/**
 * The Breadcrumb class handles the breadcrumb navigation path
 */
class Breadcrumb implements \Iterator
{
  private array $_path = [];
  private string $_separator = ' &raquo; ';
  private $rewriteUrl;
  private array $_pathArray;

  /**
   * Resets the internal path array to an empty state.
   *
   * @return void
   */
  public function reset(): void
  {
    $this->_path = [];
  }

  /**
   * Adds a breadcrumb item to the path list.
   *
   * @param string $title The title of the breadcrumb item.
   * @param string $link Optional. The URL link associated with the breadcrumb item. Defaults to an empty string.
   * @return void
   */

  public function add(string $title, string $link = ''): void
  {
    if (!empty($link)) {
      $title = '<span class="breadcrumb-item breadcrumbCustomize">' . HTML::link(HTML::output($link), $title) . '</span>';
    }

    $this->_path[] = $title;
    $this->_pathArray[] = [
      'link' => HTML::outputProtected($link),
      'title' => $title
    ];
  }

  /**
   * Generates a JSON-LD formatted breadcrumb list used for SEO purposes, structured according to Schema.org specifications.
   *
   * @return string The JSON-LD string encapsulated within a script tag for embedding in an HTML document.
   */
  public function getJsonBreadcrumb(): string
  {
    $itemlistelement = [];

    $array = $this->_pathArray;

    foreach ($array as $k => $v) {
      $itemlistelement[] = [
        '@type' => 'ListItem',
        'position' => $k,
        'item' => array('@id' => $v['link'],
          'name' => strip_tags($v['title']))
      ];
    }

    $schema_breadcrumb = ['@context' => 'https://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => $itemlistelement
    ];

    $data = json_encode($schema_breadcrumb);

    $data = '<script type="application/ld+json">' . $data . '</script>';

    return $data;
  }

  /**
   * Retrieves a combined string representation of the path elements, joined by a specified separator.
   *
   * @param string|null $separator The string to use as a separator between path elements. If null, the default separator is used.
   * @return string The concatenated string of path elements separated by the specified or default separator.
   */
  public function get(?string $separator = null): string
  {
    if (is_null($separator)) {
      $separator = $this->_separator;
    }

    return implode($separator, $this->_path);
  }

  /**
   * Retrieves the path array.
   *
   * @return array The array representing the path.
   */

  public function getArray(): array
  {
    return $this->_path;
  }

  /**
   * Retrieves the separator string used in the context of the class.
   *
   * @return string The separator string.
   */

  public function getSeparator(): string
  {
    return $this->_separator;
  }

  /**
   * Sets the separator value.
   *
   * @param string $separator The separator to be set.
   * @return string|null The previously set separator if available, otherwise null.
   */
  public function setSeparator(string $separator): ?string
  {
    $previous = $this->_separator;
    $this->_separator = $separator;

    return $previous;
  }

  /**
   * Resets the internal pointer of the _path array to its first element.
   *
   * @return void
   */
  public function Rewind(): void
  {
    //  return reset($this->_path); // php8.1 deprecated  to do
  }

  /**
   * Retrieves the current element from the internal `_path` array.
   *
   * @return mixed The current element of the `_path` array, or false if the array is empty.
   */
  public function current(): mixed
  {
    return current($this->_path);
  }

  /**
   * Retrieves the key from the current element of the internal path array.
   *
   * @return string The key of the current element in the internal path array.
   */
  public function key(): string
  {
    return key($this->_path);
  }

  /**
   * Advances the internal array pointer of the `_path` property to the next element.
   *
   * @return void
   */
  public function Next(): void
  {
    // return next($this->_path);  // php8.1 deprecated  to do
  }

  /**
   * Checks whether the current element of the _path array is valid.
   *
   * @return bool Returns true if the current element exists and is not false, false otherwise.
   */
  public function Valid(): bool
  {
    return (current($this->_path) !== false);
  }

  /**
   * Builds and returns a breadcrumb trail based on the category path or manufacturer information.
   * The method calculates the category path for a given product or other context
   * and adds the appropriate category names or manufacturer name to the breadcrumb trail.
   *
   * @return string|bool Returns the generated breadcrumb result as a string if successful,
   *                     or false if no categories or manufacturers are found.
   */
  public function getCategoriesManufacturer(): string|bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_CategoryCommon = Registry::get('CategoryCommon');
    $CLICSHOPPING_Category = Registry::get('Category');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Manufacturer = Registry::get('Manufacturers');
    $CLICSHOPPING_Prod = Registry::get('Prod');

    $this->rewriteUrl = Registry::get('RewriteUrl');

    $result = null;

    // calculate category path
    if ($CLICSHOPPING_Category->getPath()) {
      $cPath = $CLICSHOPPING_Category->getPath();
    } elseif ($CLICSHOPPING_Prod->getID() && !$CLICSHOPPING_Manufacturer->getID()) {
      $cPath = $CLICSHOPPING_Category->getProductPath($CLICSHOPPING_Prod->getID());
    } else {
      $cPath = '';
    }

    if (!empty($cPath)) {
      $cPath_array = $CLICSHOPPING_CategoryCommon->getParseCategoryPath($cPath);
    }

// add category names or the manufacturer name to the breadcrumb trail
    if (isset($cPath_array)) {
      for ($i = 0, $n = count($cPath_array); $i < $n; $i++) {
        $Qcategories = $CLICSHOPPING_Db->get('categories_description', 'categories_name', [
            'categories_id' => (int)$cPath_array[$i],
            'language_id' => $CLICSHOPPING_Language->getId()
          ]
        );

        if ($Qcategories->fetch() !== false) {
          $categories_url = $this->rewriteUrl->getCategoryTreeUrl(implode('_', array_slice($cPath_array, 0, ($i + 1))));

          $result = $CLICSHOPPING_Breadcrumb->add($Qcategories->value('categories_name'), $categories_url);
        } else {
          break;
        }
      }
    } elseif ($CLICSHOPPING_Prod->getID()) {
      $Qmanufacturer = $CLICSHOPPING_Db->get('manufacturers', 'manufacturers_name', ['manufacturers_id' => (int)$CLICSHOPPING_Manufacturer->getID()]);

      if ($Qmanufacturer->fetch() !== false) {
        $manufacturer_url = $this->rewriteUrl->getManufacturerUrl($CLICSHOPPING_Manufacturer->getID());

        $result = $CLICSHOPPING_Breadcrumb->add($Qmanufacturer->value('manufacturers_name'), $manufacturer_url);
      }
    }

    if (is_null($result)) {
      return false;
    } else {
      return $result;
    }
  }
}

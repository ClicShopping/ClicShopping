<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\Shop\SubProduct;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

/**
 * ProductSortHeadingRenderer Class
 *
 * Builds the sortable column-heading link for product listings (asc/desc toggle,
 * keyword-aware URL). Extracted verbatim from ProductsCommon::createSortHeading
 * (cyclo 21) as part of the front-office Products god-class decomposition. Pure:
 * it only reads $_GET/$_POST and static helpers — no instance state.
 *
 * Responsibilities:
 * - Render a productListing-heading anchor that toggles the sort order
 */
class ProductSortHeadingRenderer
{
  /**
   * Constructs a sortable heading link for products.
   *
   * @param string|null $sortby Current sort order and column (null = default).
   * @param string $column The column to sort by.
   * @param string $heading The heading text shown in the link.
   * @return string The HTML markup for the sortable heading link.
   */
  public function createSortHeading($sortby, $column, $heading)
  {
    if (isset($_POST['keywords'])) {
      $keywords = HTML::sanitize($_POST['keywords']);
    } elseif (isset($_GET['keywords'])) {
      $keywords = HTML::sanitize($_GET['keywords']);
    } else {
      $keywords = '';
    }

    if (isset($sortby)) {
      if (isset($_POST['keywords']) || isset($_GET['keywords'])) {
        $sort_prefix = '<a href="' . CLICSHOPPING::link(null, CLICSHOPPING::getAllGET(array('page', 'info', 'sort')) . '&keywords=' . $keywords . '&page=1&sort=' . $column . ($sortby == $column . 'a' ? 'd' : 'a')) . '" title="' . HTML::output(CLICSHOPPING::getDef('text_sort_products') . ' ' . ($sortby == $column . 'd' || substr($sortby, 0, 1) != $column ? CLICSHOPPING::getDef('text_ascendingly') : CLICSHOPPING::getDef('text_descendingly')) . ' ' . trim(CLICSHOPPING::getDef('text_by')) . ' ' . $heading) . '" class="productListing-heading">';
        $sort_suffix = ' ' . (substr($sortby, 0, 1) == $column ? (substr($sortby, 1, 1) == 'a' ? '+' : '-') : '') . '</a>';
      } else {
        $sort_prefix = '<a href="' . CLICSHOPPING::link(null, CLICSHOPPING::getAllGET(array('page', 'info', 'sort')) . '&page=1&sort=' . $column . ($sortby == $column . 'a' ? 'd' : 'a')) . '" title="' . HTML::output(CLICSHOPPING::getDef('text_sort_products') . ' ' . ($sortby == $column . 'd' || substr($sortby, 0, 1) != $column ? CLICSHOPPING::getDef('text_ascendingly') : CLICSHOPPING::getDef('text_descendingly')) . ' ' . trim(CLICSHOPPING::getDef('text_by')) . ' ' . $heading) . '" class="productListing-heading">';
        $sort_suffix = ' ' . (substr($sortby, 0, 1) == $column ? (substr($sortby, 1, 1) == 'a' ? '+' : '-') : '') . '</a>';
      }
} else {
      $sort_prefix = '<a href="' . CLICSHOPPING::link(null, CLICSHOPPING::getAllGET(array('page', 'info', 'sort')) . '&keywords=' . $keywords . '&page=1&sort=' . $column . ($sortby == $column . 'a' ? 'd' : 'a')) . '" title="' . HTML::output(CLICSHOPPING::getDef('text_sort_products') . ' ' . ($sortby == $column . 'd' || substr($sortby, 0, 1) != $column ? CLICSHOPPING::getDef('text_ascendingly') : CLICSHOPPING::getDef('text_descendingly')) . ' ' . trim(CLICSHOPPING::getDef('text_by')) . ' ' . $heading) . '" class="productListing-heading">';
      $sort_suffix = ' ' . (substr($sortby, 0, 1) == $column ? (substr($sortby, 1, 1) == 'a' ? '+' : '-') : '') . '</a>';
    }

    return $sort_prefix . $heading . $sort_suffix;
  }
}

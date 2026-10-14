<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Header;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\UrlCanonicalizer;

class HeaderOutputAMetaHeader
{
  /**
   * Generates and returns the output string containing app header tags, header tag blocks and the
   * canonical link.
   *
   * @return string Concatenated string consisting of app header tags, header tag blocks and canonical.
   */
  public function display(): string
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    $output = $CLICSHOPPING_Template->getAppsHeaderTags() . "\n";
    $output .= $CLICSHOPPING_Template->getBlocks('header_tags') . "\n";

    $canonical = $this->getCanonical();

    if ($canonical !== null) {
      $output .= '<link rel="canonical" href="' . HTML::outputProtected($canonical) . '" />' . "\n";
    }

    return $output;
  }

  /**
   * Canonical URL of the request. Auto-loaded with the header: no module to install, so no
   * uninstall can silently drop the tag.
   *
   * @return string|null The canonical URL, or null when the request designates nothing canonical.
   */
  private function getCanonical(): ?string
  {
    // Same source as the strict router's 301: the tag and the redirect always agree.
    $canonical = UrlCanonicalizer::getCanonicalUrl();

    if ($canonical !== null) {
      return $canonical;
    }

    // Requests the router does not arbitrate (SEO PRO off, query string URLs, logged-in session).
    // First match only: a page has one canonical.
    if (isset($_GET['Products'], $_GET['ProductsNew'])) {
      return CLICSHOPPING::link(null, 'Products&ProductsNew');
    }

    if (isset($_GET['Products'], $_GET['Specials'])) {
      return CLICSHOPPING::link(null, 'Products&Specials');
    }

    $rewriteUrl = Registry::get('RewriteUrl');

    if (isset($_GET['Id']) || isset($_GET['products_id'])) {
      return $rewriteUrl->getProductNameUrl((int)Registry::get('ProductsCommon')->getID());
    }

    if (isset($_GET['Info'], $_GET['Content'], $_GET['pagesId'])) {
      return $rewriteUrl->getPageManagerContentUrl((int)$_GET['pagesId']);
    }

    if (isset($_GET['Search'], $_GET['Q'])) {
      return CLICSHOPPING::link(null, 'Search&Q');
    }

    $cPath = isset($_GET['cPath']) ? Registry::get('Category')->getPath() : '';

    if (!empty($cPath)) {
      return $rewriteUrl->getCategoryTreeUrl($cPath);
    }

    if (isset($_GET['manufacturersId'])) {
      return $rewriteUrl->getManufacturerUrl((int)$_GET['manufacturersId']);
    }

    return null;
  }
}

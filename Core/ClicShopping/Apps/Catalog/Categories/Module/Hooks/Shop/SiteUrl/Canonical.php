<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Catalog\Categories\Module\Hooks\Shop\SiteUrl;

use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

/**
 * Canonical URL provider for the category listing.
 *
 * Claims any request carrying a cPath on the index stem — the SEO PRO slug that precedes it
 * is decorative (SEFU parses it into a junk $_GET key), so it is rebuilt from the category
 * itself rather than trusted.
 */
class Canonical implements HooksInterface
{
  /**
   * @param array $parameters Router context supplied by UrlCanonicalizer.
   * @return array|null The canonical verdict, or null when the request is not a category listing.
   */
  public function execute(array $parameters = []): array|null
  {
    if (($parameters['stem_key'] ?? null) !== '' || !isset($parameters['leftover']['cPath'])) {
      return null;
    }

    if (!Registry::exists('Category') || !Registry::exists('RewriteUrl')) {
      return null;
    }

    $category = Registry::get('Category');

    if ($category->getID() === null) {
      return ['not_found' => true];
    }

    $rewrite_url = Registry::get('RewriteUrl');

    // getPath() rebuilds the full breadcrumb path, so a truncated cPath normalizes itself.
    $cPath = (string)$category->getPath();

    if ($cPath === '') {
      $cPath = (string)$parameters['leftover']['cPath'];
    }

    return ['canonical' => $rewrite_url->getCategoryTreeUrl(
      $cPath,
      (string)($parameters['presentation'] ?? ''),
      $parameters['language_id'] ?? null
    )];
  }
}

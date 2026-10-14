<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Catalog\Products\Module\Hooks\Shop\SiteUrl;

use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\UrlCanonicalizer;

/**
 * Canonical URL provider for the product description page.
 *
 * Claims the Products&Description stem, whatever key carries the id: the shop emits both
 * `Id-103` and `products_id-103`, and both must consolidate on the single slugged form.
 *
 * A product sheet is not a listing: it paginates and sorts nothing, so the listing facets are
 * dropped from its canonical. Without that, `…/Id-103/page-99999` answered 200, indexable, with a
 * self-referencing canonical — an infinite URL space on the most numerous pages of the shop.
 */
class Canonical implements HooksInterface
{
  /**
   * @param array $parameters Router context supplied by UrlCanonicalizer.
   * @return array|null The canonical verdict, or null when the request is not a product page.
   */
  public function execute(array $parameters = []): array|null
  {
    $stem_key = (string)($parameters['stem_key'] ?? '');

    // A bare "Products" designates no page of this App: the router stopped on the page code
    // because the next segment matched no action. Only this App can tell that is an error.
    if ($stem_key === 'Products') {
      return ['not_found' => true];
    }

    if ($stem_key !== 'Products&Description') {
      return null;
    }

    if (!Registry::exists('ProductsCommon') || !Registry::exists('RewriteUrl')) {
      return null;
    }

    $products_common = Registry::get('ProductsCommon');
    $id = $products_common->getID();

    if (empty($id) || !ctype_digit((string)$id) || $products_common->getProductsCount() < 1) {
      return ['not_found' => true];
    }

    $canonical = Registry::get('RewriteUrl')->getProductNameUrl(
      (int)$id,
      UrlCanonicalizer::withoutListingParameters((string)($parameters['presentation'] ?? '')),
      $parameters['language_id'] ?? null
    );

    return ['canonical' => $canonical];
  }
}

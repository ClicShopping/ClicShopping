<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Catalog\Manufacturers\Module\Hooks\Shop\SiteUrl;

use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

/**
 * Canonical URL provider for the manufacturer listing.
 *
 * Claims any request carrying a manufacturersId on the index stem. An id with no active
 * manufacturer behind it designates nothing, so it is reported as not found rather than
 * served as an empty listing.
 */
class Canonical implements HooksInterface
{
  /**
   * @param array $parameters Router context supplied by UrlCanonicalizer.
   * @return array|null The canonical verdict, or null when the request is not a manufacturer listing.
   */
  public function execute(array $parameters = []): array|null
  {
    if (($parameters['stem_key'] ?? null) !== '' || !isset($parameters['leftover']['manufacturersId'])) {
      return null;
    }

    $manufacturers_id = (string)$parameters['leftover']['manufacturersId'];

    if (!ctype_digit($manufacturers_id)) {
      return ['not_found' => true];
    }

    if (!Registry::exists('Manufacturers') || !Registry::exists('RewriteUrl')) {
      return null;
    }

    if (Registry::get('Manufacturers')->getTitle((int)$manufacturers_id) === '') {
      return ['not_found' => true];
    }

    $canonical = Registry::get('RewriteUrl')->getManufacturerUrl((int)$manufacturers_id, (string)($parameters['presentation'] ?? ''));

    return ['canonical' => $canonical];
  }
}

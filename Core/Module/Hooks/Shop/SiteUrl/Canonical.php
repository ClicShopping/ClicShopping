<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\OM\Module\Hooks\Shop\SiteUrl;

use ClicShopping\OM\Apps;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\Sites\Shop\UrlCanonicalizer;

/**
 * Default canonical URL provider for App routes that carry no resource of their own.
 *
 * Most routed pages (catalog listings: specials, featured, favorites, recommendations,
 * best sellers, reviews...) need no domain knowledge to be canonicalized: their canonical URL
 * is the stem the router consumed, plus the parameters the App actually reads. So instead of a
 * PHP provider per App — six identical files differing by two constants — an App DECLARES its
 * request vocabulary in its own clicshopping.json:
 *
 *   "canonical": { "Shop": { "parameters": ["products_id", "reviews_id"] } }
 *
 * An optional `"listing": false` next to it says the page paginates and sorts nothing, so the
 * listing facets (page, sort, view, filter_id) are dropped from its canonical too. Absent means
 * listing — which every App declaring today is.
 *
 * A new App is then canonicalizable with three lines of JSON and no code. An App that declares
 * nothing is never redirected, which is what keeps the transactional routes (api, mcp, cronjob,
 * payment callbacks) out of reach, and lets an App that needs real logic ship its own provider
 * instead (Catalog/Products, Catalog/Categories, Catalog/Manufacturers, Communication/PageManager
 * do exactly that, and are left alone here because they declare no "canonical" section).
 *
 * The owning App is read from the route the router already resolved, so no directory scan is
 * added to the request.
 */
class Canonical implements HooksInterface
{
  /**
   * @param array $parameters Router context supplied by UrlCanonicalizer.
   * @return array|null The canonical URL, or null when no App claims the request.
   */
  public function execute(array $parameters = []): array|null
  {
    $stem_key = (string)($parameters['stem_key'] ?? '');

    if ($stem_key === '') {
      return null;
    }

    $section = self::getCanonicalSection($parameters['route'] ?? null, $stem_key);

    if ($section === null) {
      return null;
    }

    $own = '';

    foreach (self::getDeclaredParameters($section) as $key) {
      if (isset($parameters['leftover'][$key]) && $parameters['leftover'][$key] !== '') {
        $own .= '&' . $key . '=' . $parameters['leftover'][$key];
      }
    }

    $presentation = (string)($parameters['presentation'] ?? '');

    // "listing": false — the page paginates and sorts nothing, so page/sort/view/filter_id are not
    // part of its canonical. Absent means listing, which is what every App declaring today is.
    if (($section['listing'] ?? true) === false) {
      $presentation = UrlCanonicalizer::withoutListingParameters($presentation);
    }

    return ['canonical' => CLICSHOPPING::link(null, $stem_key . $own . $presentation)];
  }

  /**
   * @param array $section The App's declared canonical section for the Shop site.
   * @return array The request vocabulary it declares, empty when the page reads nothing.
   */
  private static function getDeclaredParameters(array $section): array
  {
    $declared = $section['parameters'] ?? [];

    return is_array($declared) ? array_values(array_filter($declared, 'is_string')) : [];
  }

  /**
   * Reads the canonical section the owning App declares for the Shop site.
   *
   * @param array|null $route The route resolved by the router, carrying the owning App.
   * @param string $stem_key The stem the router consumed.
   * @return array|null The declared section, or null when the App declares nothing.
   */
  private static function getCanonicalSection(?array $route, string $stem_key): array|null
  {
    $destination = $route['destination'] ?? null;

    if (!is_string($destination) || !str_contains($destination, '/')) {
      return null;
    }

    [$vendor_app] = explode('/', $destination, 2);

    $info = Apps::getInfo($vendor_app);

    if ($info === false || !isset($info['canonical']['Shop']['parameters'])) {
      return null;
    }

    // The route the App declares must be the one the router actually consumed.
    if (!isset($info['routes']['Shop'][$stem_key])) {
      return null;
    }

    return $info['canonical']['Shop'];
  }
}

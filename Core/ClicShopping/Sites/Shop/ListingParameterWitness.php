<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Sites\Shop;

use function in_array;

/**
 * Records whether the listing parameters carried by the request were actually HONOURED.
 *
 * The canonical gates prove a value is well-formed (`sort=99999a` matches the shape) and relevant
 * (the page is a listing), but not that anything used it: `/dinning-bar/cPath-3/sort-99999a` and
 * `/…/filter_id-99999` answered 200 with a self-referencing canonical, two infinite URL spaces.
 * Only the consumer knows — `ProductsListingRenderer::orderByClause()` and
 * `ProductsListing::getData()` already fall back to their default, they simply told nobody.
 *
 * The upper bound of `page` is the same problem solved one step earlier, through
 * DbStatement::getPageSetBounds(); both registers are read at the same chokepoint
 * (Sites/Shop/Template::buildBlocks()) and applied by UrlCanonicalizer::enforceListingBounds().
 *
 * Two rules make the verdict safe to redirect on:
 *  - ABSTENTION: a listing that cannot honour the parameter at all (sort bar disabled, no sortable
 *    column) says nothing. "Not honoured" must mean invalid, never merely inapplicable.
 *  - CONSERVATIVE OR: several listings share these keywords on one page (3 to 4 measured here), so
 *    a parameter is dropped only when NO listing honoured it. A sort valid for the side box but
 *    not for the main listing survives.
 */
final class ListingParameterWitness
{
  /**
   * The parameters a listing can be a witness for — the presentation keywords whose meaning is
   * decided by a query, not by their spelling. `language` and `currency` are global display state
   * no listing owns, and are deliberately absent.
   */
  private const WITNESSABLE = ['page', 'sort', 'view', 'filter_id'];

  /**
   * Values as they were REQUESTED, snapshot before any listing runs.
   *
   * ProductsListing::getData() overwrites $_GET['sort'] with its own default when it refuses the
   * requested one, so a listing querying later reads a value the visitor never asked for and would
   * report it honoured. Judging the snapshot instead of $_GET closes that hole.
   */
  private static ?array $requested = null;

  /**
   * parameter => at least one listing honoured it.
   */
  private static array $honoured = [];

  /**
   * Snapshots the requested values. Called by UrlCanonicalizer::enforce(), i.e. from the router,
   * before any module has run; the lazy fallback in requested() only covers direct unit calls.
   */
  public static function snapshot(): void
  {
    self::$requested = [];

    foreach (self::WITNESSABLE as $key) {
      if (isset($_GET[$key]) && \is_string($_GET[$key]) && $_GET[$key] !== '') {
        self::$requested[$key] = $_GET[$key];
      }
    }
  }

  /**
   * @return string|null The value the request carried for this parameter, null when it carried none.
   */
  public static function requested(string $parameter): ?string
  {
    if (self::$requested === null) {
      self::snapshot();
    }

    return self::$requested[$parameter] ?? null;
  }

  /**
   * Records a listing's verdict on a parameter it was able to judge. A parameter the request does
   * not carry is ignored, and true always wins over false (see CONSERVATIVE OR above).
   */
  public static function witness(string $parameter, bool $honoured): void
  {
    if (!in_array($parameter, self::WITNESSABLE, true) || self::requested($parameter) === null) {
      return;
    }

    self::$honoured[$parameter] = $honoured || (self::$honoured[$parameter] ?? false);
  }

  /**
   * @return array The verdicts observed on this request, parameter => honoured. A parameter no
   *               listing judged is absent, and must therefore never be dropped.
   */
  public static function getVerdicts(): array
  {
    return self::$honoured;
  }
}

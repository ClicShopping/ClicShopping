<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\Shop;

use ClicShopping\OM\HTML;

/**
 * Class ProductsListingContext
 *
 * Immutable per-module configuration consumed by {@see ProductsListingRenderer}.
 * Encapsulates every value that differs between front-office product listing
 * modules (new products, specials, favorites, listing, search, category) so
 * that the rendering pipeline itself remains identical.
 *
 * The constants prefix is the single source of truth for the runtime
 * configuration lookups (e.g. {prefix}_TEMPLATE, {prefix}_MAX_DISPLAY,
 * {prefix}_DELETE_BUY_BUTTON, etc.). The renderer reads them via
 * {@see defined()} / constant(), never inventing keys.
 */
readonly class ProductsListingContext
{
  /**
   * @param string $constantsPrefix Module constants prefix, e.g. "MODULE_FRONT_PAGE_NEW_PRODUCTS".
   * @param string $cssContainerClass Outer wrapper CSS class for the listing grid.
   * @param string|null $cssHeadingClass Heading wrapper CSS class, or null to omit the heading.
   * @param string|null $headingTextDef Language define key for the heading text, or null.
   * @param array{special:string,favorite:string,featured:string,new:string} $tickerClasses CSS classes for the four ticker variants.
   * @param string $tickerPercentageClass CSS class for the discount percentage ticker.
   * @param string $group Module group (e.g. "modules_front_page").
   * @param string $trackingCode Module code used by AI ProductsTracking.
   * @param string $modulePosition Either "left" or "right".
   * @param int $sortOrder Module sort order.
   * @param float $trackingWeight AI tracking weight for this surface.
   * @param string|null $hiddenUrlField Optional value for the hidden "url" field in the cart form (e.g. "Products&Specials").
   * @param string $listingCommentLabel HTML comment label, e.g. "New Products" or "Specials Products".
   * @param bool $displayCartButton Whether the "Add to cart" button should be rendered (positive semantics).
   * @param bool $displayDetailsButton Whether the "View details" button should be rendered (positive semantics).
   * @param bool $displaySortBar Whether the "Sort by" dropdown should be rendered for this listing.
   * @param string[] $sortColumns Ordered list of sortable column keys (MODEL, NAME, PRICE, QUANTITY, WEIGHT, DATE). The order defines the numeric sort index used in $_GET['sort'].
   * @param bool $displayViewSwitch Whether the grid/line view switch is offered. Both views use the single configured template (MODULE_xxx_TEMPLATE); "line" simply forces the bootstrap column to 12 (full width) while "grid" uses MODULE_xxx_COLUMNS.
   * @param array<string,bool> $displayOptions Per-field display toggles consulted by the renderer before computing each optional product field (e.g. ['manufacturer' => true, 'weight' => false]). Keys absent from the map fall back to the renderer default (shown). Lets each listing opt fields in/out without API churn.
   */
  public function __construct(
    public string  $constantsPrefix,
    public string  $cssContainerClass,
    public ?string $cssHeadingClass,
    public ?string $headingTextDef,
    public array   $tickerClasses,
    public string  $tickerPercentageClass,
    public string  $group,
    public string  $trackingCode,
    public string  $modulePosition,
    public int     $sortOrder,
    public float   $trackingWeight,
    public ?string $hiddenUrlField = null,
    public string  $listingCommentLabel = 'Products',
    public bool    $displayCartButton = true,
    public bool    $displayDetailsButton = true,
    public bool    $displaySortBar = false,
    public array   $sortColumns = [],
    public bool    $displayViewSwitch = false,
    public array   $displayOptions = [],
  ) {}

  /**
   * Whether an optional product field should be computed and displayed.
   * Unknown keys fall back to $default so adding a new toggle never changes
   * existing behavior unless a module opts out explicitly.
   *
   * @param string $key Field key (e.g. 'manufacturer', 'weight', 'model').
   * @param bool $default Returned when the key is not present in displayOptions.
   */
  public function shows(string $key, bool $default = true): bool
  {
    return $this->displayOptions[$key] ?? $default;
  }

  /**
   * Resolve the currently requested view ('grid' or 'line') from $_GET,
   * defaulting to 'grid'. 'line' is only honored when the view switch is enabled.
   */
  public function currentView(): string
  {
    if ($this->displayViewSwitch && isset($_GET['view']) && HTML::sanitize($_GET['view']) === 'line') {
      return 'line';
    }

    return 'grid';
  }

  /**
   * Build the full constant name for a given suffix.
   * Returns the constant name unconditionally; callers must guard with defined().
   */
  public function constant(string $suffix): string
  {
    return $this->constantsPrefix . '_' . $suffix;
  }

  /**
   * Read a configuration constant by suffix, with a fallback when undefined.
   *
   * @param string $suffix Suffix appended to constantsPrefix.
   * @param mixed $default Returned when the constant is not defined.
   * @return mixed
   */
  public function config(string $suffix, mixed $default = null): mixed
  {
    $name = $this->constant($suffix);

    return \defined($name) ? \constant($name) : $default;
  }

  /**
   * True when the configured "front title" constant is enabled.
   * Used by modules whose presentation includes an optional heading block.
   */
  public function isFrontTitleEnabled(): bool
  {
    return $this->config('FRONT_TITLE', 'False') === 'True';
  }
}

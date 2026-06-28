<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\CockpitAI\ProductsTracking;

/**
 * Class ProductsListingRenderer
 *
 * Renders the per-row HTML fragment shared by every front-office product
 * listing module (new products, specials, favorites, listing, search,
 * category). The differences across modules are captured in
 * {@see ProductsListingContext}; this class hosts the strictly common
 * logic so that a single bugfix or extension benefits every listing.
 *
 * Usage:
 * <code>
 *   $renderer = new ProductsListingRenderer($context);
 *   $html = $renderer->renderList($Qproduct);
 * </code>
 *
 * The legacy template files under template/&lt;Template&gt;/modules/&lt;group&gt;/template_html/
 * are loaded as before via ob_start()/require so existing HTML markup keeps
 * working without modification.
 */
final class ProductsListingRenderer
{
  private mixed $productsCommon;
  private mixed $productsFunctionTemplate;
  private mixed $productsAttributes;
  private mixed $reviews;
  private mixed $language;
  private mixed $template;

  /**
   * Whether {@see ProductsTracking::insertProductTracking()} should be
   * invoked for each rendered product. Defaults to true; callers can
   * disable it for surfaces that historically did not track (e.g. the
   * home page new-products module).
   */
  private bool $trackingEnabled = true;

  /**
   * Language define key used as the visible label for each sortable column.
   */
  private const SORT_LABEL = [
    'MODEL'        => 'table_heading_model',
    'NAME'         => 'table_heading_products',
    'MANUFACTURER' => 'table_heading_manufacturer',
    'PRICE'        => 'table_heading_price',
    'QUANTITY'     => 'table_heading_quantity',
    'WEIGHT'       => 'table_heading_weight',
    'DATE'         => 'table_heading_date',
  ];

  /**
   * SQL fragment applied in the ORDER BY clause for each sortable column.
   * NAME relies on a products_description (pd) join; the others live on the
   * products table (p) directly.
   */
  private const SORT_SQL = [
    'MODEL'        => 'p.products_model',
    'NAME'         => 'pd.products_name',
    'MANUFACTURER' => 'm.manufacturers_name',
    'PRICE'        => 'p.products_price',
    'QUANTITY'     => 'p.products_quantity',
    'WEIGHT'       => 'p.products_weight',
    'DATE'         => 'p.products_date_added',
  ];

  public function __construct(
    public readonly ProductsListingContext $context,
  ) {
    $this->productsCommon = Registry::get('ProductsCommon');
    $this->productsFunctionTemplate = Registry::get('ProductsFunctionTemplate');
    $this->productsAttributes = Registry::get('ProductsAttributes');
    $this->reviews = Registry::get('Reviews');
    $this->language = Registry::get('Language');
    $this->template = Registry::get('Template');
  }

  /**
   * Toggle AI product tracking for this rendering session.
   */
  public function withTracking(bool $enabled): self
  {
    $this->trackingEnabled = $enabled;

    return $this;
  }

  /**
   * Render the listing toolbar: an optional grid/line view switch followed by
   * the "Sort by" dropdown, laid out with flexbox (no Bootstrap grid columns
   * or floats) so it integrates cleanly above any listing.
   *
   * The sort dropdown always renders its items (no $_GET['sort'] guard) and
   * does not wrap the heading anchor inside a second <a href="#">
   * (createSortHeading already returns a full <a>). The view switch only
   * appears when the Context enables displayViewSwitch.
   *
   * Returns an empty string when neither the sort bar nor the view switch is
   * applicable.
   */
  public function renderSortBar(): string
  {
    $columns = array_values($this->context->sortColumns);
    $hasSort = $this->context->displaySortBar && $columns !== [];
    $hasView = $this->context->displayViewSwitch;

    if (!$hasSort && !$hasView) {
      return '';
    }

    $html = '<div class="ProductsListingToolbar d-flex justify-content-end align-items-center gap-2 mb-2">';

    if ($hasView) {
      $html .= $this->renderViewSwitch();
    }

    if ($hasSort) {
      $sortParam = isset($_GET['sort']) ? HTML::sanitize($_GET['sort']) : '1a';

      $html .= '<div class="dropdown ProductsListingSortBar">';
      $html .= '<button type="button" class="btn btn-secondary btn-sm dropdown-toggle" id="dropdownMenuSort" data-bs-toggle="dropdown" aria-expanded="false">';
      $html .= CLICSHOPPING::getDef('text_sort_by');
      $html .= '</button>';
      $html .= '<ul class="dropdown-menu dropdown-menu-end text-start" aria-labelledby="dropdownMenuSort">';

      foreach ($columns as $i => $key) {
        if (!isset(self::SORT_LABEL[$key])) {
          continue;
        }

        $label = CLICSHOPPING::getDef(self::SORT_LABEL[$key]);
        $html .= '<li>' . $this->productsCommon->createSortHeading($sortParam, $i + 1, $label) . '</li>';
      }

      $html .= '</ul>';
      $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
  }

  /**
   * Render only the grid/line view switch (no surrounding flex/float wrapper),
   * for modules that keep their own header (e.g. paginated listings) and want
   * to drop the switch wherever they like. Both views use the single
   * configured template; "line" only forces full-width columns. Returns an
   * empty string when displayViewSwitch is disabled.
   */
  public function renderViewSwitch(): string
  {
    if (!$this->context->displayViewSwitch) {
      return '';
    }

    $current = $this->context->currentView();
    $base = CLICSHOPPING::getAllGET(['view']);
    $gridLink = CLICSHOPPING::link(null, $base . '&view=grid');
    $lineLink = CLICSHOPPING::link(null, $base . '&view=line');

    return '<div class="btn-group btn-group-sm ProductsListingViewSwitch" role="group">'
      . '<a href="' . $gridLink . '" class="btn btn-outline-secondary' . ($current === 'grid' ? ' active' : '') . '" title="' . HTML::outputProtected(CLICSHOPPING::getDef('text_view_grid')) . '"><i class="bi bi-grid-3x2-gap-fill"></i></a>'
      . '<a href="' . $lineLink . '" class="btn btn-outline-secondary' . ($current === 'line' ? ' active' : '') . '" title="' . HTML::outputProtected(CLICSHOPPING::getDef('text_view_list')) . '"><i class="bi bi-card-list"></i></a>'
      . '</div>';
  }

  /**
   * Render the listing header row: the (optional) heading on the left and the
   * sort/view toolbar on the right, on a single flex line. Pass the module's
   * already-built heading HTML (each module composes its own — simple <h2> or
   * a date-formatted title). Returns an empty string when there is neither a
   * heading nor a toolbar.
   *
   * @param string $headingHtml Pre-built heading markup, or '' for no heading.
   */
  public function renderHeaderRow(string $headingHtml = ''): string
  {
    $toolbar = $this->renderSortBar();

    if ($headingHtml === '' && $toolbar === '') {
      return '';
    }

    return '<div class="ProductsListingHeader d-flex justify-content-between align-items-center flex-wrap mb-2">'
      . '<div class="me-auto ProductsListingHeaderTitle">' . $headingHtml . '</div>'
      . $toolbar
      . '</div>';
  }

  /**
   * Resolve the ORDER BY column expression from $_GET['sort'] against the
   * declared sortColumns, falling back to $default when sorting is disabled,
   * absent or malformed. The returned value excludes the "order by" keyword
   * so callers can compose it into their own query.
   *
   * @param string $default Column expression used when no valid sort is requested (e.g. "rand(), p.products_date_added desc").
   */
  public function orderByClause(string $default): string
  {
    $columns = array_values($this->context->sortColumns);

    if (!$this->context->displaySortBar || $columns === [] || !isset($_GET['sort'])) {
      return $default;
    }

    if (!preg_match('/^([0-9]+)([ad])$/', HTML::sanitize($_GET['sort']), $matches)) {
      return $default;
    }

    $index = (int)$matches[1] - 1;
    $direction = $matches[2] === 'd' ? 'desc' : 'asc';

    if (!isset($columns[$index], self::SORT_SQL[$columns[$index]])) {
      return $default;
    }

    return self::SORT_SQL[$columns[$index]] . ' ' . $direction;
  }

  /**
   * Render the full listing for an executed query/iterable yielding rows
   * exposing valueInt('products_id') (e.g. a ClicShopping DbStatement).
   *
   * Returns only the inner d-flex grid markup; callers remain responsible
   * for the outer container, heading, pagination and trailing comments
   * because those wrappers differ noticeably between modules.
   */
  public function renderList(mixed $rows): string
  {
    // A single configured template (MODULE_xxx_TEMPLATE) serves both views;
    // the line view only changes the column width (see renderItem).
    $filename = $this->template->getTemplateModulesFilename(
      $this->context->group . '/template_html/' . $this->context->config('TEMPLATE')
    );

    // View hook: lets CSS render the same template as a grid (cards) or a
    // line (horizontal) layout without a second template file.
    $html = '<div class="d-flex flex-wrap ProductsListingItems ProductsListingItems--' . $this->context->currentView() . '">';
    $counter = 1;

    try {
      while ($rows->fetch()) {
        $products_id = $rows->valueInt('products_id');
        // Bind the shared ProductsCommon instance to the row before rendering:
        // every stateful helper (getProductsBuyButton, getProductsAllowingToInsertQuantity,
        // getProductsOrdersView, ...) will then resolve against this id rather
        // than the empty $_GET context of the listing page.
        $this->productsCommon->setID($products_id);
        $html .= $this->renderItem($products_id, $counter, $filename);
        $counter++;
      }
    } finally {
      // Restore the legacy GET/POST id resolution for any caller that may
      // re-use the shared ProductsCommon after this listing.
      $this->productsCommon->clearID();
    }

    $html .= '</div>' . "\n";

    return $html;
  }

  /**
   * Render a single product card by computing every variable expected by
   * the legacy template_html/*.php fragments and including the file in an
   * isolated output buffer.
   */
  private function renderItem(int $products_id, int $counter, string $filename): string
  {
    $context = $this->context;
    $productsCommon = $this->productsCommon;
    $productsFunctionTemplate = $this->productsFunctionTemplate;
    $productsAttributes = $this->productsAttributes;
    $reviews = $this->reviews;

    if ($this->trackingEnabled) {
      ProductsTracking::insertProductTracking(
        $products_id,
        $context->trackingCode,
        $context->modulePosition,
        $context->sortOrder,
        $this->language->getId(),
        null,
        $context->trackingWeight
      );
    }

    $delete_word = (int)$context->config('SHORT_DESCRIPTION_DELETE_WORLDS', 0);
    $products_short_description_number = (int)$context->config('SHORT_DESCRIPTION', 0);
    // Line view forces full-width (12); grid view uses the configured columns.
    $bootstrap_column = $context->currentView() === 'line' ? 12 : (int)$context->config('COLUMNS', 0);
    $size_button = $productsCommon->getSizeButton('md');
    // Legacy flag wiring: ProductsFunctionTemplate::getDisplayInputQuantity()
    // and getButtonViewDetails() treat 'False' as "display this button".
    // We expose positive booleans on the Context and translate here so the
    // legacy APIs keep working unchanged.
    $cart_legacy_flag = $context->displayCartButton ? 'False' : 'True';
    $details_legacy_flag = $context->displayDetailsButton ? 'False' : 'True';
    $display_stock = (string)$context->config('DISPLAY_STOCK', 'none');
    $image_size = (string)$context->config('IMAGE_MEDIUM', 'Small');
    $ticker_flag = (string)$context->config('TICKER', 'False');
    $percentage_ticker_flag = (string)$context->config('POURCENTAGE_TICKER', 'False');

    $products_name_url = $productsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($products_id);
    $products_name = $productsCommon->getProductsName($products_id);
    $products_stock = $productsFunctionTemplate->getStock($display_stock, $products_id);
    $products_flash_discount = $productsFunctionTemplate->getFlashDiscount($products_id, '<br />');
    $min_order_quantity_products_display = $productsFunctionTemplate->getMinOrderQuantityProductDisplay($products_id);
    $submit_button_view = $productsFunctionTemplate->getButtonView($products_id);

    $button_buy_id = 'buttonBuyId_' . $counter;
    $buy_button = HTML::button(
      CLICSHOPPING::getDef('button_buy_now'),
      null,
      null,
      'primary',
      ['params' => 'id="' . $button_buy_id . '"'],
      'sm'
    );
    $productsCommon->getBuyButton($buy_button);

    $stock_quantity = (int)$productsCommon->getProductsQuantity($products_id);

    // Quantity input toggle (replaces the legacy *_SHORT_DESCRIPTION_DELETE_WORLDS slot).
    // True (default) shows the editable quantity field; False hides it and the
    // quantity is submitted as a hidden field (or, when the minimum order qty is
    // greater than 1, the cart button links to the product page instead).
    $show_qty_input = $context->config('DISPLAY_QUANTITY_INPUT', 'True') === 'True';

    if ($stock_quantity > 0 && $show_qty_input) {
      $input_quantity = $productsFunctionTemplate->getDisplayInputQuantity($cart_legacy_flag, $products_id);
    } else {
      $input_quantity = '';
    }

    $product_price = $productsCommon->getCustomersPrice($products_id);
    $products_short_description = $productsCommon->getProductsShortDescription(
      $products_id,
      $delete_word,
      $products_short_description_number
    );
    $avg_reviews = '<span class="ModulesReviews">' . HTML::stars($reviews->getAverageProductReviews($products_id)) . '</span>';

    [$submit_button, $form, $endform, $min_quantity] = $this->renderCartForm($products_id, $stock_quantity, $show_qty_input, $products_name_url);

    $products_quantity_unit = $context->shows('quantityUnit') ? $productsFunctionTemplate->getProductQuantityUnitType($products_id) : '';

    [
      $submit_button,
      $form,
      $endform,
      $min_quantity,
      $input_quantity,
      $min_order_quantity_products_display,
    ] = $this->applySubmitButtonOverrides($products_id, $products_name_url, $submit_button, $form, $endform, $min_quantity, $input_quantity, $min_order_quantity_products_display);

    $button_small_view_details = $productsFunctionTemplate->getButtonViewDetails($details_legacy_flag, $products_id);

    $products_image = $productsFunctionTemplate->getImage($image_size, $products_id);

    //bug
    $products_image .= $productsFunctionTemplate->getTicker(
      $ticker_flag,
      $products_id,
      $context->tickerClasses['special'] ?? '',
      $context->tickerClasses['favorite'] ?? '',
      $context->tickerClasses['featured'] ?? '',
      $context->tickerClasses['new'] ?? ''
    );

    $ticker = $productsFunctionTemplate->getTickerPourcentage(
      $percentage_ticker_flag,
      $products_id,
      $context->tickerPercentageClass
    );

    [
      'products_model' => $products_model,
      'products_manufacturers' => $products_manufacturers,
      'product_price_kilo' => $product_price_kilo,
      'products_date_available' => $products_date_available,
      'products_only_shop' => $products_only_shop,
      'products_only_web' => $products_only_web,
      'products_packaging' => $products_packaging,
      'products_shipping_delay' => $products_shipping_delay,
      'products_tag' => $products_tag,
      'products_volume' => $products_volume,
      'products_weight' => $products_weight,
    ] = $this->collectDisplayFields($products_id);

    // Manufacturer name link (products-listing templates display it in place of
    // the product name; getManufacturerName already embeds the product name link).
    // Always assigned so any template referencing it stays warning-free.
    $manufacturer_name = $productsFunctionTemplate->getManufacturerName($products_id);
    $manufacturer_image = $productsFunctionTemplate->getManufacturerImage($products_id, $products_image);

    $jsonLtd = $productsFunctionTemplate->getProductJsonLd($products_id);

    if (!is_file($filename)) {
      echo CLICSHOPPING::getDef('template_does_not_exist') . '<br /> ' . $filename;
      exit;
    }

    ob_start();
    require($filename);

    return (string)ob_get_clean();
  }

  /**
   * Applies the free-product and sold-out overrides on the submit-button state
   * (each forces the button/form and resets the quantity helpers). Extracted
   * verbatim from renderItem; returns the updated state for re-binding.
   *
   * @return array{0:string,1:string,2:string,3:int|null,4:string,5:string}
   */
  private function applySubmitButtonOverrides(int $products_id, string $products_name_url, string $submit_button, string $form, string $endform, ?int $min_quantity, string $input_quantity, string $min_order_quantity_products_display): array
  {
    $productsCommon = $this->productsCommon;

    // Free button must run before sold-out: a free product is never sold-out logic-wise.
    if ($productsCommon->getProductsOrdersView($products_id) != 1
        && \defined('NOT_DISPLAY_PRICE_ZERO') && NOT_DISPLAY_PRICE_ZERO == 'false') {
      $submit_button = HTML::button(CLICSHOPPING::getDef('text_products_free'), '', $products_name_url, 'danger');
      $min_quantity = 0;
      $form = '';
      $endform = '';
      $input_quantity = '';
      $min_order_quantity_products_display = '';
    }

    $soldOutMessage = $productsCommon->getProductsSoldOut($products_id);

    if (!empty($soldOutMessage)) {
      $submit_button = $soldOutMessage;
      $form = '';
      $endform = '';
      $min_quantity = 0;
      $input_quantity = '';
      $min_order_quantity_products_display = '';
    }

    return [$submit_button, $form, $endform, $min_quantity, $input_quantity, $min_order_quantity_products_display];
  }

  /**
   * Collects the optional display fields for a listing item — each is always
   * assigned (empty when its display toggle is off) so the template scope stays
   * intact. Extracted verbatim from renderItem to drain the per-field
   * $context->shows() ternaries from its NPath.
   *
   * @return array<string,string> Keyed by template variable name
   */
  private function collectDisplayFields(int $products_id): array
  {
    $context = $this->context;
    $productsFunctionTemplate = $this->productsFunctionTemplate;

    // [template var => [Context::shows() flag, ProductsFunctionTemplate getter]].
    // Driven by a map so the per-field "show ? lookup : ''" stops multiplying the
    // method's NPath (one loop instead of N independent ternaries).
    $simpleFields = [
      'products_model' => ['model', 'getProductsModel'],
      'products_manufacturers' => ['manufacturer', 'getProductsManufacturer'],
      'product_price_kilo' => ['priceByWeight', 'getProductsPriceByWeight'],
      'products_date_available' => ['dateAvailable', 'getProductsDateAvailable'],
      'products_only_shop' => ['onlyShop', 'getProductsOnlyTheShop'],
      'products_only_web' => ['onlyWeb', 'getProductsOnlyOnTheWebSite'],
      'products_packaging' => ['packaging', 'getProductsPackaging'],
      'products_shipping_delay' => ['shippingDelay', 'getProductsShippingDelay'],
      'products_volume' => ['volume', 'getProductsVolume'],
      'products_weight' => ['weight', 'getProductsWeight'],
    ];

    $fields = [];

    foreach ($simpleFields as $var => [$flag, $method]) {
      $fields[$var] = $context->shows($flag) ? $productsFunctionTemplate->$method($products_id) : '';
    }

    // Tags are multi-value (linked spans), so they keep a dedicated build.
    $fields['products_tag'] = '';

    if ($context->shows('tags')) {
      $tag = $productsFunctionTemplate->getProductsHeadTag($products_id);

      if (\is_array($tag)) {
        foreach ($tag as $value) {
          $encoded = mb_convert_encoding($value, 'UTF-8', mb_detect_encoding($value, 'auto'));
          $fields['products_tag'] .= '#<span class="productTag">'
            . HTML::link(
              CLICSHOPPING::link(null, 'Search&keywords=' . HTML::outputProtected($encoded . '&search_in_description=1&categories_id=&inc_subcat=1'), 'rel="nofollow"'),
              $value
            )
            . '</span> ';
        }
      }
    }

    return $fields;
  }

  /**
   * Resolves the add-to-cart submit button / form state for a listing item.
   * Extracted verbatim from renderItem (its most branchy block) to drain the
   * method's NPath.
   *
   * @return array{0:string,1:string,2:string,3:int|null} [submit_button, form, endform, min_quantity]
   */
  private function renderCartForm(int $products_id, int $stock_quantity, bool $show_qty_input, string $products_name_url): array
  {
    $context = $this->context;
    $productsCommon = $this->productsCommon;
    $productsAttributes = $this->productsAttributes;

    // Default state: no cart submit, no form wrapper.
    $submit_button = '';
    $form = '';
    $endform = '';
    $min_quantity = null;

    if ($context->displayCartButton) {
      $minQty = (int)$productsCommon->getProductsMinimumQuantity($products_id);
      $hasAttributes = $productsAttributes->getHasProductAttributes($products_id);

      if ($minQty !== 0 && $stock_quantity !== 0 && $hasAttributes === false) {
        if (!$show_qty_input && $minQty > 1) {
          // Hidden input + minimum order quantity > 1: avoid the cart silently
          // bumping the quantity. Send the customer to the product page, which
          // displays the "minimum quantity to order" notice.
          $submit_button = HTML::button(CLICSHOPPING::getDef('button_buy_now'), null, $products_name_url, 'primary', null, 'sm');
        } else {
          $form = HTML::form(
            'cart_quantity',
            CLICSHOPPING::link(null, 'Cart&Add'),
            'post',
            'class="justify-content-center ModulesCartQuantity"',
            ['tokenize' => true]
          ) . "\n";
          $form .= HTML::hiddenField('products_id', $products_id);

          if ($context->hiddenUrlField !== null) {
            $form .= HTML::hiddenField('url', $context->hiddenUrlField);
          }

          // When the visible field is hidden, still submit the quantity so the
          // cart receives a numeric cart_quantity (else add-to-cart is rejected).
          if (!$show_qty_input) {
            $form .= HTML::hiddenField('cart_quantity', max(1, $minQty));
          }

          $endform = '</form>';
          $submit_button = $productsCommon->getProductsBuyButton($products_id);
        }
      }
    }

    return [$submit_button, $form, $endform, $min_quantity];
  }
}

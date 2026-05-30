<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;
use function is_null;

/**
 * Class ProductsFunctionTemplate
 *
 * This class provides functions to handle product-related information such as generating product URLs,
 * retrieving stock details, managing discount displays, handling quantity inputs, and creating custom product-related buttons or images.
 */
class ProductsFunctionTemplate
{
  protected mixed $productsCommon;
  protected mixed $customer;
  private mixed $template;
  protected mixed $category;
  protected mixed $rewriteUrl;

  /**
   * Class ProductsFunctionTemplate
   *
   * This class provides functions to handle product-related information such as generating product URLs,
   * retrieving stock details, managing discount displays, handling quantity inputs, and creating custom product-related buttons or images.
   */
  public function __construct()
  {
    $this->productsCommon = Registry::get('ProductsCommon');
    $this->customer = Registry::get('Customer');
    $this->template = Registry::get('Template');
    $this->category = Registry::get('Category');
    $this->rewriteUrl = Registry::get('RewriteUrl');
  }

  /**
   * Retrieves the rewritten URL for the products.
   *
   * @return string The rewritten URL of the products.
   */
  public function getProductsUrlRewrited()
  {
    return $this->rewriteUrl;
  }

  /**
   * Retrieves the URL for the product name with appropriate HTML formatting and item properties.
   *
   * @param int|string $products_id The ID of the product for which the name URL is being generated.
   * @return string The formatted product name URL.
   */
  public function getProductsNameUrl(int|string $products_id): string
  {
    $products_name = HTML::link($this->rewriteUrl->getProductNameUrl($products_id), '<span itemprop="name">' . $this->productsCommon->getProductsName($products_id) . '</span>', 'itemprop="url"');

    return $products_name;
  }

//display a button on the stock (good, alert, out of stock).

  /**
   * Retrieves product stock information based on the specified constant.
   *
   * @param string $constant Specifies the type of stock information to retrieve ('number' or 'image').
   * @param string $products_id The ID of the product for which stock information is requested.
   * @param string $tag An optional tag appended to the stock information (used when $constant is 'number').
   * @return string The stock information as a string, or an empty string if the constant is unrecognized.
   */
  public function getStock(string $constant, string $products_id, string $tag = ' '): string
  {
    if ($constant == 'number') {
      $products_stock = CLICSHOPPING::getDef('text_stock') . $tag . $this->productsCommon->getProductsStock($products_id);
    } elseif ($constant == 'image') {
      $products_stock = $this->productsCommon->getDisplayProductsStock($products_id);
    } else {
      $products_stock = '';
    }

    return $products_stock;
  }

  /**
   * Retrieves the flash discount information for a given product.
   *
   * @param string $products_id The ID of the product.
   * @param string $tag A delimiter tag to separate the discount text. Defaults to '<br />'.
   *
   * @return string The formatted flash discount information for the specified product.
   */
  public function getFlashDiscount(string $products_id, string $tag = '<br />'): string
  {
    $products_flash_discount = '';

    if (!empty($this->productsCommon->getProductsFlashDiscount($products_id))) {
      $products_flash_discount = CLICSHOPPING::getDef('text_flash_discount') . $tag . $this->productsCommon->getProductsFlashDiscount($products_id);
    }

    return $products_flash_discount;
  }

  /**
   * Retrieves and formats the minimum order quantity required for a product display.
   *
   * @param string $products_id The ID of the product for which the minimum order quantity is being retrieved.
   * @param string $tag An optional tag to be appended to the formatted output.
   * @return string The formatted minimum order quantity display for the product, or an empty string if no minimum order quantity is required.
   */
  public function getMinOrderQuantityProductDisplay(string $products_id, string $tag = ' '): string
  {
    if ($this->productsCommon->getProductsMinimumQuantityToTakeAnOrder($products_id) > 1) {
      $min_order_quantity_products_display = CLICSHOPPING::getDef('min_qty_product') . $tag . $this->productsCommon->getProductsMinimumQuantityToTakeAnOrder($products_id);

      return $min_order_quantity_products_display;
    } else {
      return '';
    }
}

// display a message in public function the customer group applied - before submit button

  /**
   * Retrieves a button view message based on product minimum quantity and available quantity.
   *
   * @param int|string $products_id The ID of the product to check.
   * @return string|null The message allowing order submission, or null if conditions are not met.
   */
  public function getButtonView($products_id)
  {
    if ($this->productsCommon->getProductsMinimumQuantity($products_id) != 0 && $this->productsCommon->getProductsQuantity($products_id) != 0) {
      $submit_button_view = $this->productsCommon->getProductsAllowingTakeAnOrderMessage();
      return $submit_button_view;
    }
}

  /**
   * Retrieves the display input quantity for a product based on the given parameters.
   *
   * @param string $constant A flag to determine the condition for fetching the input quantity.
   * @param string $products_id The identifier of the product.
   * @param string $tag An optional string used for concatenation in the returned value.
   * @return string The formatted input quantity information or an empty string if conditions are not met.
   */
  public function getDisplayInputQuantity(string $constant, string $products_id, string $tag = ' '): string
  {
    $input_quantity = '';

    if ($constant == 'False') {
      $input_quantity = '';

      if ($this->productsCommon->getProductsAllowingToInsertQuantity($products_id)) {
        if ($this->productsCommon->getHasProductAttributes($products_id) === false) {
          $input_quantity = CLICSHOPPING::getDef('text_customer_quantity') . $tag . $this->productsCommon->getProductsAllowingToInsertQuantity($products_id);
        }
      }
    }

    return $input_quantity;
  }

  /**
   * Retrieves the unit type of product quantity for a given product, optionally formatted with a custom tag.
   *
   * @param string $products_id The ID of the product for which the quantity unit type should be retrieved.
   * @param string $tag An optional tag to append to the quantity unit type. Defaults to a single space.
   * @return string The formatted product quantity unit type.
   */
  public function getProductQuantityUnitType(string $products_id, string $tag = ' '): string
  {
    $products_quantity_unit = '';

    if ($this->customer->getCustomersGroupID() == 0) {
      if (!empty($this->productsCommon->getProductQuantityUnitType($products_id))) {
        $products_quantity_unit = CLICSHOPPING::getDef('text_products_quantity_type') . $tag . $this->productsCommon->getProductQuantityUnitType($products_id);
      }
} else {
      if (!empty($this->productsCommon->getProductQuantityUnitTypeCustomersGroup($products_id))) {
        $products_quantity_unit = CLICSHOPPING::getDef('text_products_quantity_type') . $tag . $this->productsCommon->getProductQuantityUnitTypeCustomersGroup($products_id);
      }
}

    return $products_quantity_unit;
  }

  /**
   * Generates an HTML button for viewing product details based on the provided parameters.
   *
   * @param string $constant A value that determines whether the button should be generated or not.
   * @param string $products_id The ID of the product for which the button is generated.
   * @param string|null $icon Optional icon to display on the button. Defaults to null.
   * @param string $button_color The color of the button. Defaults to 'info'.
   * @param mixed $params Optional additional parameters for the button element. Defaults to null.
   * @param string $button_size The size of the button. Defaults to 'sm'.
   * @return string The generated HTML button as a string.
   */
  public function getButtonViewDetails(string $constant, string $products_id, ?string $icon = null, string $button_color = 'info', $params = null, string $button_size = 'sm'): string
  {
    $button = '';

    if ($constant == 'False') {
      if (is_null($icon)) {
        $button = HTML::button(CLICSHOPPING::getDef('button_details'), '', $this->rewriteUrl->getProductNameUrl($products_id), $button_color, $params, $button_size);
      } else {
        $button = HTML::button(null, $icon, $this->rewriteUrl->getProductNameUrl($products_id), $button_color, $params, $button_size);
      }
}

    return $button;
  }

  /**
   * Generates and returns an HTML image tag wrapped in a link for the specified product.
   *
   * @param string $constant Designates the size of the image ('Medium' or other). Determines which image size to use.
   * @param string $products_id The ID of the product for which the image is being generated.
   * @param string|array $parameters Optional additional parameters for the image tag. Defaults to an empty string.
   * @param bool $responsive Indicates whether the image should support responsive behavior. Defaults to true.
   * @param string $css Optional CSS class to be applied to the image. Defaults to an empty string.
   * @return string The generated HTML string containing the linked product image.
   */
  public function getImage(string $constant, string $products_id, $parameters = '', bool $responsive = true, string $css = ''): string
  {
    if ($constant == 'Medium') {
      if ($this->productsCommon->getProductsImageMedium($products_id) !== false) {
        $products_image = HTML::link($this->rewriteUrl->getProductNameUrl($products_id), HTML::image($this->template->getDirectoryTemplateImages() . $this->productsCommon->getProductsImageMedium($products_id), HTML::outputProtected($this->productsCommon->getProductsName($products_id)), \defined('MEDIUM_IMAGE_WIDTH') ? (int)MEDIUM_IMAGE_WIDTH : 0, defined('MEDIUM_IMAGE_HEIGHT') ? (int)MEDIUM_IMAGE_HEIGHT : 0, $parameters, $responsive, $css));
      } else {
        $products_image = HTML::link($this->rewriteUrl->getProductNameUrl($products_id), HTML::image($this->template->getDirectoryTemplateImages() . $this->productsCommon->getProductsImage($products_id), HTML::outputProtected($this->productsCommon->getProductsName($products_id)), \defined('SMALL_IMAGE_WIDTH') ? (int)SMALL_IMAGE_WIDTH : 0, \defined('SMALL_IMAGE_HEIGHT') ? (int)SMALL_IMAGE_HEIGHT : 0, $parameters, $responsive, $css));
      }
} else {
      $products_image = HTML::link($this->rewriteUrl->getProductNameUrl($products_id), HTML::image($this->template->getDirectoryTemplateImages() . $this->productsCommon->getProductsImage($products_id), HTML::outputProtected($this->productsCommon->getProductsName($products_id)), \defined('SMALL_IMAGE_WIDTH') ? (int)SMALL_IMAGE_WIDTH : 0, \defined('SMALL_IMAGE_HEIGHT') ? (int)SMALL_IMAGE_HEIGHT : 0, $parameters, $responsive, $css));
    }

    return $products_image;
  }

  /**
   * Generates a ticker (link and image) for a product based on its status, such as special, favorite, featured, or new.
   *
   * @param string $constant A flag indicating whether ticker functionality is enabled.
   * @param string $products_id The ID of the product for which the ticker should be generated.
   * @param string $cssSpecial The CSS class for the "special" ticker.
   * @param string $cssFavorites The CSS class for the "favorites" ticker.
   * @param string $cssFeatured The CSS class for the "featured" ticker.
   * @param string $cssProductsNew The CSS class for the "new products" ticker.
   * @return string The generated ticker HTML as a string, or an empty string if no ticker is applicable.
   */
  public function getTicker(string $constant, string $products_id, string $cssSpecial, string $cssFavorites, string $cssFeatured, string $cssProductsNew): string
  {
    if ($constant !== 'True') {
      return '';
    }

    $id = (int)$products_id;

    if ($this->productsCommon->getProductsTickerSpecials($id) === true) {
      return HTML::link($this->rewriteUrl->getProductNameUrl($products_id), HTML::tickerImage(CLICSHOPPING::getDef('text_ticker_specials'), $cssSpecial, true));
    }

    if ($this->productsCommon->getProductsTickerFavorites($id) === true) {
      return HTML::link($this->rewriteUrl->getProductNameUrl($products_id), HTML::tickerImage(CLICSHOPPING::getDef('text_ticker_favorite'), $cssFavorites, true));
    }

    if ($this->productsCommon->getProductsTickerFeatured($id) === true) {
      return HTML::link($this->rewriteUrl->getProductNameUrl($products_id), HTML::tickerImage(CLICSHOPPING::getDef('text_ticker_featured'), $cssFeatured, true));
    }

    if ($this->productsCommon->getProductsTickerProductsNew($id) === true) {
      return HTML::link($this->rewriteUrl->getProductNameUrl($products_id), HTML::tickerImage(CLICSHOPPING::getDef('text_ticker_products_new'), $cssProductsNew, true));
    }

    return '';
  }

  /**
   * Generates a ticker percentage element for a specific product based on its ID and a CSS class,
   * if the provided condition is satisfied.
   *
   * @param string $constant A toggle condition to determine if the ticker percentage should be generated. Typically 'True' or 'False'.
   * @param string $products_id The ID of the product for which the ticker percentage is generated.
   * @param string $cssPourcentage The CSS class used for styling the ticker percentage.
   * @return string The generated ticker percentage element as an HTML string, or an empty string if the condition is not met.
   */
  public function getTickerPourcentage(string $constant, string $products_id, string $cssPourcentage): string
  {
    if ($constant !== 'True') {
      return '';
    }

    $percentage = $this->productsCommon->getProductsTickerSpecialsPourcentage($products_id);

    if (empty($percentage)) {
      return '';
    }

    return HTML::link($this->rewriteUrl->getProductNameUrl($products_id), HTML::tickerImage($percentage, $cssPourcentage, true));
  }

  /**
   * Retrieves the model of a product for a given product ID, optionally formatted with a custom tag.
   *
   * @param string $products_id The ID of the product for which the model should be retrieved.
   * @param string $tag An optional tag to prepend to the product model. Defaults to a single space.
   * @return string The formatted product model, or an empty string if the model is not available.
   */
  public function getProductsModel(int|string $products_id, string $tag = ' '): string
  {
    if (!empty($this->productsCommon->getProductsModel($products_id))) {
      $products_model = $tag . $this->productsCommon->getProductsModel($products_id);

      return $products_model;
    } else {
      return '';
    }
}

  /**
   * Retrieves the manufacturer name for a given product, optionally formatted with a custom tag.
   *
   * @param string $products_id The ID of the product for which the manufacturer name should be retrieved.
   * @param string $tag An optional tag to append to the manufacturer name. Defaults to a single space.
   * @return string The formatted manufacturer name or an empty string if no manufacturer is found.
   */
  public function getProductsManufacturer(int|string $products_id, string $tag = ' '): string
  {
    if (!empty($this->productsCommon->getProductsManufacturer($products_id))) {
      $products_manufacturers = CLICSHOPPING::getDef('text_manufacturer') . $tag . $this->productsCommon->getProductsManufacturer($products_id);

      return $products_manufacturers;
    } else {
      return '';
    }
}

  /**
   * Retrieves the price of a product per weight unit for a given product, optionally formatted with a custom tag.
   *
   * @param string $products_id The ID of the product for which the price per weight unit should be retrieved.
   * @param string $tag An optional tag to append to the price per weight unit. Defaults to a single space.
   * @return string The formatted price per weight unit, or an empty string if the price information is not available.
   */
  public function getProductsPriceByWeight(int|string $products_id, string $tag = ' '): string
  {
    if (!empty($this->productsCommon->getProductsPriceByWeight($products_id))) {
      $product_price_kilo = CLICSHOPPING::getDef('text_products_price_kilo') . $tag . $this->productsCommon->getProductsPriceByWeight($products_id);

      return $product_price_kilo;
    } else {
      return '';
    }
}

  /**
   * Retrieves the availability date of a product in a formatted manner if it is set and in the future.
   *
   * @param mixed $products_id The ID of the product for which the availability date should be retrieved.
   * @param string $tag An optional tag to prepend to the formatted availability date. Defaults to a single space.
   * @return string The formatted product availability date or an empty string if no date is set or available.
   */
  public function getProductsDateAvailable(int|string $products_id, string $tag = ' '): string
  {
    if (!empty($this->productsCommon->getProductsDateAvailable($products_id))) {
      $products_date_available = $this->productsCommon->getProductsDateAvailable($products_id);
      if ($products_date_available > date('Y-m-d H:i:s')) {
        $products_date_available = CLICSHOPPING::getDef('text_date_available') . $tag . DateTime::toShort($products_date_available);
      }

      return $products_date_available;
    } else {
      return '';
    }
}

  /**
   * Retrieves the shop-only availability message for a given product.
   *
   * @param mixed $products_id The ID of the product to check its shop-only availability.
   * @return string The shop-only availability message if applicable, or an empty string otherwise.
   */
  public function getProductsOnlyTheShop(int|string $products_id): string
  {
    if ($this->productsCommon->getProductsOnlyTheShop($products_id) == 1) {
      $products_only_shop = CLICSHOPPING::getDef('text_only_shop');

      return $products_only_shop;
    } else {
      return '';
    }
}

  /**
   * Determines if a product is only available on the website and retrieves the corresponding label if applicable.
   *
   * @param mixed $products_id The ID of the product to check.
   * @return string The label indicating the product is only available on the website, or an empty string if not applicable.
   */
  public function getProductsOnlyOnTheWebSite(int|string $products_id): string
  {
    if ($this->productsCommon->getProductsOnlyOnTheWebSite($products_id) == 1) {
      $products_only_web = CLICSHOPPING::getDef('text_only_web');

      return $products_only_web;
    } else {
      return '';
    }
}

  /**
   * Retrieves the packaging type of a product for a given product ID, optionally formatted with a custom tag.
   *
   * @param mixed $products_id The ID of the product for which the packaging type should be retrieved.
   * @param string $tag An optional tag to format the packaging type. Defaults to a single space.
   * @return string The formatted product packaging type. Returns an empty string if packaging type is not set or invalid.
   */
  public function getProductsPackaging(int|string $products_id, string $tag = ' '): string
  {
    $products_packaging = $this->productsCommon->getProductsPackaging($products_id);

    if ($products_packaging == 0) $products_packaging = '';
    if ($products_packaging == 1) $products_packaging = CLICSHOPPING::getDef('text_products_info_packaging_text') . $tag . CLICSHOPPING::getDef('text_products_packaging_new');
    if ($products_packaging == 2) $products_packaging = CLICSHOPPING::getDef('text_products_info_packaging_text') . $tag . CLICSHOPPING::getDef('text_products_packaging_repackaged');
    if ($products_packaging == 3) $products_packaging = CLICSHOPPING::getDef('text_products_info_packaging_text') . $tag . CLICSHOPPING::getDef('text_products_packaging_used');

    return $products_packaging;
  }

  /**
   * Retrieves the shipping delay information for a given product, optionally formatted with a custom tag.
   *
   * @param mixed $products_id The ID of the product for which the shipping delay information should be retrieved.
   * @param string $tag An optional tag to append to the shipping delay information. Defaults to a single space.
   * @return string The formatted shipping delay information, or an empty string if no delay information is available.
   */
  public function getProductsShippingDelay(int|string $products_id, string $tag = ' '): string
  {
    if (!empty($this->productsCommon->getProductsShippingDelay($products_id))) {
      $products_shipping_delay = CLICSHOPPING::getDef('text_display_shipping_delay') . $tag . $this->productsCommon->getProductsShippingDelay($products_id);

      return $products_shipping_delay;
    } else {
      return '';
    }
}

  /**
   * Retrieves the shipping delay message for out-of-stock products for a given product, optionally formatted with a custom tag.
   *
   * @param mixed $products_id The ID of the product to retrieve the out-of-stock shipping delay message for.
   * @param string $tag An optional tag to append to the shipping delay message. Defaults to a single space.
   * @return string The formatted out-of-stock shipping delay message. Returns an empty string if no delay message is found.
   */
  public function getProductsShippingDelayOutOfStock(int|string $products_id, string $tag = ' '): string
  {
    if (!empty($this->productsCommon->getProductsShippingDelayOutOfStock($products_id))) {
      $products_shipping_delay_out_of_stock = CLICSHOPPING::getDef('text_display_shipping_delay_out_of_stock') . $tag . $this->productsCommon->getProductsShippingDelayOutOfStock($products_id);

      return $products_shipping_delay_out_of_stock;
    } else {
      return '';
    }
}

  /**
   * Retrieves and processes the head tag(s) associated with a given product.
   *
   * @param mixed $products_id The ID of the product for which the head tag(s) should be retrieved.
   * @return array|string An array of processed head tag(s) if available, or an empty string if no tags are found.
   */
  public function getProductsHeadTag(int|string $products_id)
  {
    if (!empty($this->productsCommon->getProductsHeadTag($products_id))) {
      $products_tag = $this->productsCommon->getProductsHeadTag($products_id);
      $delimiter = ',';
      $products_tag = trim(preg_replace('|\\s*(?:' . preg_quote($delimiter, null) . ')\\s*|', $delimiter, $products_tag));
      $tag = explode(',', $products_tag);

      return $tag;
    } else {
      return '';
    }
}

  /**
   * Retrieves the volume of a product for a given product ID, optionally formatted with a custom tag.
   *
   * @param mixed $products_id The ID of the product for which the volume should be retrieved.
   * @param string $tag An optional tag to append to the product volume. Defaults to a single space.
   * @return string The formatted product volume. Returns an empty string if no volume is found.
   */
  public function getProductsVolume(int $products_id, string $tag = ' '): string
  {
    if (!empty($this->productsCommon->getProductsVolume($products_id))) {
      $products_volume = CLICSHOPPING::getDef('text_products_volume') . $tag . $this->productsCommon->getProductsVolume($products_id);

      return $products_volume;
    } else {
      return '';
    }
}

  /**
   * Retrieves the weight information of a product, formatted with a specified tag and weight symbol.
   *
   * @param mixed $products_id The ID of the product for which the weight should be retrieved.
   * @param string $tag An optional tag to format the weight value and weight symbol. Defaults to ' / '.
   * @return string The formatted product weight including the weight symbol. Returns an empty string if no weight is available.
   */
  public function getProductsWeight(int $products_id, string $tag = ' / '): string
  {
    if (!empty($this->productsCommon->getProductsWeight($products_id))) {
      $weight_symbol = $this->productsCommon->getSymbolWeightByProducts($this->productsCommon->getWeightClassIdByProducts($products_id));
      $products_weight = CLICSHOPPING::getDef('text_products_weight') . '  ' . $this->productsCommon->getProductsWeight($products_id) . $tag . $weight_symbol;

      return $products_weight;
    } else {
      return '';
    }
}

  /**
   * Retrieves the name of the manufacturer for a given product as a formatted link.
   *
   * @param mixed $products_id The ID of the product for which the manufacturer name should be retrieved.
   * @return string The formatted manufacturer name as a hyperlink.
   */
  public function getManufacturerName(int $products_id): string
  {
    if (isset($_GET['manufacturersId']) && !is_null($_GET['manufacturersId']) && is_numeric($_GET['manufacturersId'])) {
      $manufacturer_id = HTML::sanitize($_GET['manufacturersId']);
      $name = HTML::link(CLICSHOPPING::link(null, 'Products&Description&manufacturersId=' . $manufacturer_id . '&products_id=' . $products_id), '<span itemprop="name">' . $this->productsCommon->getProductsName($products_id) . '</span>', 'itemprop="url"');
    } else {
      $name = HTML::link(CLICSHOPPING::link(null, 'Products&Description&' . ($this->category->getPath() ? 'cPath=' . $this->category->getPath() . '&' : '') . 'products_id=' . $products_id), '<span itemprop="name">' . $this->productsCommon->getProductsName($products_id) . '</span>', 'itemprop="url"');
    }

    return $name;
  }

  /**
   * Generates the manufacturer image link for a given product.
   *
   * @param mixed $products_id The ID of the product for which the manufacturer image is to be generated.
   * @param string $products_image The product image file name to be included in the link.
   * @return string A string containing the HTML link element for the manufacturer image.
   */
  public function getManufacturerImage(int $products_id, string $products_image): string
  {
    if (isset($_GET['manufacturersId']) && is_numeric($_GET['manufacturersId'])) {
      $manufacturer_id = HTML::sanitize($_GET['manufacturersId']);
      $image = HTML::link(CLICSHOPPING::link(null, 'Products&Description&manufacturersId=' . $manufacturer_id . '&products_id=' . $products_id), HTML::image($this->template->getDirectoryTemplateImages() . $products_image, HTML::outputProtected($this->productsCommon->getProductsName($products_id)), (int)SMALL_IMAGE_WIDTH, (int)SMALL_IMAGE_HEIGHT, null, true));
    } else {
      $image = HTML::link($this->rewriteUrl->getProductNameUrl($products_id, 'products_id=', ($this->category->getPath() ? 'cPath=' . $this->category->getPath() . '&' : '') . 'products_id=' . $products_id), HTML::image($this->template->getDirectoryTemplateImages() . $products_image, HTML::outputProtected($this->productsCommon->getProductsName($products_id)), (int)SMALL_IMAGE_WIDTH, (int)SMALL_IMAGE_HEIGHT, null, true));
    }

    return $image;
  }

  /**
   * Retrieves the length dimension of a given product.
   *
   * @param mixed $products_id The ID of the product for which the length dimension should be retrieved.
   * @return string The length of the product, formatted with a descriptive label. Returns an empty string if no dimension is found.
   */
  public function getProductslength(int $products_id): string
  {
    if (!empty($this->productsCommon->getProductsDimension($products_id))) {
      $products_length = CLICSHOPPING::getDef('text_products_length') . ' : ' . $this->productsCommon->getProductsDimension($products_id);

      return $products_length;
    } else {
      return '';
    }
}

  /**
   * Generates a JSON-LD structured data script for a product, including details such as name, description, SKU, brand, price, stock status, and reviews.
   *
   * @param mixed $products_id The ID of the product for which the JSON-LD data is generated.
   * @return string The JSON-LD structured data script for the specified product.
   */
  public function getProductJsonLd(int $products_id): string
  {
    $CLICSHOPPING_Reviews = Registry::get('Reviews');

    if ($this->productsCommon->getProductsStock($products_id) > 0) {
      $stock = 'InStock';
    } else {
      $stock = 'OutofStock';
    }

    if (\defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'true') {
      $stock = 'InStock';
    }

    $products_packaging = $this->productsCommon->getProductsPackaging($products_id);

    if ($products_packaging == 0) $products_packaging = 'https://schema.org/NewCondition'; // default newCondition
    if ($products_packaging == 1) $products_packaging = 'https://schema.org/NewCondition';
    if ($products_packaging == 2) $products_packaging = 'https://schema.org/RefurbishedCondition';
    if ($products_packaging == 3) $products_packaging = 'https://schema.org/UsedCondition';

    $price = $this->productsCommon->getDisplayPriceGroupWithoutCurrencies($products_id);

    if ($CLICSHOPPING_Reviews->getAverageProductReviews($products_id) == 0) {
      $review_average = 1;
    } else {
      $review_average = $CLICSHOPPING_Reviews->getAverageProductReviews($products_id);
    }

//description
    $str = $this->productsCommon->getProductsDescription($products_id);
    $str = str_replace('"', '', $str);
    $str = HTMLOverrideCommon::cleanHtmlOptimized($str);
    $description = HTMLOverrideCommon::cleanHtmlOptimized($str);

    $name = str_replace('"', '', $this->productsCommon->getProductsName($products_id));
    $name = HTMLOverrideCommon::cleanHtmlOptimized($name);

    $json = [
      '@context'    => 'https://schema.org/',
      '@type'       => 'Product',
      'name'        => $name,
      'model'       => $this->productsCommon->getProductsModel($products_id),
      'image'       => [
        HTTP::typeUrlDomain() . $this->template->getDirectoryTemplateImages() . $this->productsCommon->getProductsImage($products_id),
        HTTP::typeUrlDomain() . $this->template->getDirectoryTemplateImages() . $this->productsCommon->getProductsImageMedium($products_id),
      ],
      'description' => $description,
      'sku'         => $this->productsCommon->getProductsSKU($products_id),
      'mpn'         => $this->productsCommon->getProductsMNP($products_id),
      'jan'         => $this->productsCommon->getProductsJAN($products_id),
      'isbn'        => $this->productsCommon->getProductsISBN($products_id),
    ];

    $manufacturer = $this->productsCommon->getProductsManufacturer($products_id);
    if (!empty($manufacturer)) {
      $json['brand'] = [
        '@type' => 'Thing',
        'name'  => $manufacturer,
      ];
    }

    $json['aggregateRating'] = [
      '@type'       => 'AggregateRating',
      'ratingValue' => $review_average,
      'reviewCount' => $CLICSHOPPING_Reviews->getCount($products_id),
    ];

    $offer = [
      '@type'          => 'Offer',
      'url'            => $this->rewriteUrl->getProductNameUrl($products_id),
      'priceCurrency'  => HTML::output(HTML::sanitize($_SESSION['currency'])),
      'price'          => $price,
      'priceValidUntil'=> '',
      'availability'   => 'https://schema.org/' . $stock,
      'seller'         => [
        '@type' => 'Organization',
        'name'  => HTML::output(STORE_NAME),
      ],
    ];

    if (!empty($products_packaging)) {
      $offer['itemCondition'] = 'https://schema.org/' . $products_packaging;
    }

    $json['offers'] = $offer;

    $output = '<script defer type="application/ld+json">' . "\n"
      . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
      . '</script>';

    return $output;
  }
}
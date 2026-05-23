<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Currency\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Tax;
use function count;
use function is_null;
use function is_string;

class Currencies
{
  public array $currencies = [];
  private mixed $db;
  protected $show;
  protected string $selected;
  protected string $default;

  /**
   * Constructor method.
   *
   * Initializes the database connection and loads the currencies data from the database.
   * Populates the currencies array with relevant details and determines the default currency
   * based on its value.
   *
   * @return void
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->currencies = [];

    $Qcurrencies = $this->db->prepare('select currencies_id as id,
                                                code,
                                                title,
                                                symbol_left,
                                                symbol_right,
                                                decimal_point,
                                                thousands_point,
                                                decimal_places,
                                                value,
                                                surcharge
                                         from :table_currencies
                                         where status = 1
                                        ');

    $Qcurrencies->execute();
    $Qcurrencies->setCache('currencies');

    $currencies = $Qcurrencies->fetchAll();

    foreach ($currencies as $c) {
      $this->currencies[$c['code']] = [
        'id' => (int)$c['id'],
        'title' => $c['title'],
        'symbol_left' => $c['symbol_left'],
        'symbol_right' => $c['symbol_right'],
        'decimal_point' => $c['decimal_point'],
        'thousands_point' => $c['thousands_point'],
        'decimal_places' => (int)$c['decimal_places'],
        'value' => (float)$c['value'],
        'surcharge' => (float)$c['surcharge']
      ];
    }

    if (!isset($this->default) && ((float)$c['value'] === 1.0)) {
      $this->default = $c['code'];
    }
  }

  /**
   * @param float|null $number
   * @param bool $calculate_currency_value
   * @param string|null $currency_type
   * @param mixed $currency_value
   * @return string|null
   */
  public function format(float|null $number, bool $calculate_currency_value = true, string|null $currency_type = null, mixed $currency_value = null): ?string
  {
    if (empty($currency_type) && CLICSHOPPING::getSite() === 'Shop') {
      $currency_type = $_SESSION['currency'];
    }

    if (CLICSHOPPING::getSite() === 'ClicShoppingAdmin') {
      $currency_type = DEFAULT_CURRENCY;
    }

    if ($calculate_currency_value === true) {
      $rate = $currency_value ?? $this->currencies[$currency_type]['value'];

      if ($this->currencies[$currency_type]['surcharge'] > 0) {
        $rate += ($rate * $this->currencies[$currency_type]['surcharge']);
      }

      $format_string = '&nbsp;' . $this->currencies[$currency_type]['symbol_left'] . number_format(round($number * $rate, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . '&nbsp;' . $this->currencies[$currency_type]['symbol_right'];
    } else {
      $format_string = '&&nbsp;' . $this->currencies[$currency_type]['symbol_left'] . number_format(round($number, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . '&nbsp;' . $this->currencies[$currency_type]['symbol_right'];
    }

    return $format_string;
  }

  /**
   * @param float|null $products_price The base price of the product.
   * @param mixed $products_tax The tax to be applied to the product price.
   * @param int $quantity The quantity of products. Defaults to 1.
   * @return float The total calculated price including tax for the given quantity.
   */
  public function calculatePrice(float|null $products_price, $products_tax, int $quantity = 1)
  {
    return round(Tax::addTax($products_price, $products_tax), $this->currencies[$_SESSION['currency']]['decimal_places']) * $quantity;
  }

  /**
   * @param string $code
   * @return bool
   */
  public function isSet(string $code): bool
  {
    if (isset($this->currencies[$code]) && !is_null($this->currencies[$code])) {
      return true;
    } else {
      return false;
    }
  }

  /**
   * @param string $code
   * @return float
   */
  public function getValue(string $code): float
  {
    return $this->currencies[$code]['value'];
  }

  /**
   * @param string $code The currency code for which to retrieve the decimal places.
   * @return string The number of decimal places for the specified currency.
   */
  public function getDecimalPlaces(string $code): string
  {
    return $this->currencies[$code]['decimal_places'];
  }

  /**
   * Generates and returns a price tag based on the tax tag retrieved from the Tax registry.
   *
   * @return string The generated price tag.
   */
  private function priceTag(): string
  {
    $CLICSHOPPING_Tax = Registry::get('Tax');

    $tag = $CLICSHOPPING_Tax->getTag();

    if (isset($tag)) {
      $pricetag = $tag;
    } else {
      $pricetag = $tag;
    }

    return $pricetag;
  }

  /**
   * Display the formatted price of a product, including tax and optional discount calculations based on quantity.
   *
   * @param float|null $products_price The base price of the product.
   * @param float|null $products_tax The tax rate applicable to the product.
   * @param int $quantity The quantity of the product being purchased. Defaults to 1.
   * @return string The formatted price as a string, optionally including tax, discounts, or complying with display rules.
   */
  public function displayPrice(float|null $products_price, float|null $products_tax, int $quantity = 1)
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

// Marketing : price is update by discount of the quantity and in function the product
//Display only in shoppingCart
    if (isset($_GET['Cart']) && $_GET['Cart'] == 'Cart') {
      $new_price_with_discount_quantity = $CLICSHOPPING_ProductsCommon->getProductsNewPriceByDiscountByQuantity($_SESSION['ProductsID'], $quantity, $products_price);

      if ($new_price_with_discount_quantity > 0) {
        $products_price = $CLICSHOPPING_ProductsCommon->getProductsNewPriceByDiscountByQuantity($_SESSION['ProductsID'], $quantity, $products_price);
        unset($_SESSION['ProductsID']);
      }
    }

    if ((($CLICSHOPPING_Customer->getCustomersGroupID() == 0) && (DISPLAY_PRODUCT_PRICE_VALUE_TAX == 'true')) || (($CLICSHOPPING_Customer->getCustomersGroupID() != 0) && (DISPLAY_PRODUCT_PRICE_VALUE_TAX_PRO == 'true'))) {
      return $this->format($this->calculatePrice($products_price, $products_tax, $quantity)) . ' ' . $this->priceTag();
    } else {

// do not display if the price 0
      if (NOT_DISPLAY_PRICE_ZERO == 'false') {
        if ($products_price > 0) {
          return $this->format($this->calculatePrice($products_price, $products_tax, $quantity));
        } else {
          return '';
        }
      } else {
        return $this->format($this->calculatePrice($products_price, $products_tax, $quantity));
      } // END NOT_DISPLAY_PRICE_ZERO
    }
  }

  /**
   * Calculates and formats the price per kilogram of a product based on its price, weight, tax, and quantity.
   *
   * @param float $products_price The price of the product.
   * @param float $products_weight The weight of the product.
   * @param float $value The value determining if price per kilo calculation is applicable.
   * @param float $products_tax The tax rate applied to the product price.
   * @param int $quantity The quantity of the product (default is 1).
   * @return string|false The formatted price per kilogram with optional price tag, or false if the calculation is not applicable.
   */
  public function displayPriceKilo(float $products_price, float $products_weight, float $value, float $products_tax, int $quantity = 1)
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (($products_weight > 0) && ($products_weight > '0') && ($value == 1)) {
      $products_price_kilo = round(($products_price / $products_weight), 2);

      if ((($CLICSHOPPING_Customer->getCustomersGroupID() == 0) && (DISPLAY_PRODUCT_PRICE_VALUE_TAX == 'true')) || (($CLICSHOPPING_Customer->getCustomersGroupID() != 0) && (DISPLAY_PRODUCT_PRICE_VALUE_TAX_PRO == 'true'))) {
        return $this->format($this->calculatePrice($products_price_kilo, $products_tax, $quantity)) . ' ' . $this->priceTag() . '';
      } else {
        return $this->format($this->calculatePrice($products_price_kilo, $products_tax, $quantity));
      }
    } else {
      return false;
    }
  }

  /**
   * Trims unnecessary decimal zeros from a number based on the specified currency's decimal places.
   *
   * @param string $number The numerical value as a string to be trimmed.
   * @param string|null $currency_code The currency code to determine the decimal places. If null, the default currency is used.
   * @return string The trimmed numerical value as a string.
   */
  public function trim(string $number, string|null $currency_code = null): string
  {
    if (!isset($currency_code)) {
      $currency_code = $this->getDefault();
    }

    $dec_point = '.';

    $currency = $_SESSION['currency'];

    if (!empty($this->currencies[$currency]['thousands_point'])) {
      $dec_point = $this->currencies[$currency]['thousands_point'];
    }

    $number = str_replace($dec_point . str_repeat('0', $this->currencies[$currency_code]['decimal_places']), '', $number);

    return $number;
  }

  /**
   * Retrieves the value of a specific key within the currency data or all currency data for a given currency code.
   *
   * @param string|null $key The specific key to retrieve. If null, all data for the currency will be returned.
   * @param string|null $currency_code The currency code to retrieve the data for. If null, the default currency code is used.
   *
   * @return mixed The value associated with the given key, or all data for the specified currency.
   */
  public function get(string|null $key = null, string|null $currency_code = null)
  {
    if (!isset($currency_code)) {
      $currency_code = $this->getDefault();
    }

    if (isset($key)) {
      return $this->currencies[$currency_code][$key];
    }

    return $this->currencies[$currency_code];
  }

  /**
   * Retrieves the currency code corresponding to the given currency ID.
   *
   * @param int $id The ID of the currency to be searched.
   * @return string|null Returns the currency code if found, or null if no matching currency is found.
   */
  public function getCode(int $id): ?string
  {
    foreach ($this->currencies as $code => $c) {
      if ($c['id'] === $id) {
        return $code;
      }
    }

    return null;
  }

  /**
   * Generates a dropdown for selecting currencies if multiple currencies are available.
   *
   * @param string $class Optional CSS class to apply to the dropdown element.
   * @return string A string containing the HTML markup for the currency dropdown or an empty string if conditions are not met.
   */
  public function getCurrenciesDropDown(string $class = '')
  {
    if ((count($this->currencies) > 1)) {
      reset($this->currencies);
      $currency_header = '';

      $currencies_array = $this->getAll();

      $hidden_get_variables = '';

      foreach ($_GET as $key => $value) {
        if (is_string($value) && ($key != 'currency') && ($key != session_name()) && ($key != 'x') && ($key != 'y')) {
          $hidden_get_variables .= HTML::hiddenField($key, $value);
        }
      }

      if (!isset($_GET['Checkout'])) {
        $currency_header .= HTML::form('currencies', CLICSHOPPING::link(), 'get', null, ['session_id' => true]);
        $currency_header .= '<label for="CurrencyDropDown" class="visually-hidden"></label>';
        $currency_header .= HTML::selectField('currency', $currencies_array, HTML::sanitize($_SESSION['currency']), 'id="CurrencyDropDown" class="' . $class . '" onchange="this.form.submit();"') . $hidden_get_variables;
        $currency_header .= '</form>';
      } else {
        $currency_header = '';
      }

      return $currency_header;
    }
  }


  /**
   * Retrieves all currencies as an array of associative arrays containing their ID and title.
   *
   * @return array An array of currencies, where each currency is represented by an associative array with 'id' and 'text' keys.
   */
  public function getAll(): array
  {
    $result = [];

    foreach ($this->currencies as $code => $c) {
      $result[] = [
        'id' => $code,
        'text' => $c['title']
      ];
    }

    return $result;
  }

  /**
   * Formats and displays a number with the associated currency symbol.
   *
   * @param float $number The numeric value to be formatted.
   * @param string|null $currency_code The currency code to determine formatting. Defaults to the default currency if not provided.
   * @param float|null $currency_value The conversion value for the currency. Defaults to null.
   * @param bool $calculate Whether to recalculate the currency value. Defaults to true.
   * @return string The formatted string that includes the currency symbol and formatted number.
   */
  public function show(float $number, string|null $currency_code = null, float|null $currency_value = null, bool $calculate = true): string
  {
    if (!isset($currency_code)) {
      $currency_code = $this->getDefault();
    }

    $value = $this->raw($number, $currency_code, $currency_value, $calculate, true);

    return $this->currencies[$currency_code]['symbol_left'] . $value . $this->currencies[$currency_code]['symbol_right'];
  }

  /**
   * Converts a numeric value into a formatted string based on the specified currency and optional calculations.
   *
   * @param float $number The numeric value to be formatted.
   * @param string|null $currency_code The currency code to use for formatting. If not provided, the default currency is used.
   * @param float|null $currency_value The value of the currency for calculations. If not provided, the default value for the specified currency is used.
   * @param bool $calculate Determines whether to calculate the value using the currency value and surcharge. Defaults to true.
   * @param bool $use_locale Indicates whether to use locale-specific numeric formatting. Defaults to false.
   * @return string The formatted currency value as a string.
   */
  public function raw(float $number, ?string $currency_code = null, ?float $currency_value = null, bool $calculate = true, bool $use_locale = false): string
  {
    if (!isset($currency_code)) {
      $currency_code = $this->getDefault();
    }

    if ($calculate === true) {
      if (!isset($currency_value)) {
        $currency_value = $this->currencies[$currency_code]['value'];
      }

      if ($this->currencies[$currency_code]['surcharge'] > 0) {
        $currency_value += ($currency_value * $this->currencies[$currency_code]['surcharge']);
      }
    } else {
      $currency_value = 1;
    }

    $dec_point = '.';
    $thousands_sep = '';

    if ($use_locale === true) {
      $CLICSHOPPING_Language = Registry::get('Language');

      $dec_point = $CLICSHOPPING_Language->getNumericDecimalSeparator();
      $thousands_sep = $CLICSHOPPING_Language->getNumericThousandsSeparator();
    }

    $value = number_format(round($number * $currency_value, $this->currencies[$currency_code]['decimal_places']), $this->currencies[$currency_code]['decimal_places'], $dec_point, $thousands_sep);

    return $value;
  }

  /**
   * Retrieves and processes currency values based on the provided number for all available currencies.
   *
   * @param float $number The numeric value to be converted or processed for each currency.
   * @param bool $use_trim Optional flag to determine whether to trim the resulting values. Defaults to false.
   * @return array An associative array where the keys are currency codes and the values are the processed currency representations.
   */
  public function showAll(float $number, bool $use_trim = false): array
  {
    $result = [];

    foreach (array_keys($this->currencies) as $code) {
      $value = $this->show($number, $code);

      if ($use_trim === true) {
        $value = $this->trim($value);
      }

      $result[$code] = $value;
    }

    return $result;
  }

  /**
   * Retrieves the default value based on the given condition.
   *
   * @param bool $true_default Indicates whether to return the true default value;
   *                            if false, checks for a selected value first.
   * @return string|null Returns the selected value if available and $true_default is false,
   *                      otherwise returns the default value.
   */
  public function getDefault(bool $true_default = false): string|null
  {
    return (($true_default === false) && $this->hasSelected()) ? $this->selected : $this->default;
  }

  /**
   * Retrieves the currently selected value.
   *
   * @return string|null The selected value, or null if no value is selected.
   */
  public function getSelected(): string|null
  {
    return $this->selected;
  }

  /**
   * Checks if a selection has been made.
   *
   * @return bool Returns true if a selection exists, otherwise false.
   */
  public function hasSelected(): bool
  {
    return isset($this->selected);
  }

  /**
   * Sets the selected code if it exists.
   *
   * @param string $code The code to set as selected.
   * @return bool Returns true if the code exists and is set as selected, otherwise false.
   */
  public function setSelected(string $code): bool
  {
    if ($this->exists($code)) {
      $this->selected = $code;

      return true;
    }

    return false;
  }

  /**
   * Checks if a currency code exists in the currencies array.
   *
   * @param string $code The currency code to check for existence.
   * @return bool Returns true if the currency code exists, false otherwise.
   */
  public function exists(string $code): bool
  {
    return array_key_exists($code, $this->currencies);
  }
}

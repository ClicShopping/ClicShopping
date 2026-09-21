<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Currency\Classes\ClicShoppingAdmin;

use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Currency\Currency as CurrencyApp;
use Exception;
use PDOException;
use SimpleXMLElement;
use function is_null;

class CurrenciesAdmin extends \ClicShopping\Apps\Configuration\Currency\Classes\Shop\Currencies
{
  /**
   * Constructor method to initialize the currencies array and set the default currency.
   *
   * @param array|null $currencies Optional array of currencies to initialize.
   * @return void
   */
  public function __construct(?array $currencies = null)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    /**
     *
     */
      $this->currencies = [];

    $Qcurrencies = $CLICSHOPPING_Db->query('select currencies_id as id,
                                                  code,
                                                  title,
                                                  symbol_left,
                                                  symbol_right,
                                                  decimal_point,
                                                  thousands_point,
                                                  decimal_places,
                                                  value
                                           from :table_currencies
                                          ');

    $Qcurrencies->execute();

    $currencies = $Qcurrencies->fetchAll();

    foreach ($currencies as $c) {
      /**
       *
       */
      $this->currencies[$c['code']] = [
        'id' => (int)$c['id'],
        'title' => $c['title'],
        'symbol_left' => $c['symbol_left'],
        'symbol_right' => $c['symbol_right'],
        'decimal_point' => $c['decimal_point'],
        'thousands_point' => $c['thousands_point'],
        'decimal_places' => (int)$c['decimal_places'],
        'value' => (float)$c['value']
      ];

      if (!isset($this->default) && ((float)$c['value'] === 1.0)) {
        $this->default = $c['code'];
      }
    }
  }

  /**
   * Retrieves an array of all currencies in the format of id and text pairs.
   *
   * @return array An array where each element contains 'id' as the currency code and 'text' as the currency title.
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
   * Updates all available currencies with the latest exchange rates fetched from the European Central Bank.
   * If the default currency is not the source currency (EUR), it will adjust the rates accordingly.
   * The method retrieves the current exchange rates from the ECB XML feed, processes them,
   * converts rates if necessary, and updates the currencies in the database.
   *
   * @return void
   * @throws Exception If the ECB currency rate XML data cannot be loaded.
   */
  public function updateAllCurrencies()
  {
    if (!Registry::exists('CurrencyApp')) {
      Registry::set('CurrencyApp', new CurrencyApp());
    }

    $CLICSHOPPING_Currency = Registry::get('CurrencyApp');

    // This is a constant
    $sourceCurrency = 'EUR';
    $defaultCurrency = DEFAULT_CURRENCY;

    $XML = HTTP::getResponse([
      'url' => 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml'
    ]);

    if (empty($XML)) {
      throw new Exception('Can not load currency rates from the European Central Bank website');
    }

    $currencies = [];

    foreach ($this->getAll() as $c) {
      $currencies[(string)$c['id']] = null;
    }

    // This is a constant
    $currencies[$sourceCurrency] = 1.0;

    $XML = new SimpleXMLElement($XML);

    foreach ($XML->Cube->Cube->Cube as $rate) {
      $code = (string)$rate['currency'];
      if (array_key_exists($code, $currencies)) {
        $currencies[$code] = (float)$rate['rate'];
      }
    }

    if ($defaultCurrency !== $sourceCurrency) {
      $currencies = self::convertRates($currencies, $defaultCurrency);
    }

    foreach ($currencies as $code => $value) {
      if (!is_null($value)) {
        try {
          $CLICSHOPPING_Currency->db->save('currencies', ['value' => $value, 'last_updated' => 'now()'], ['code' => $code]);
        } catch (PDOException $e) {
          trigger_error($e->getMessage());
        }
      }
    }
  }

  /**
   * Rebases quoted rates on the default currency.
   *
   * A currency the source does not quote stays null: dividing it would write 0 and price its
   * products at zero. Public because it is the extraction seam the test feeds.
   *
   * @param array<string,float|null> $rates Quoted rates, null for a currency the source ignores
   * @param string $defaultCurrency Currency the rates must be expressed in
   * @return array<string,float|null> Rebased rates, still null where there was no quote
   * @throws Exception When the default currency itself carries no quote
   */
  public static function convertRates(array $rates, string $defaultCurrency): array
  {
    $base = $rates[$defaultCurrency] ?? null;

    if (empty($base)) {
      throw new Exception('The default currency ' . $defaultCurrency . ' carries no quote: no rate can be expressed in it');
    }

    $converted = [];

    foreach ($rates as $code => $value) {
      $converted[$code] = is_null($value) ? null : $value / $base;
    }

    return $converted;
  }
}
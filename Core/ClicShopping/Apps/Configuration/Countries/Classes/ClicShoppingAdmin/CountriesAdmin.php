<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  namespace ClicShopping\Apps\Configuration\Countries\Classes\ClicShoppingAdmin;

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\Configuration\Countries\Countries;
  class CountriesAdmin
  {
    private mixed $countries;

    public function __construct()
    {
      Registry::set('Countries', new Countries());
      $this->countries = Registry::get('Countries');
    }

    /**
     * Distinct ISO-4217 codes declared on countries, for a select field.
     * @param string|null $keep A code to list even if no country declares it (the value being edited).
     * @return array<int, array{id: string, text: string}>
     */
    public function currenciesCodeList(?string $keep = null): array {
      $QcurrencyCode = $this->countries->db->prepare('SELECT DISTINCT country_currency_code
                                                      FROM :table_countries
                                                      WHERE country_currency_code IS NOT NULL
                                                        AND country_currency_code <> \'\'
                                                      ORDER BY country_currency_code
                                                    ');

      $QcurrencyCode->execute();

      $codes = array_column($QcurrencyCode->fetchAll(), 'country_currency_code');

      if (!empty($keep) && !in_array($keep, $codes, true)) {
        array_unshift($codes, $keep);
      }

      return array_map(static fn($code) => ['id' => $code, 'text' => $code], $codes);
    }

    /**
     * Normalizes a posted country currency code: ISO-4217 (3 letters) or null when empty/invalid.
     * @param mixed $code
     * @return string|null
     */
    public static function normalizeCurrencyCode(mixed $code): ?string {
      $code = mb_strtoupper(trim((string)$code));

      return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : null;
    }
  }
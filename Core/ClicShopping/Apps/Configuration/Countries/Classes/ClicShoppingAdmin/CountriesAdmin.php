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
     * get the list of the currency code from countries table
     * @return array
     */
    public function currenciesCodeList(): array {
      $QcurrencyCode = $this->countries->db->prepare('SELECT countries_id,
                                                             country_currency_code
                                                      FROM :table_countries
                                                      GROUP BY country_currency_code
                                                      ORDER BY country_currency_code DESC
                                                    ');

      $QcurrencyCode->execute();
      $array_code = $QcurrencyCode->fetchAll();

      foreach ($array_code as $code) {
        $array_country_currency_code[] = [
          'id' => $code['country_currency_code'],
          'text' => $code['country_currency_code']
        ];
      }

      return $array_country_currency_code;
    }
  }
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Currency\Sites\ClicShoppingAdmin\Pages\Home\Actions\Currency;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Currency');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    $title = HTML::sanitize($_POST['title']);
    $code = HTML::sanitize($_POST['code']);
    $symbol_left = HTML::sanitize($_POST['symbol_left']);
    $symbol_right = HTML::sanitize($_POST['symbol_right']);
    $decimal_point = HTML::sanitize($_POST['decimal_point']);
    $thousands_point = HTML::sanitize($_POST['thousands_point']);
    $decimal_places = HTML::sanitize($_POST['decimal_places']);
    $value = HTML::sanitize($_POST['value']);
    $surcharge = HTML::sanitize($_POST['surcharge']);

    $sql_data_array = [
      'title' => $title,
      'code' => mb_strtoupper($code),
      'symbol_left' => $symbol_left,
      'symbol_right' => $symbol_right,
      'decimal_point' => $decimal_point,
      'thousands_point' => $thousands_point,
      'decimal_places' => $decimal_places,
      'value' => (float)$value,
      'surcharge' => (float)$surcharge
    ];

    $this->app->db->save('currencies', $sql_data_array);
    $currencies_id = $this->app->db->lastInsertId();

    if (isset($_POST['default'])) {
      $this->app->db->save('configuration', [
        'configuration_value' => $code
      ], [
          'configuration_key' => 'DEFAULT_CURRENCY'
        ]
      );
    }

    Cache::clear('currencies');

    $this->app->redirect('Currency&page=' . $page . '&cID=' . $currencies_id);
  }
}
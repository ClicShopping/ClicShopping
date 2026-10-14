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

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Currency');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['cID'])) {
      $currencies_id = HTML::sanitize($_GET['cID']);
    } else {
      $currencies_id = null;
    }

    if (!\is_null($currencies_id)) {
      $title = HTML::sanitize($_POST['title']);
      $code = mb_strtoupper(HTML::sanitize($_POST['code']));

      // A code is unique: Currencies indexes by code, a duplicate silently shadows the other row.
      $Qduplicate = $this->app->db->prepare('select currencies_id
                                             from :table_currencies
                                             where code = :code
                                             and currencies_id <> :currencies_id
                                             limit 1
                                            ');
      $Qduplicate->bindValue(':code', $code);
      $Qduplicate->bindInt(':currencies_id', (int)$currencies_id);
      $Qduplicate->execute();

      if ($Qduplicate->fetch() !== false) {
        Registry::get('MessageStack')->add($this->app->getDef('error_currency_code_exists', ['code' => $code]), 'error');
        $this->app->redirect('Currency&Edit&page=' . $page . '&cID=' . $currencies_id);
      }

      $Qprevious = $this->app->db->get('currencies', 'code', ['currencies_id' => (int)$currencies_id]);
      $was_default = \defined('DEFAULT_CURRENCY') && $Qprevious->value('code') === DEFAULT_CURRENCY;

      $symbol_left = HTML::sanitize($_POST['symbol_left']);
      $symbol_right = HTML::sanitize($_POST['symbol_right']);
      $decimal_point = HTML::sanitize($_POST['decimal_point']);
      $thousands_point = HTML::sanitize($_POST['thousands_point']);
      $decimal_places = HTML::sanitize($_POST['decimal_places']);
      $value = HTML::sanitize($_POST['value']);
      $surcharge = HTML::sanitize($_POST['surcharge']);

      $sql_data_array = [
        'title' => $title,
        'code' => $code,
        'symbol_left' => $symbol_left,
        'symbol_right' => $symbol_right,
        'decimal_point' => $decimal_point,
        'thousands_point' => $thousands_point,
        'decimal_places' => $decimal_places,
        'value' => (float)$value,
        'last_updated' => 'now()',
        'surcharge' => (float)$surcharge
      ];

      $this->app->db->save('currencies', $sql_data_array, ['currencies_id' => (int)$currencies_id]);

      // Renaming the default currency must carry DEFAULT_CURRENCY along, or it points to no row.
      if (isset($_POST['default']) || $was_default) {
        $this->app->db->save('configuration', [
          'configuration_value' => $code
        ], [
            'configuration_key' => 'DEFAULT_CURRENCY'
          ]
        );

        Cache::clear('configuration');
      }

      Cache::clear('currencies');
    }

    $this->app->redirect('Currency&page=' . $page . '&cID=' . $currencies_id);
  }
}
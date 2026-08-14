<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Currency\Sites\ClicShoppingAdmin\Pages\Home\Actions\Currency;

use ClicShopping\Apps\Configuration\Currency\Classes\ClicShoppingAdmin\CurrenciesAdmin;
use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class UpdateAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Currency');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (!Registry::exists('CurrenciesAdmin')) {
      Registry::set('CurrenciesAdmin', new CurrenciesAdmin());
    }

    $CurrenciesAdmin = Registry::get('CurrenciesAdmin');

    $CurrenciesAdmin->updateAllCurrencies();

    Cache::clear('currencies');

    $this->app->redirect('Currency&page=' . $page);
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Currency\Module\Hooks\ClicShoppingAdmin\Cronjob;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Currency\Classes\ClicShoppingAdmin\CurrenciesAdmin;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron;

class Process implements HooksInterface
{
  public function __construct()
  {
  }

  /**
   * Updates all currency data by utilizing the CurrenciesAdmin service.
   *
   * Ensures that the CurrenciesAdmin instance is properly initialized and
   * triggers the update process for all currencies.
   *
   * @return void
   */
  private static function updateAllCurrencies(): void
  {
    if (!Registry::exists('CurrenciesAdmin')) {
      Registry::set('CurrenciesAdmin', new CurrenciesAdmin());
    }

    $CurrenciesAdmin = Registry::get('CurrenciesAdmin');

    $CurrenciesAdmin->updateAllCurrencies();
  }

  /**
   * Handles the execution of a cron job related to currency updates.
   *
   * This method checks for a 'cronId' parameter in the GET request, validates it,
   * and performs currency updates if the 'cronId' matches the predefined cron code.
   * If no 'cronId' is provided in the request, the method executes the update for all currencies
   * using the predefined cron ID.
   *
   * @return void
   */
  private static function cronJob(): void
  {
    $cron_id_gdpr = Cron::getCronCode('currency');

    if (isset($_GET['cronId'])) {
      $cron_id = HTML::sanitize($_GET['cronId']);

      Cron::updateCron($cron_id);

      if (isset($cron_id) && $cron_id_gdpr == $cron_id) {
        static::updateAllCurrencies();
      }
    } else {
      Cron::updateCron($cron_id_gdpr);

      if (isset($cron_id_gdpr)) {
        static::updateAllCurrencies();
      }
    }
  }

  /**
   * Executes the main process by calling the cron job and clearing the currency cache.
   *
   * @return void
   */
  public function execute()
  {
    static::cronJob();

    Cache::clear('currencies');
  }
}
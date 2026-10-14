<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Currency\Module\Hooks\Shop\Cronjob;

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
   * Updates all currency rates using the CurrenciesAdmin module.
   *
   * This method initializes the CurrenciesAdmin module if it is not already
   * registered in the application registry. Once initialized or obtained from
   * the registry, it invokes the updateAllCurrencies method of the CurrenciesAdmin
   * instance to update all currencies.
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
   * Executes a scheduled cron job for updating currency data.
   *
   * This method checks for a specific cron ID passed via the GET parameter.
   * It validates and sanitizes the cron ID before updating the corresponding cron task.
   * If the cron ID matches the predefined ID for the currency update task,
   * the method triggers the currency data update.
   * If no specific cron ID is provided, it defaults to using the predefined cron ID
   * and performs the currency data update accordingly.
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
   * Executes the main logic of the method by running the cron job and clearing the cache for currencies.
   *
   * @return void
   */
  public function execute()
  {
    static::cronJob();

    Cache::clear('currencies');
  }
}
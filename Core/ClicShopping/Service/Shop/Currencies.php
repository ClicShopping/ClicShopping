<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;

use ClicShopping\Apps\Configuration\Currency\Classes\Shop\Currencies as CurrenciesClass;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * Service class responsible for managing the currency system in the shop.
 * This service initializes and configures the currency settings based on various factors
 * such as session data, URL parameters, and application defaults.
 */
class Currencies implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Initializes the application currency settings by verifying the existence of required files,
   * setting up the currency registry, and managing the session currency based on user input or default settings.
   *
   * @return bool Returns true if the initialization process completes successfully; otherwise, false.
   */
  public static function start(): bool
  {
    if (is_file(CLICSHOPPING::BASE_DIR . 'Apps/Configuration/Currency/Classes/Shop/Currencies.php')) {
      Registry::set('Currencies', new CurrenciesClass());
      $CLICSHOPPING_Currencies = Registry::get('Currencies');

      if (!isset($_SESSION['currency']) || isset($_GET['currency']) || ((USE_DEFAULT_LANGUAGE_CURRENCY == 'true') && (CLICSHOPPING::getDef('language_currency') != $_SESSION['currency']))) {
        if (isset($_GET['currency']) && $CLICSHOPPING_Currencies->isSet($_GET['currency'])) {
          $_SESSION['currency'] = HTML::sanitize($_GET['currency']);
        } else {
          $_SESSION['currency'] = ((USE_DEFAULT_LANGUAGE_CURRENCY == 'true') && $CLICSHOPPING_Currencies->isSet(CLICSHOPPING::getDef('language_currency'))) ? CLICSHOPPING::getDef('language_currency') : DEFAULT_CURRENCY;
        }
      }
      return true;
    } else {
      return false;
    }
  }

  /**
   * Stops the execution or operation of a process.
   *
   * @return bool Returns true indicating the stop operation has been executed successfully.
   */
  public static function stop(): bool
  {
    return true;
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Stripe\Module\ClicShoppingAdmin\Config\ST;
/**
 * Stripe Payment Module Configuration
 *
 * This class is a configuration module for the Stripe payment integration in the ClicShopping admin panel.
 * It extends the ClicShoppingAdmin ConfigAbstract class and provides methods to initialize, install,
 * and uninstall the Stripe payment module within the admin system.
 */
class ST extends \ClicShopping\Apps\Payment\Stripe\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'Stripe';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initialize the module by setting its title, short title, introduction,
   * and determining its installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_stripe_title');
    $this->short_title = $this->app->getDef('module_stripe_short_title');
    $this->introduction = $this->app->getDef('module_stripe_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_STRIPE_ST_STATUS') && (trim(CLICSHOPPING_APP_STRIPE_ST_STATUS) != '');
  }

  /**
   * Installs the module by adding it to the list of installed payment modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_PAYMENT_INSTALLED')) {
      $installed = explode(';', MODULE_PAYMENT_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_PAYMENT_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the payment module by removing its reference from the MODULE_PAYMENT_INSTALLED configuration.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_PAYMENT_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_PAYMENT_INSTALLED', implode(';', $installed));
    }
  }
}
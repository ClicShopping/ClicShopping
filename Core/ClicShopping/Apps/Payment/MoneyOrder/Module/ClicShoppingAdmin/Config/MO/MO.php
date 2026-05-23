<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\MoneyOrder\Module\ClicShoppingAdmin\Config\MO;
/**
 * The MO class represents the Money Order module configuration for the ClicShoppingAdmin.
 * It provides functionality for initializing, installing, and uninstalling the Money Order payment module.
 */
class MO extends \ClicShopping\Apps\Payment\MoneyOrder\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'MoneyOrder';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes module properties such as title, short title, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_moneyorder_title');
    $this->short_title = $this->app->getDef('module_moneyorder_short_title');
    $this->introduction = $this->app->getDef('module_moneyorder_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_MONEYORDER_MO_STATUS') && (trim(CLICSHOPPING_APP_MONEYORDER_MO_STATUS) != '');
  }

  /**
   * Installs the payment module by adding it to the list of installed modules.
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
   * Uninstalls the payment module by removing its entry from the list of installed modules.
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
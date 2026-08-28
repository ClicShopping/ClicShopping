<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalTax\Module\ClicShoppingAdmin\Config\TX;

class TX extends \ClicShopping\Apps\OrderTotal\TotalTax\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'TotalTax';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction,
   * and installation status based on application definitions and configuration.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_tx_title');
    $this->short_title = $this->app->getDef('module_tx_short_title');
    $this->introduction = $this->app->getDef('module_tx_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_STATUS') && (trim(CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_STATUS) != '');
  }

  /**
   * Installs the module at the rank its declared role commands.
   *
   * @return bool false when the module declares no usable role — nothing is written then.
   */
  public function install()
  {
    $installed = \defined('MODULE_ORDER_TOTAL_INSTALLED') ? explode(';', MODULE_ORDER_TOTAL_INSTALLED) : [];

    // Fail-closed: the module goes in at the rank its declared role commands, or it is NOT
    // installed. Appending blindly is what lets a line print without being counted in the total.
    $chain = \ClicShopping\OM\OrderTotalSequence::place($installed, $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code);

    if ($chain === null) {
      return false;
    }

    parent::install();

    $this->app->saveCfgParam('MODULE_ORDER_TOTAL_INSTALLED', implode(';', $chain));

    return true;
  }

  /**
   * Uninstalls the module by removing its reference from the installed modules list.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_ORDER_TOTAL_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed, true);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_ORDER_TOTAL_INSTALLED', implode(';', $installed));
    }
  }
}
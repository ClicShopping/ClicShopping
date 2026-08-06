<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\Total\Module\ClicShoppingAdmin\Config\TO;

class TO extends \ClicShopping\Apps\OrderTotal\Total\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'Total';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its properties such as title, short title, introduction,
   * and installation status by fetching definitions and configurations from the application.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_to_title');
    $this->short_title = $this->app->getDef('module_to_short_title');
    $this->introduction = $this->app->getDef('module_to_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_ORDER_TOTAL_TOTAL_TO_STATUS') && (trim(CLICSHOPPING_APP_ORDER_TOTAL_TOTAL_TO_STATUS) != '');
  }

  /**
   * Installs the module and appends its identifier to the list of installed modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_ORDER_TOTAL_INSTALLED')) {
      $installed = explode(';', MODULE_ORDER_TOTAL_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_ORDER_TOTAL_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing it from the list of installed modules.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_ORDER_TOTAL_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_ORDER_TOTAL_INSTALLED', implode(';', $installed));
    }
  }
}
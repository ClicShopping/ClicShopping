<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsLength\Module\ClicShoppingAdmin\Config\PL;

class PL extends \ClicShopping\Apps\Configuration\ProductsLength\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'products_length';

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
    $this->title = $this->app->getDef('module_we_title');
    $this->short_title = $this->app->getDef('module_we_short_title');
    $this->introduction = $this->app->getDef('module_we_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS') && (trim(CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS) != '');
  }

  /**
   * Installs the module by registering its identifier into the configuration parameter.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_PROUCTS_LENGTH_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_PROUCTS_LENGTH_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_PROUCTS_LENGTH_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing its entry from the list of installed modules.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_PROUCTS_LENGTH_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_PROUCTS_LENGTH_INSTALLED', implode(';', $installed));
    }
  }
}
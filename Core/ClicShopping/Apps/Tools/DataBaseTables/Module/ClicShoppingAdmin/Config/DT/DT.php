<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\DataBaseTables\Module\ClicShoppingAdmin\Config\DT;
/**
 * Represents the DT module configuration for the ClicShoppingAdmin application.
 * This class extends the base ConfigAbstract class and provides implementation
 * specific to the DT module, including install and uninstall functionalities.
 */
class DT extends \ClicShopping\Apps\Tools\DataBaseTables\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'data_base_tables';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_dt_title');
    $this->short_title = $this->app->getDef('module_dt_short_title');
    $this->introduction = $this->app->getDef('module_dt_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_DATA_BASE_TABLES_DT_STATUS') && (trim(CLICSHOPPING_APP_DATA_BASE_TABLES_DT_STATUS) != '');
  }

  /**
   * Installs the module by adding its identifiers to the installed modules configuration.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_DATA_BASE_TABLES_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_DATA_BASE_TABLES_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_DATA_BASE_TABLES_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing its identifiers from the installed modules configuration.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_DATA_BASE_TABLES_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_DATA_BASE_TABLES_INSTALLED', implode(';', $installed));
    }
  }
}
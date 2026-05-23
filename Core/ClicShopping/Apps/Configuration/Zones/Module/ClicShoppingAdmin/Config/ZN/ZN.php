<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Zones\Module\ClicShoppingAdmin\Config\ZN;
/**
 * This class represents the configuration module for managing zones within the ClicShopping application.
 * It extends the ConfigAbstract class and provides functionalities for initializing, installing,
 * and uninstalling the module.
 */
class ZN extends \ClicShopping\Apps\Configuration\Zones\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'zones';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its properties, including title, short title,
   * introduction, and installation status.
   *
   * Determines the installation status based on the configuration value.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_zn_title');
    $this->short_title = $this->app->getDef('module_zn_short_title');
    $this->introduction = $this->app->getDef('module_zn_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_ZONES_ZN_STATUS') && (trim(CLICSHOPPING_APP_ZONES_ZN_STATUS) != '');
  }

  /**
   * Handles the installation process for the module.
   *
   * Adds the module to the list of installed modules in the configuration.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_ZONES_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_ZONES_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_ZONES_INSTALLED', implode(';', $installed));
  }

  /**
   * Handles the uninstallation process for the module.
   *
   * Updates the configuration to remove the module from the installed modules list.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_ZONES_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_ZONES_INSTALLED', implode(';', $installed));
    }
  }
}
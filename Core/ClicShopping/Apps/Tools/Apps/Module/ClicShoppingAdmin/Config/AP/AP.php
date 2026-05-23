<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Apps\Module\ClicShoppingAdmin\Config\AP;

class AP extends \ClicShopping\Apps\Tools\Apps\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'apps';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction,
   * and installation status based on the application's definitions and configuration.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_ap_title');
    $this->short_title = $this->app->getDef('module_ap_short_title');
    $this->introduction = $this->app->getDef('module_ap_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_APPS_AP_STATUS') && (trim(CLICSHOPPING_APP_APPS_AP_STATUS) != '');
  }

  /**
   * Installs the current module by adding its reference to the list of installed modules
   * and updating the configuration parameter accordingly.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_APPS_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_APPS_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_APPS_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the current module by removing its reference from the list of installed modules
   * and updating the configuration parameter accordingly.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_APPS_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_APPS_INSTALLED', implode(';', $installed));
    }
  }
}
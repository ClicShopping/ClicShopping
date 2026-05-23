<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ServiceAPP\Module\ClicShoppingAdmin\Config\SV;

class SV extends \ClicShopping\Apps\Tools\ServiceAPP\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'service';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction,
   * and checking its installation status based on configuration constants.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_sv_title');
    $this->short_title = $this->app->getDef('module_sv_short_title');
    $this->introduction = $this->app->getDef('module_sv_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_SERVICE_APP_SV_STATUS') && (trim(CLICSHOPPING_APP_SERVICE_APP_SV_STATUS) != '');
  }

  /**
   * Installs the module and adds its entry to the installed modules configuration parameter.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_SERVICE_APP_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_SERVICE_APP_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_SERVICE_APP_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module and removes its entry from the installed modules configuration parameter.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_SERVICE_APP_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_SERVICE_APP_INSTALLED', implode(';', $installed));
    }
  }
}
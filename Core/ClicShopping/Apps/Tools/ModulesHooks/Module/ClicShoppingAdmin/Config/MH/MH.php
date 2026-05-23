<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ModulesHooks\Module\ClicShoppingAdmin\Config\MH;

class MH extends \ClicShopping\Apps\Tools\ModulesHooks\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'modules_hooks';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_mh_title');
    $this->short_title = $this->app->getDef('module_mh_short_title');
    $this->introduction = $this->app->getDef('module_mh_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_MODULES_HOOKS_MH_STATUS') && (trim(CLICSHOPPING_APP_MODULES_HOOKS_MH_STATUS) != '');
  }

  /**
   * Installs the module by adding its configuration to the installed modules list.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_MODULES_HOOKS_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_MODULES_HOOKS_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_MODULES_HOOKS_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing its configuration from the installed modules list.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_MODULES_HOOKS_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_MODULES_HOOKS_INSTALLED', implode(';', $installed));
    }
  }
}
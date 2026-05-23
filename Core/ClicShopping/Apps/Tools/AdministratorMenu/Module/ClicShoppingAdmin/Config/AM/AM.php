<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\AdministratorMenu\Module\ClicShoppingAdmin\Config\AM;

class AM extends \ClicShopping\Apps\Tools\AdministratorMenu\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'administrator_menu';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  protected function init()
  {
    $this->title = $this->app->getDef('module_am_title');
    $this->short_title = $this->app->getDef('module_am_short_title');
    $this->introduction = $this->app->getDef('module_am_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_ADMINISTRATOR_MENU_AM_STATUS') && (trim(CLICSHOPPING_APP_ADMINISTRATOR_MENU_AM_STATUS) != '');
  }

  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_ADMINISTRATOR_MENU_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_ADMINISTRATOR_MENU_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_ADMINISTRATOR_MENU_INSTALLED', implode(';', $installed));
  }

  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_ADMINISTRATOR_MENU_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_ADMINISTRATOR_MENU_INSTALLED', implode(';', $installed));
    }
  }
}
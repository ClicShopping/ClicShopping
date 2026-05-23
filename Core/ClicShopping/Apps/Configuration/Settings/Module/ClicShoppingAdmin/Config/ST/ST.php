<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Settings\Module\ClicShoppingAdmin\Config\ST;

class ST extends \ClicShopping\Apps\Configuration\Settings\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'settings';

  public bool $is_uninstallable = false;
  public int|null $sort_order = 400;

  protected function init()
  {
    $this->title = $this->app->getDef('module_st_title');
    $this->short_title = $this->app->getDef('module_st_short_title');
    $this->introduction = $this->app->getDef('module_st_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_SETTINGS_ST_STATUS') && (trim(CLICSHOPPING_APP_SETTINGS_ST_STATUS) != '');
  }

  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_SETTINGS_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_SETTINGS_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_SETTINGS_INSTALLED', implode(';', $installed));
  }

  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_SETTINGS_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_SETTINGS_INSTALLED', implode(';', $installed));
    }
  }
}
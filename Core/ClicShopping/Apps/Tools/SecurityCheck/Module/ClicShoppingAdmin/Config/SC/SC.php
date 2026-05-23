<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\SecurityCheck\Module\ClicShoppingAdmin\Config\SC;

class SC extends \ClicShopping\Apps\Tools\SecurityCheck\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'SecurityCheck';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  protected function init()
  {
    $this->title = $this->app->getDef('module_sc_title');
    $this->short_title = $this->app->getDef('module_sc_short_title');
    $this->introduction = $this->app->getDef('module_sc_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_SECURITY_CHECK_SC_STATUS') && (trim(CLICSHOPPING_APP_SECURITY_CHECK_SC_STATUS) != '');
  }

  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_SECURITY_CHECK_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_SECURITY_CHECK_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_SECURITY_CHECK_INSTALLED', implode(';', $installed));
  }

  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_SECURITY_CHECK_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_SECURITY_CHECK_INSTALLED', implode(';', $installed));
    }
  }
}
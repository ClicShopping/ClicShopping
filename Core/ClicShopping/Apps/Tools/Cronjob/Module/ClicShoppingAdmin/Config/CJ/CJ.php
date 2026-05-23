<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Cronjob\Module\ClicShoppingAdmin\Config\CJ;

class CJ extends \ClicShopping\Apps\Tools\Cronjob\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'cronjob';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status
   * based on application definitions and configuration constants.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_cj_title');
    $this->short_title = $this->app->getDef('module_cj_short_title');
    $this->introduction = $this->app->getDef('module_cj_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_CRONJOB_CJ_STATUS') && (trim(CLICSHOPPING_APP_CRONJOB_CJ_STATUS) != '');
  }

  /**
   * Installs the module by executing the parent install method,
   * adding the module to the list of installed modules, and
   * updating the configuration accordingly.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_CRONJOB_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_CRONJOB_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_CRONJOB_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by executing the parent uninstall method,
   * removing the module from the list of installed modules, and
   * updating the configuration accordingly.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_CRONJOB_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_CRONJOB_INSTALLED', implode(';', $installed));
    }
  }
}
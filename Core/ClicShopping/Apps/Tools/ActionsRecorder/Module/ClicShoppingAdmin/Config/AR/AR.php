<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ActionsRecorder\Module\ClicShoppingAdmin\Config\AR;

class AR extends \ClicShopping\Apps\Tools\ActionsRecorder\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'actions_recorder';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_ar_title');
    $this->short_title = $this->app->getDef('module_ar_short_title');
    $this->introduction = $this->app->getDef('module_ar_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_ACTIONS_RECORDER_AR_STATUS') && (trim(CLICSHOPPING_APP_ACTIONS_RECORDER_AR_STATUS) != '');
  }

  /**
   * Installs the module by registering its identifier into the system's configuration.
   *
   * Checks if the constant `MODULE_MODULES_ACTIONS_RECORDER_INSTALLED` is defined
   * and appends the module's identifier to the list of installed modules.
   *
   * Also ensures the updated list is saved in the configuration parameter.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_ACTIONS_RECORDER_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_ACTIONS_RECORDER_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_ACTIONS_RECORDER_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the current module by removing its entry from the installed modules list and saving the updated configuration parameter.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_ACTIONS_RECORDER_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_ACTIONS_RECORDER_INSTALLED', implode(';', $installed));
    }
  }
}
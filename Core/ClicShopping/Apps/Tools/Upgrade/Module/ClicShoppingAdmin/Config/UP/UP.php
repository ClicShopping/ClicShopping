<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Module\ClicShoppingAdmin\Config\UP;

class UP extends \ClicShopping\Apps\Tools\Upgrade\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'upgrade';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, and introduction
   * using application definitions and determining if the module is installed
   * based on the configuration status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_up_title');
    $this->short_title = $this->app->getDef('module_up_short_title');
    $this->introduction = $this->app->getDef('module_up_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_UPGRADE_UP_STATUS') && (trim(CLICSHOPPING_APP_UPGRADE_UP_STATUS) != '');
  }

  /**
   * Installs the current module by adding it to the list of installed modules
   * in the configuration and performing any necessary setup defined in the parent class.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_UPGRADE_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_UPGRADE_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_UPGRADE_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the current module by removing it from the list of installed modules
   * in the configuration and performing any necessary cleanup defined in the parent class.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_UPGRADE_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_UPGRADE_INSTALLED', implode(';', $installed));
    }
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Administrators\Module\ClicShoppingAdmin\Config\AD;

/**
 * Class AD
 *
 * This class represents a configuration module for administrators within the ClicShopping Admin Configuration system.
 * It extends the ConfigAbstract class and provides methods for initializing, installing,
 * and uninstalling the module.
 */
class AD extends \ClicShopping\Apps\Configuration\Administrators\Module\ClicShoppingAdmin\Config\ConfigAbstract
{
  protected $pm_code = 'administrators';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_ad_title');
    $this->short_title = $this->app->getDef('module_ad_short_title');
    $this->introduction = $this->app->getDef('module_ad_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_ADMINISTRATORS_AD_STATUS') && (trim(CLICSHOPPING_APP_ADMINISTRATORS_AD_STATUS) != '');
  }

  /**
   * Installs the module by adding its reference to the list of installed modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_ADMINISTRATORS_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_ADMINISTRATORS_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_ADMINISTRATORS_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing its reference from the list of installed modules.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_ADMINISTRATORS_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_ADMINISTRATORS_INSTALLED', implode(';', $installed));
    }
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\AM;
/**
 * This class represents the configuration module for the AntiSpam functionality within the ClicShoppingAdmin context.
 * It extends the ConfigAbstract class to provide specific behaviors for the AntiSpam module.
 */
class AM extends \ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\ConfigAbstract
{
  protected $pm_code = 'antispam';
  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_am_title');
    $this->short_title = $this->app->getDef('module_am_short_title');
    $this->introduction = $this->app->getDef('module_am_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_ANTISPAM_AM_STATUS') && (trim(CLICSHOPPING_APP_ANTISPAM_AM_STATUS) != '');
  }

  /**
   * Installs the module by adding it to the list of installed modules.
   *
   * @return bool Returns true if the module installation succeeds.
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_ANTISPAM_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_ANTISPAM_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_ANTISPAM_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the current module and removes its reference from the list of installed modules.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_ANTISPAM_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed, true);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_ANTISPAM_INSTALLED', implode(';', $installed));
    }
  }
}
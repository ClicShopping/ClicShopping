<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\IN;

/**
 * This class represents the configuration module for the Antispam application within the ClicShoppingAdmin interface.
 *
 * Extends the ConfigAbstract class to inherit core configuration functionalities and provides specific
 * implementations for installation, uninstallation, and initialization of the Antispam module.
 */

class IN extends \ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\ConfigAbstract
{
  protected $pm_code = 'antispam';
  public bool $is_uninstallable = true;
  public int|null $sort_order = 600;

  /**
   * Initializes the module by setting its title, short title, introduction,
   * and installation status based on the application's configuration definitions.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_in_title');
    $this->short_title = $this->app->getDef('module_in_short_title');
    $this->introduction = $this->app->getDef('module_in_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_ANTISPAM_IN_STATUS') && (trim(CLICSHOPPING_APP_ANTISPAM_IN_STATUS) != '');
  }

  /**
   * Installs the module by appending its identifier to the list of installed modules
   * in the configuration.
   *
   * @return void
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
   * Uninstalls the module by removing its entry from the installed modules list
   * and updating the configuration parameters accordingly.
   *
   * @return bool Returns true on successful uninstallation, false otherwise.
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
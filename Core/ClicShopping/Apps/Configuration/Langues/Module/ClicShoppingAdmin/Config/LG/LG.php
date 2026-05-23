<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Langues\Module\ClicShoppingAdmin\Config\LG;
/**
 * ClicShopping LG module class.
 *
 * This class represents the LG module in the ClicShopping Admin Configuration.
 * It extends the `ConfigAbstract` class to provide functionality for installing
 * and uninstalling the module, as well as initialization of its settings and configurations.
 */
class LG extends \ClicShopping\Apps\Configuration\Langues\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'langues';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_lg_title');
    $this->short_title = $this->app->getDef('module_lg_short_title');
    $this->introduction = $this->app->getDef('module_lg_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_LANGUES_LG_STATUS') && (trim(CLICSHOPPING_APP_LANGUES_LG_STATUS) != '');
  }

  /**
   * Installs the module by adding its identifier to the configuration parameter
   * MODULE_MODULES_LANGUES_INSTALLED. The module's identifier is generated using
   * the vendor, code, and specific module code.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_LANGUES_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_LANGUES_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_LANGUES_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing its reference from the installed modules configuration.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_LANGUES_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_LANGUES_INSTALLED', implode(';', $installed));
    }
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Specials\Module\ClicShoppingAdmin\Config\SP;
/**
 * The SP class is a configuration module for managing the Specials application in the ClicShopping admin panel.
 * It extends the ConfigAbstract class and provides initialization, installation, and uninstallation functionalities
 * for the module within the admin environment.
 */
class SP extends \ClicShopping\Apps\Marketing\Specials\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'specials';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its core properties.
   *
   * Assigns values to the module's title, short title, introduction, and installation status
   * based on the application's predefined definitions and configuration constants.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_sp_title');
    $this->short_title = $this->app->getDef('module_sp_short_title');
    $this->introduction = $this->app->getDef('module_sp_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_SPECIALS_SP_STATUS') && (trim(CLICSHOPPING_APP_SPECIALS_SP_STATUS) != '');
  }

  /**
   * Installs the module by adding it to the installed modules configuration.
   *
   * Updates the MODULE_MODULES_PRODUCTS_SPECIALS_INSTALLED configuration parameter
   * to include the current module, marking it as registered and installed.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_PRODUCTS_SPECIALS_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_PRODUCTS_SPECIALS_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_SPECIALS_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing it from the installed modules configuration.
   *
   * Updates the MODULE_MODULES_PRODUCTS_SPECIALS_INSTALLED configuration parameter
   * to exclude the current module, ensuring it is no longer registered as installed.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_PRODUCTS_SPECIALS_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_SPECIALS_INSTALLED', implode(';', $installed));
    }
  }
}
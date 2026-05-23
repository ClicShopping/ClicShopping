<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Module\ClicShoppingAdmin\Config\PD;

/**
 * The PD class represents a configuration module for the Products application in the ClicShoppingAdmin platform.
 * It is used to manage the installation, uninstallation, and initialization of the module.
 * This class extends the ConfigAbstract base class, inheriting its configuration-related functionalities.
 */
class PD extends \ClicShopping\Apps\Catalog\Products\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'products';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_pd_title');
    $this->short_title = $this->app->getDef('module_pd_short_title');
    $this->introduction = $this->app->getDef('module_pd_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS') && (trim(CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS) != '');
  }

  /**
   * Installs the module by adding it to the installed modules list.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_CATALOG_PRODUCTS_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_CATALOG_PRODUCTS_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_CATALOG_PRODUCTS_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the current module by removing its entry from the list of installed modules.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_CATALOG_PRODUCTS_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_CATALOG_PRODUCTS_INSTALLED', implode(';', $installed));
    }
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Module\ClicShoppingAdmin\Config\CS;

/**
 * This class represents the configuration module for the Suppliers App within the ClicShopping Admin panel.
 * It provides functionalities to initialize the module, and manage its installation and uninstallation processes.
 *
 * The `CS` module is a part of the Catalog Suppliers App and extends the base functionality from the
 * ConfigAbstract class.
 */
class CS extends \ClicShopping\Apps\Catalog\Suppliers\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'suppliers';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  protected function init()
  {
    $this->title = $this->app->getDef('module_cs_title');
    $this->short_title = $this->app->getDef('module_cs_short_title');
    $this->introduction = $this->app->getDef('module_cs_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_SUPPLIERS_CS_STATUS') && (trim(CLICSHOPPING_APP_SUPPLIERS_CS_STATUS) != '');
  }

  /**
   * Installs the current module by adding its identifier to the list of installed modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_SUPPLIERS_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_SUPPLIERS_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_SUPPLIERS_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing its reference from the list of installed supplier modules.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_SUPPLIERS_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_SUPPLIERS_INSTALLED', implode(';', $installed));
    }
  }
}
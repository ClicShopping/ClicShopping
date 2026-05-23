<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\TaxClass\Module\ClicShoppingAdmin\Config\TC;
/**
 * This class represents the configuration module TC of the TaxClass application in the ClicShoppingAdmin.
 * It extends the ConfigAbstract base class to provide base functionality for configuration modules.
 */
class TC extends \ClicShopping\Apps\Configuration\TaxClass\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'tax_class';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction text,
   * and determining if the module is installed based on the configuration status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_tc_title');
    $this->short_title = $this->app->getDef('module_tc_short_title');
    $this->introduction = $this->app->getDef('module_tc_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_TAX_CLASS_TC_STATUS') && (trim(CLICSHOPPING_APP_TAX_CLASS_TC_STATUS) != '');
  }

  /**
   * Installs the module by adding its identifier to the list of installed modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_TAX_CLASS_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_TAX_CLASS_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_TAX_CLASS_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by performing necessary cleanup actions, including
   * removing the module from the list of installed modules and saving the updated
   * configuration.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_TAX_CLASS_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_TAX_CLASS_INSTALLED', implode(';', $installed));
    }
  }
}
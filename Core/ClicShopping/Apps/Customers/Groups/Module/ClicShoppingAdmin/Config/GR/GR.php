<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\ClicShoppingAdmin\Config\GR;

class GR extends \ClicShopping\Apps\Customers\Groups\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'groups';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_gr_title');
    $this->short_title = $this->app->getDef('module_gr_short_title');
    $this->introduction = $this->app->getDef('module_gr_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_CUSTOMERS_GROUPS_GR_STATUS') && (trim(CLICSHOPPING_APP_CUSTOMERS_GROUPS_GR_STATUS) != '');
  }

  /**
   * Installs the module by adding its reference to the list of installed modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_CUSTOMERS_GROUPS_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_CUSTOMERS_GROUPS_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_CUSTOMERS_GROUPS_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing its reference from the list of installed modules.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_CUSTOMERS_GROUPS_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_CUSTOMERS_GROUPS_INSTALLED', implode(';', $installed));
    }
  }
}
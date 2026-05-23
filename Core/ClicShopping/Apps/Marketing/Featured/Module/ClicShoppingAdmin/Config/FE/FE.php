<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Featured\Module\ClicShoppingAdmin\Config\FE;

class FE extends \ClicShopping\Apps\Marketing\Featured\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'featured';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction,
   * and installation status based on defined application parameters.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_fe_title');
    $this->short_title = $this->app->getDef('module_fe_short_title');
    $this->introduction = $this->app->getDef('module_fe_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_FEATURED_FE_STATUS') && (trim(CLICSHOPPING_APP_FEATURED_FE_STATUS) != '');
  }

  /**
   * Installs the module by performing setup operations such as adding its entry
   * to the installed modules configuration parameter.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_PRODUCTS_FEATURED_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_PRODUCTS_FEATURED_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_FEATURED_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by performing cleanup operations such as removing its entry
   * from the installed modules configuration parameter.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_PRODUCTS_FEATURED_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_FEATURED_INSTALLED', implode(';', $installed));
    }
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Favorites\Module\ClicShoppingAdmin\Config\FA;

class FA extends \ClicShopping\Apps\Marketing\Favorites\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'favorites';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_fa_title');
    $this->short_title = $this->app->getDef('module_fa_short_title');
    $this->introduction = $this->app->getDef('module_fa_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_FAVORITES_FA_STATUS') && (trim(CLICSHOPPING_APP_FAVORITES_FA_STATUS) != '');
  }

  /**
   * Installs the module by adding its entry to the installed modules configuration.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_PRODUCTS_FAVORITES_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_PRODUCTS_FAVORITES_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_FAVORITES_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing its entry from the installed modules configuration.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_PRODUCTS_FAVORITES_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_FAVORITES_INSTALLED', implode(';', $installed));
    }
  }
}
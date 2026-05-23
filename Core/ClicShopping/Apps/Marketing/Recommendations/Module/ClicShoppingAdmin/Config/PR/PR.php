<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Module\ClicShoppingAdmin\Config\PR;

class PR extends \ClicShopping\Apps\Marketing\Recommendations\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'product_recommandations';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_pr_title');
    $this->short_title = $this->app->getDef('module_pr_short_title');
    $this->introduction = $this->app->getDef('module_pr_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_STATUS') && (trim(CLICSHOPPING_APP_RECOMMENDATIONS_PR_STATUS) != '');
  }

  /**
   * Installs the module and adds its configuration data to the system.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_PRODUCTS_PRODUCT_RECOMMENDATIONS_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_PRODUCTS_PRODUCT_RECOMMENDATIONS_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_PRODUCT_RECOMMENDATIONS_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module and removes its configuration data from the installed modules list.
   *
   * @return bool Returns true if the uninstallation process completes successfully, false otherwise.
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_PRODUCTS_PRODUCT_RECOMMENDATIONS_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_PRODUCT_RECOMMENDATIONS_INSTALLED', implode(';', $installed));
    }
  }
}
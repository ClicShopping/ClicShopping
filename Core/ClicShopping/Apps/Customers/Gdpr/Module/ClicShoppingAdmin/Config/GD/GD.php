<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Module\ClicShoppingAdmin\Config\GD;

class GD extends \ClicShopping\Apps\Customers\Gdpr\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'gdpr';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, and introduction text,
   * and determining if the module is installed based on a defined configuration status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_me_title');
    $this->short_title = $this->app->getDef('module_me_short_title');
    $this->introduction = $this->app->getDef('module_me_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_STATUS') && (trim(CLICSHOPPING_APP_CUSTOMERS_GDPR_GD_STATUS) != '');
  }

  /**
   * Installs the current module by adding its entry to the installed modules list
   * and saving the updated configuration parameter.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_CUSTOMERS_GDPR_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_CUSTOMERS_GDPR_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_CUSTOMERS_GDPR_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the current module by removing its entry from the installed modules list
   * and saving the updated configuration parameter.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_CUSTOMERS_GDPR_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_CUSTOMERS_GDPR_INSTALLED', implode(';', $installed));
    }
  }
}
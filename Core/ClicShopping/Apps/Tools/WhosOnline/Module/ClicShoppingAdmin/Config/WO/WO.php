<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\WhosOnline\Module\ClicShoppingAdmin\Config\WO;

class WO extends \ClicShopping\Apps\Tools\WhosOnline\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'whos_online';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;
  public $title;
  public string $short_title;
  public string $introduction;
  public $is_installed;

  /**
   * Initializes the module by setting its title, short title, introduction,
   * and determining its installation status based on the configuration.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_wo_title');
    $this->short_title = $this->app->getDef('module_wo_short_title');
    $this->introduction = $this->app->getDef('module_wo_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_WHOS_ONLINE_WO_STATUS') && (trim(CLICSHOPPING_APP_WHOS_ONLINE_WO_STATUS) != '');
  }

  /**
   * Installs the module by adding its entry to the list of installed modules
   * and updating the configuration accordingly.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_WHOS_ONLINE_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_WHOS_ONLINE_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_WHOS_ONLINE_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing its entry from the list of installed modules
   * and updating the configuration accordingly.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_WHOS_ONLINE_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_WHOS_ONLINE_INSTALLED', implode(';', $installed));
    }
  }
}
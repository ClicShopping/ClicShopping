<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\AI;

class AI extends \ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'api';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction,
   * and installation status based on the application's defined parameters.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_ai_title');
    $this->short_title = $this->app->getDef('module_ai_short_title');
    $this->introduction = $this->app->getDef('module_ai_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_API_AI_STATUS') && (trim(CLICSHOPPING_APP_API_AI_STATUS) != '');
  }

  /**
   * Installs the current module by adding it to the list of installed modules
   * and updating the configuration parameters accordingly.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_API_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_API_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_API_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the current module by removing it from the list of installed modules
   * and updating the configuration parameters accordingly.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_API_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_API_INSTALLED', implode(';', $installed));
    }
  }
}
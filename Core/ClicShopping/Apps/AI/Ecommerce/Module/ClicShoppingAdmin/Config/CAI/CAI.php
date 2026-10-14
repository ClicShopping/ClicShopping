<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\CAI;

/**
 *
 * This class defines the Page Manager module configuration for the ClicShoppingAdmin.
 * It extends the abstract configuration class ConfigAbstract and provides
 * initialization, installation, and uninstallation logic for the module.
 *
 * Properties:
 * - $pm_code: The code identifier for the module.
 * - $is_uninstallable: A flag indicating if the module can be uninstalled.
 * - $sort_order: The sort order of the module in the listing.
 *
 * Methods:
 * - init(): Initializes the module by defining its title, short title, introduction, and installation status.
 * - install(): Installs the module by adding it to the configuration parameter
 *   'MODULE_MODULES_ECOMMERCE_INSTALLED'.
 * - uninstall(): Uninstalls the module by removing it from the configuration parameter
 *   'MODULE_MODULES_ECOMMERCE_INSTALLED'.
 */
class CAI extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'cockpit_ai';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 420;

  protected function init()
  {
    $this->title = $this->app->getDef('module_cai_title');
    $this->short_title = $this->app->getDef('module_cai_short_title');
    $this->introduction = $this->app->getDef('module_cai_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_STATUS') && (trim(CLICSHOPPING_APP_ECOMMERCE_CAI_STATUS) != '');
  }

    /**
   * Installs the current module by adding it to the list of installed modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_ECOMMERCE_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_ECOMMERCE_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_ECOMMERCE_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing it from the list of installed modules
   * and updating the configuration parameter.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_ECOMMERCE_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_ECOMMERCE_INSTALLED', implode(';', $installed));
    }
  }
}
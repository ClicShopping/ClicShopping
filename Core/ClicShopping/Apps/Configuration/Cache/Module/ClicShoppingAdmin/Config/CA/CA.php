<?php
/**
 * Class CA
 *
 * Represents a configuration module for the cache system in the ClicShoppingAdmin application.
 * This class manages the installation, uninstallation, and initialization of the cache module.
 * It extends the ConfigAbstract class, leveraging its functionality for configuration management.
 */

namespace ClicShopping\Apps\Configuration\Cache\Module\ClicShoppingAdmin\Config\CA;
/**
 * The CA class extends the ConfigAbstract class and provides functionality
 * for managing the 'cache' module configuration within the Admin Panel.
 *
 * This class handles the installation and uninstallation of the module,
 * while also initializing its title, short title, introduction, and installation status.
 *
 * Properties:
 * - $pm_code: Identifier for the module's code.
 * - $is_uninstallable: Determines whether the module can be uninstalled.
 * - $sort_order: Sets the display order of the module.
 *
 * Methods:
 * - init(): Initializes the module by setting its titles, introduction,
 *   and installation status.
 * - install(): Executes the installation process, registering the
 *   module in the system's configuration.
 * - uninstall(): Executes the uninstallation process, removing the
 *   module from the system's configuration.
 */
class CA extends \ClicShopping\Apps\Configuration\Cache\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'cache';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_ca_title');
    $this->short_title = $this->app->getDef('module_ca_short_title');
    $this->introduction = $this->app->getDef('module_ca_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_CACHE_CA_STATUS') && (trim(CLICSHOPPING_APP_CACHE_CA_STATUS) != '');
  }

  /**
   * Installs the module by adding its reference to the cache of installed modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_CACHE_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_CACHE_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_CACHE_INSTALLED', implode(';', $installed));
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

    $installed = explode(';', MODULE_MODULES_CACHE_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_CACHE_INSTALLED', implode(';', $installed));
    }
  }
}
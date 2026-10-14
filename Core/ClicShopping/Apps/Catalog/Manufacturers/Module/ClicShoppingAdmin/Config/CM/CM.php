<?php
/**
 * Class CM
 *
 * This class represents the configuration module for managing manufacturers in the ClicShoppingAdmin application.
 * It extends the ConfigAbstract class, providing initialization, installation, and uninstallation logic
 * for the module configuration.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Module\ClicShoppingAdmin\Config\CM;

/**
 * Class CM
 *
 * Represents the configuration module for manufacturers within the ClicShoppingAdmin application.
 * This class provides the logic for initializing, installing, and uninstalling the manufacturers module,
 * extending the ConfigAbstract functionality.
 */
class CM extends \ClicShopping\Apps\Catalog\Manufacturers\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'manufacturers';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting the title, short title, and introduction
   * values, and determining whether the module is installed.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_cm_title');
    $this->short_title = $this->app->getDef('module_cm_short_title');
    $this->introduction = $this->app->getDef('module_cm_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS') && (trim(CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS) != '');
  }

  /**
   * Installs the module by updating the configuration parameter to include the module's identifier.
   * This process ensures the module is added to the list of installed manufacturers modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_MANUFACTURERS_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_MANUFACTURERS_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_MANUFACTURERS_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing it from the list of installed modules.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_MANUFACTURERS_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed, true);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_MANUFACTURERS_INSTALLED', implode(';', $installed));
    }
  }
}
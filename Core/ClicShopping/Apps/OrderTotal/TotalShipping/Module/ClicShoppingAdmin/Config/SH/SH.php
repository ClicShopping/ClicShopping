<?php
/**
 * Class SH
 *
 * This class handles the configuration of the TotalShipping module
 * for the ClicShoppingAdmin application within the OrderTotal namespace.
 * It extends the base ConfigAbstract class and implements methods
 * related to the installation, uninstallation, and initialization of the module.
 *
 * Attributes:
 * - `$pm_code`: Identifies the unique code for the TotalShipping module.
 * - `$is_uninstallable`: Indicates whether the module can be uninstalled.
 * - `$sort_order`: Defines the sort order of the module.
 *
 * Methods:
 * - `init()`: Initializes the module by setting its title, short title, introduction,
 *   and determining its installation status based on a predefined constant.
 * - `install()`: Installs the module by appending its identifier to the list
 *   of installed modules.
 * - `uninstall()`: Uninstalls the module by removing its identifier from the list
 *   of installed modules.
 */

namespace ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\SH;
/**
 * Class SH
 *
 * This class handles the configuration of the TotalShipping module
 * for the ClicShoppingAdmin application within the OrderTotal namespace.
 * It extends the base ConfigAbstract class and implements methods
 * related to the installation, uninstallation, and initialization of the module.
 *
 * Attributes:
 * - `$pm_code`: Identifies the unique code for the TotalShipping module.
 * - `$is_uninstallable`: Indicates whether the module can be uninstalled.
 * - `$sort_order`: Defines the sort order of the module.
 *
 * Methods:
 * - `init()`: Initializes the module by setting its title, short title, introduction,
 *   and determining its installation status based on a predefined constant.
 * - `install()`: Installs the module by appending its identifier to the list
 *   of installed modules.
 * - `uninstall()`: Uninstalls the module by removing its identifier from the list
 *   of installed modules.
 */
class SH extends \ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'TotalShipping';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction,
   * and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_sh_title');
    $this->short_title = $this->app->getDef('module_sh_short_title');
    $this->introduction = $this->app->getDef('module_sh_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_STATUS') && (trim(CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_STATUS) != '');
  }

  /**
   * Installs the module at the rank its declared role commands.
   *
   * @return bool false when the module declares no usable role — nothing is written then.
   */
  public function install()
  {
    $installed = \defined('MODULE_ORDER_TOTAL_INSTALLED') ? explode(';', MODULE_ORDER_TOTAL_INSTALLED) : [];

    // Fail-closed: the module goes in at the rank its declared role commands, or it is NOT
    // installed. Appending blindly is what lets a line print without being counted in the total.
    $chain = \ClicShopping\OM\OrderTotalSequence::place($installed, $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code);

    if ($chain === null) {
      return false;
    }

    parent::install();

    $this->app->saveCfgParam('MODULE_ORDER_TOTAL_INSTALLED', implode(';', $chain));

    return true;
  }

  /**
   * Uninstalls the module by removing its entry from the list of installed modules.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_ORDER_TOTAL_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_ORDER_TOTAL_INSTALLED', implode(';', $installed));
    }
  }
}
<?php
/**
 * Class RO
 *
 * This class is responsible for handling the configuration of the Return Orders module
 * within the ClicShopping Admin interface. It extends the ConfigAbstract class and
 * provides methods for initializing, installing, and uninstalling the module.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Module\ClicShoppingAdmin\Config\RO;

class RO extends \ClicShopping\Apps\Orders\ReturnOrders\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'return_orders';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  protected function init()
  {
    $this->title = $this->app->getDef('module_ro_title');
    $this->short_title = $this->app->getDef('module_ro_short_title');
    $this->introduction = $this->app->getDef('module_ro_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_RETURN_ORDERS_RO_STATUS') && (trim(CLICSHOPPING_APP_RETURN_ORDERS_RO_STATUS) != '');
  }

  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_PRODUCTS_RETURN_ORDERS_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_PRODUCTS_RETURN_ORDERS_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_RETURN_ORDERS_INSTALLED', implode(';', $installed));
  }

  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_PRODUCTS_RETURN_ORDERS_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_RETURN_ORDERS_INSTALLED', implode(';', $installed));
    }
  }
}
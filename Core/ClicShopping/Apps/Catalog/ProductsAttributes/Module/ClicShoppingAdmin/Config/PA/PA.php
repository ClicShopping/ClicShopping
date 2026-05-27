<?php
/**
 * The PA class is a configuration module for the Products Attributes application
 * in the ClicShopping admin panel.
 *
 * This class extends the ConfigAbstract class and provides initialization,
 * installation, and uninstallation functionality for the module.
 *
 * Properties:
 * - $pm_code: Specifies the module code.
 * - $is_uninstallable: Indicates whether the module can be uninstalled.
 * - $sort_order: Determines the order in which the module is displayed.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Module\ClicShoppingAdmin\Config\PA;

class PA extends \ClicShopping\Apps\Catalog\ProductsAttributes\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'products_attributes';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  protected function init()
  {
    $this->title = $this->app->getDef('module_pa_title');
    $this->short_title = $this->app->getDef('module_pa_short_title');
    $this->introduction = $this->app->getDef('module_pa_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS') && (trim(CLICSHOPPING_APP_PRODUCTS_ATTRIBUTES_PA_STATUS) != '');
  }

  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_PRODUCTS_ATTRIBUTES_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_PRODUCTS_ATTRIBUTES_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_ATTRIBUTES_INSTALLED', implode(';', $installed));
  }

  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_PRODUCTS_ATTRIBUTES_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_ATTRIBUTES_INSTALLED', implode(';', $installed));
    }
  }
}
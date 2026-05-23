<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsQuantityUnit\Module\ClicShoppingAdmin\Config\PQ;

class PQ extends \ClicShopping\Apps\Configuration\ProductsQuantityUnit\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'products_quantity_unit';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module with its title, short title, introduction,
   * and determines if it is installed by checking its status definition.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_pq_title');
    $this->short_title = $this->app->getDef('module_pq_short_title');
    $this->introduction = $this->app->getDef('module_pq_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_PRODUCTS_QUANTITY_UNIT_PQ_STATUS') && (trim(CLICSHOPPING_APP_PRODUCTS_QUANTITY_UNIT_PQ_STATUS) != '');
  }

  /**
   * Installs the module and adds it to the list of installed modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_PRODUCTS_QUANTITY_UNIT_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_PRODUCTS_QUANTITY_UNIT_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_QUANTITY_UNIT_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module and removes it from the list of installed modules.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_PRODUCTS_QUANTITY_UNIT_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_PRODUCTS_QUANTITY_UNIT_INSTALLED', implode(';', $installed));
    }
  }
}
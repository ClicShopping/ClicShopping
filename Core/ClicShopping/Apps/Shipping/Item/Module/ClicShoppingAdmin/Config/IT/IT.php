<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Shipping\Item\Module\ClicShoppingAdmin\Config\IT;
/**
 * Class IT
 *
 * This class represents the configuration handler for the "Item" shipping module in the ClicShoppingAdmin application.
 * It provides installation and uninstallation logic for the module and manages related configuration parameters.
 */
class IT extends \ClicShopping\Apps\Shipping\Item\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'Item';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its title, short title, introduction message,
   * and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_item_title');
    $this->short_title = $this->app->getDef('module_item_short_title');
    $this->introduction = $this->app->getDef('module_item_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_ITEM_IT_STATUS') && (trim(CLICSHOPPING_APP_ITEM_IT_STATUS) != '');
  }

  /**
   * Installs the shipping module by adding its entry to the list of installed modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_SHIPPING_INSTALLED')) {
      $installed = explode(';', MODULE_SHIPPING_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_SHIPPING_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the shipping module by removing its entry from the list of installed modules.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_SHIPPING_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_SHIPPING_INSTALLED', implode(';', $installed));
    }
  }
}
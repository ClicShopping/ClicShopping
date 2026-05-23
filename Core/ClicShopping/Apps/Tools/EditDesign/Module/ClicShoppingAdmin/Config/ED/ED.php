<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\EditDesign\Module\ClicShoppingAdmin\Config\ED;

class ED extends \ClicShopping\Apps\Tools\EditDesign\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'data_base_tables';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  /**
   * Initializes the module by setting its titles, introduction, and installation status.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_de_title');
    $this->short_title = $this->app->getDef('module_de_short_title');
    $this->introduction = $this->app->getDef('module_de_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_EDIT_DESIGN_ED_STATUS') && (trim(CLICSHOPPING_APP_EDIT_DESIGN_ED_STATUS) != '');
  }

  /**
   * Installs the module and adds its entry to the installed modules list.
   *
   * @return bool Returns true if the installation process is completed successfully.
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_EDIT_DESIGN_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_EDIT_DESIGN_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_EDIT_DESIGN_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module and removes its entry from the installed modules list.
   *
   * @return bool Returns true if the uninstallation process is completed successfully.
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_EDIT_DESIGN_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_EDIT_DESIGN_INSTALLED', implode(';', $installed));
    }
  }
}
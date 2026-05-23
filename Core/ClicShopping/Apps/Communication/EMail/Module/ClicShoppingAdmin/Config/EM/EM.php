<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\EMail\Module\ClicShoppingAdmin\Config\EM;

/**
 * Class EM
 *
 * A configuration module for the ClicShopping E-Mail application within the ClicShoppingAdmin interface.
 * This module defines initialization, installation, and uninstallation logic specific to this configuration.
 * It extends the ConfigAbstract class from the ClicShopping framework.
 */
class EM extends \ClicShopping\Apps\Communication\EMail\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'email';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  protected function init()
  {
    $this->title = $this->app->getDef('module_em_title');
    $this->short_title = $this->app->getDef('module_em_short_title');
    $this->introduction = $this->app->getDef('module_em_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_EMAIL_EM_STATUS') && (trim(CLICSHOPPING_APP_EMAIL_EM_STATUS) != '');
  }

  /**
   * Installs the module by adding its identifier to the list of installed modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_EMAIL_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_EMAIL_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_EMAIL_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the current module by removing its entry from the list of installed modules
   * and updating the configuration parameter.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_EMAIL_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_EMAIL_INSTALLED', implode(';', $installed));
    }
  }
}
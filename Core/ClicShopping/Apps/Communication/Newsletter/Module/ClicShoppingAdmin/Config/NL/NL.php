<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter\Module\ClicShoppingAdmin\Config\NL;

/**
 * This class represents the Newsletter configuration module within the ClicShoppingAdmin panel.
 * It extends the ConfigAbstract class and provides functionality to manage the Newsletter module configuration.
 */
class NL extends \ClicShopping\Apps\Communication\Newsletter\Module\ClicShoppingAdmin\Config\ConfigAbstract
{

  protected $pm_code = 'newsletter';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 400;

  protected function init()
  {
    $this->title = $this->app->getDef('module_nl_title');
    $this->short_title = $this->app->getDef('module_nl_short_title');
    $this->introduction = $this->app->getDef('module_nl_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_NEWSLETTER_NL_STATUS') && (trim(CLICSHOPPING_APP_NEWSLETTER_NL_STATUS) != '');
  }

  /**
   * Installs the module by adding its identifier to the list of installed newsletter modules.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_NEWSLETTER_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_NEWSLETTER_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('NL', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing its entry from the list of installed modules.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_NEWSLETTER_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_NEWSLETTER_INSTALLED', implode(';', $installed));
    }
  }
}
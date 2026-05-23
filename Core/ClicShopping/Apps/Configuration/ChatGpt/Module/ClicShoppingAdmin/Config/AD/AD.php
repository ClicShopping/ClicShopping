<?php
/**
 * Agent Domains Configuration Module
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AD;

use ClicShopping\OM\Registry;

/**
 * This class represents the ChatGPT configuration module within the ClicShoppingAdmin environment.
 * It extends the ConfigAbstract class and provides functionality for initializing, installing,
 * and uninstalling the ChatGPT module.
 */
class AD extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigAbstract
{
  protected $pm_code = 'chatgpt';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 600;

  /**
   * Initializes the module by setting its title, short title, introduction, and installation status
   * based on the application definitions and configuration constants.
   *
   * @return void
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_ad_agent_domains_title');
    $this->short_title = $this->app->getDef('module_ad_agent_domains_short_title');
    $this->introduction = $this->app->getDef('module_ad_agent_domains_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_CHATGPT_AD_DOMAINS_STATUS') && (trim(CLICSHOPPING_APP_CHATGPT_AD_DOMAINS_STATUS) != '');
  }

  /**
   * Installs the current module and updates the list of installed modules configuration.
   *
   * @return void
   */
  public function install()
  {
    parent::install();

    if (\defined('MODULE_MODULES_CHATGPT_INSTALLED')) {
      $installed = explode(';', MODULE_MODULES_CHATGPT_INSTALLED);
    }

    $installed[] = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    $this->app->saveCfgParam('MODULE_MODULES_CHATGPT_INSTALLED', implode(';', $installed));
  }

  /**
   * Uninstalls the module by removing its entry from the installed modules configuration.
   *
   * @return void
   */
  public function uninstall()
  {
    parent::uninstall();

    $installed = explode(';', MODULE_MODULES_CHATGPT_INSTALLED);
    $installed_pos = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed);

    if ($installed_pos !== false) {
      unset($installed[$installed_pos]);

      $this->app->saveCfgParam('MODULE_MODULES_CHATGPT_INSTALLED', implode(';', $installed));
    }
  }
}

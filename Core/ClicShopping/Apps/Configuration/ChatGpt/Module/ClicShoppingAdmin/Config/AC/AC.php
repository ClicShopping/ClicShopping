<?php
/**
 * Agent Critics Configuration Module
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AC;

/**
 * AgentCritics Configuration Module
 * 
 * Manages individual critic activation settings.
 * Controls which critics are available for action evaluation.
 * 
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AgentCritics
 * @since 4.2.0
 */
class AC extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigAbstract
{
  protected $pm_code = 'chatgpt';

  public bool $is_uninstallable = true;
  public int|null $sort_order = 330;

  /**
   * Initialize module configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('module_ac_agent_critics_title');
    $this->short_title = $this->app->getDef('module_ac_agent_critics_short_title');
    $this->introduction = $this->app->getDef('module_ac_agent_critics_introduction');
    $this->is_installed = \defined('CLICSHOPPING_APP_CHATGPT_AC_STATUS') && (trim(CLICSHOPPING_APP_CHATGPT_AC_STATUS) != '');
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

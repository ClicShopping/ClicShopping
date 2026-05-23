<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

class CfgmDashboard
{
  public string $code = 'dashboard';
  public string $directory;
  public $language_directory;
  public $site = 'ClicShoppingAdmin';
  public string $key = 'MODULE_ADMIN_DASHBOARD_INSTALLED';
  public $title;
  public bool $template_integration = false;

  /**
   * Initializes the dashboard module by setting up necessary directories and the module's title.
   *
   * @return void
   */
  public function __construct()
  {
    $this->directory = CLICSHOPPING::getConfig('dir_root', $this->site) . 'Core/modules/dashboard/';
    $this->language_directory = CLICSHOPPING::getConfig('dir_root') . 'Core/languages/';

    $this->title = CLICSHOPPING::getDef('module_cfg_module_dashboard_title');
  }
}
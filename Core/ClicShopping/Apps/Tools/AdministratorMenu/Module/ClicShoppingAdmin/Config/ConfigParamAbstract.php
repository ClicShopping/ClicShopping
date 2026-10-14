<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\AdministratorMenu\Module\ClicShoppingAdmin\Config;

use ClicShopping\OM\Registry;

abstract class ConfigParamAbstract extends \ClicShopping\Sites\ClicShoppingAdmin\ConfigParamAbstract
{
  public mixed $app;
  protected $config_module;

  protected string $key_prefix = 'clicshopping_app_administrator_menu_';
  public bool $app_configured = true;

  /**
   * Constructs a new instance of the class and initializes its properties.
   *
   * @param string $config_module The configuration module name used for initialization.
   * @return void
   */
  public function __construct($config_module)
  {
    $this->app = Registry::get('AdministratorMenu');

    $this->key_prefix .= mb_strtolower($config_module) . '_';

    $this->config_module = $config_module;

    $this->code = (new \ReflectionClass($this))->getShortName();

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Config/' . $config_module . '/Params/' . $this->code);
    parent::__construct();
  }
}

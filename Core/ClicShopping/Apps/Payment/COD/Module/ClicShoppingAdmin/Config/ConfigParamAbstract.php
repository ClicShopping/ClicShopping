<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\COD\Module\ClicShoppingAdmin\Config;

use ClicShopping\OM\Registry;

/**
 * Abstract class ConfigParamAbstract.
 *
 * Provides an abstract base for configuring parameters within the COD app in the ClicShoppingAdmin environment.
 */
abstract class ConfigParamAbstract extends \ClicShopping\Sites\ClicShoppingAdmin\ConfigParamAbstract
{
  public mixed $app;
  protected $config_module;

  protected string $key_prefix = 'clicshopping_app_cod_';
  public bool $app_configured = true;

  /**
   * Constructor method.
   *
   * @param string $config_module The configuration module name used to initialize the object and load related definitions.
   * @return void
   */
  public function __construct($config_module)
  {
    $this->app = Registry::get('COD');

    $this->key_prefix .= mb_strtolower($config_module) . '_';

    $this->config_module = $config_module;

    $this->code = (new \ReflectionClass($this))->getShortName();

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Config/' . $config_module . '/Params/' . $this->code);
    parent::__construct();
  }
}

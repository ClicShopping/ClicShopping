<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Stripe\Module\ClicShoppingAdmin\Config;

use ClicShopping\OM\Registry;

/**
 * Abstract base class for configuration parameters in the Stripe payment module.
 *
 * This class is used to represent and handle configuration parameters for
 * the Stripe module within the ClicShoppingAdmin application. It provides
 * basic functionality for initialization and definition loading.
 *
 * Properties:
 * - $app: Instance of the Stripe application.
 * - $config_module: Represents the name of the configuration module handled by this parameter.
 * - $key_prefix: Prefix applied to the keys related to the configuration parameters.
 * - $app_configured: Boolean indicating if the app is properly configured.
 *
 * Methods:
 * - __construct: Initializes the configuration parameter object with the specified module name,
 *                sets up the application instance, key prefix, and loads necessary definitions.
 */
abstract class ConfigParamAbstract extends \ClicShopping\Sites\ClicShoppingAdmin\ConfigParamAbstract
{
  public mixed $app;
  protected $config_module;

  protected string $key_prefix = 'clicshopping_app_stripe_';
  public bool $app_configured = true;

  /**
   * Constructor for the class.
   *
   * @param string $config_module The configuration module name to initialize the object with.
   * @return void
   */
  public function __construct($config_module)
  {
    $this->app = Registry::get('Stripe');

    $this->key_prefix .= mb_strtolower($config_module) . '_';

    $this->config_module = $config_module;

    /**
     *
     */
      $this->code = (new \ReflectionClass($this))->getShortName();

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Config/' . $config_module . '/Params/' . $this->code);
    parent::__construct();
  }
}

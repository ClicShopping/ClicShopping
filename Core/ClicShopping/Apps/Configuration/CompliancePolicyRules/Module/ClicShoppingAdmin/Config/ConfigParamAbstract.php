<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

abstract class ConfigParamAbstract extends \ClicShopping\Sites\ClicShoppingAdmin\ConfigParamAbstract
{
  public mixed $app;
  protected $config_module;
  protected string $key_prefix = 'clicshopping_app_compliance_policy_rules_';
  public bool $app_configured = true;

  /** Secrets stored encrypted (`Hash::encryptDatatext`); read them through `Hash::displayDecryptedDataText`. */
  public const array ENCRYPTED_KEYS = [
    'CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_FRE_CHORUS_PRO_CLIENT_SECRET',
    'CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_FRE_CHORUS_PRO_TECHNICAL_PASSWORD',
    'CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_FRE_PAPPERS_API_TOKEN',
  ];

  /**
   * Constructor for initializing the module configuration and loading definitions.
   *
   * @param string $config_module The name of the configuration module being initialized.
   * @return void
   */
  public function __construct($config_module)
  {
    $this->app = Registry::get('CompliancePolicyRules');

    if ($config_module != 'CPR') {
      $this->key_prefix .= mb_strtolower($config_module) . '_';
    }

    $this->config_module = $config_module;

    $this->code = (new \ReflectionClass($this))->getShortName();

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Config/' . $config_module . '/Params/' . $this->code);

    parent::__construct();
  }

  /**
   * A secret is never sent back to the page: empty password field, left empty = value kept.
   *
   * @return string
   */
  public function getInputField()
  {
    if (!in_array(mb_strtoupper($this->key), self::ENCRYPTED_KEYS, true)) {
      return parent::getInputField();
    }

    $placeholder = (string)$this->getInputValue() !== '' ? ' placeholder="••••••••"' : '';

    return HTML::passwordField($this->key, '', 'id="' . $this->key . '" autocomplete="new-password"' . $placeholder);
  }
}

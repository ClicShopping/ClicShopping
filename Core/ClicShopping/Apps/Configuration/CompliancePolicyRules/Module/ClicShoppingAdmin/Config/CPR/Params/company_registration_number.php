<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\CPR\Params;

class company_registration_number extends \ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'RCS : 1234567';
  public int|null $sort_order = 110;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_configuration_compliance_policy_rules_eu_vat_number_title');
    $this->description = $this->app->getDef('cfg_configuration_compliance_policy_rules_eu_vat_number_description');
  }
}

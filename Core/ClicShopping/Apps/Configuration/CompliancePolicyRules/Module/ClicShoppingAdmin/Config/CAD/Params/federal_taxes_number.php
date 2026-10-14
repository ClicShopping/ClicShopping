<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\CAD\Params;

class federal_taxes_number extends \ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = 'TPS/GST : R127066546';
  public int|null $sort_order = 10;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_configuration_compliance_policy_rules_federal_taxes_number_title');
    $this->description = $this->app->getDef('cfg_configuration_compliance_policy_rules_federal_taxes_number_description');
  }
}

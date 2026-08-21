<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\CPR\Params;

class url_sales_conditions extends \ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = 'Info&Content&pagesId=4';
  public int|null $sort_order = 40;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_configuration_compliance_policy_rules_url_sales_conditions_title');
    $this->description = $this->app->getDef('cfg_configuration_compliance_policy_rules_url_sales_conditions_description');
  }
}

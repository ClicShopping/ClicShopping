<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\CPR\Params;

class url_privacy extends \ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = 'Info&Content&pagesId=5';
  public int|null $sort_order = 50;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_configuration_compliance_policy_rules_url_privacy_title');
    $this->description = $this->app->getDef('cfg_configuration_compliance_policy_rules_url_privacy_description');
  }
}

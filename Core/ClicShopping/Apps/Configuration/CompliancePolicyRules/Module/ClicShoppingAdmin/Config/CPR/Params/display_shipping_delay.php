<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\CPR\Params;

class display_shipping_delay extends \ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '4 days';
  public int|null $sort_order = 80;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_configuration_compliance_policy_rules_display_shipping_delay_title');
    $this->description = $this->app->getDef('cfg_configuration_compliance_policy_rules_display_shipping_delay_description');
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\CPR\Params;

/**
 * Separator between the date and the order id in the invoice number (historically 'S').
 */
class invoice_number_reference extends \ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'S';
  public int|null $sort_order = 130;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_configuration_compliance_policy_rules_invoice_number_reference_title');
    $this->description = $this->app->getDef('cfg_configuration_compliance_policy_rules_invoice_number_reference_description');
  }
}

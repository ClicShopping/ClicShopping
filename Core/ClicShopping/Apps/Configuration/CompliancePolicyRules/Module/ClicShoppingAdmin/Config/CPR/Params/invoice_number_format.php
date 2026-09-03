<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\CPR\Params;

use ClicShopping\OM\HTML;

/**
 * Date part of the invoice number. The value IS the PHP date format, so no mapping table.
 * Kept out of the language definition `date_format`: that one is a display setting, and the
 * invoice number must not change with the language it is read in.
 */
class invoice_number_format extends \ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'm/d/Y';
  public int|null $sort_order = 120;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_configuration_compliance_policy_rules_invoice_number_format_title');
    $this->description = $this->app->getDef('cfg_configuration_compliance_policy_rules_invoice_number_format_description');
  }

  public function getInputField()
  {
    $array = [
      ['id' => 'd/m/Y', 'text' => $this->app->getDef('cfg_configuration_compliance_policy_rules_invoice_number_format_dmy')],
      ['id' => 'm/d/Y', 'text' => $this->app->getDef('cfg_configuration_compliance_policy_rules_invoice_number_format_mdy')],
      ['id' => 'Y-m-d', 'text' => $this->app->getDef('cfg_configuration_compliance_policy_rules_invoice_number_format_ymd')],
    ];

    return HTML::selectField($this->key, $array, $this->getInputValue(), 'id="' . $this->key . '"');
  }
}

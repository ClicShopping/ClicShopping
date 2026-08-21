<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\FRE\Params;

use ClicShopping\OM\HTML;

class chorus_pro_sandbox extends \ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'False';
  public int|null $sort_order = 40;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_configuration_compliance_policy_rules_chorus_pro_sandbox_title');
    $this->description = $this->app->getDef('cfg_configuration_compliance_policy_rules_chorus_pro_sandbox_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::radioField($this->key, 'True', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_configuration_compliance_policy_rules_chorus_pro_sandbox_true') . ' ';
    $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_configuration_compliance_policy_rules_chorus_pro_sandbox_false');

    return $input;
  }
}
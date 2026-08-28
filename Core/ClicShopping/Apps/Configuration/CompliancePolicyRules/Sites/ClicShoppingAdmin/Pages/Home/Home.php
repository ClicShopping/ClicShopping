<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Sites\ClicShoppingAdmin\Pages\Home;

use ClicShopping\Apps\Configuration\CompliancePolicyRules\CompliancePolicyRules;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Domains\PagesAbstract;

class Home extends PagesAbstract
{
  public mixed $app;

  protected function init()
  {
    Registry::remove('CompliancePolicyRules');

    if (!Registry::exists('CompliancePolicyRules')) {
      $CLICSHOPPING_CompliancePolicyRules = new CompliancePolicyRules();
      Registry::set('CompliancePolicyRules', $CLICSHOPPING_CompliancePolicyRules);
     }

    $this->app = $CLICSHOPPING_CompliancePolicyRules;

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}

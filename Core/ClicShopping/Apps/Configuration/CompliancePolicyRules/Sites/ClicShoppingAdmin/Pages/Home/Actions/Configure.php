<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\CompliancePolicyRules\Classes\Shared\InvoiceFooterRenderer;

class Configure extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_CompliancePolicyRules = Registry::get('CompliancePolicyRules');

    AdministratorAdmin::checkUserAccess();

    $this->page->setFile('configure.php');
    $this->page->data['action'] = 'Configure';

    $CLICSHOPPING_CompliancePolicyRules->loadDefinitions('ClicShoppingAdmin/configure');

    $modules = $CLICSHOPPING_CompliancePolicyRules->getConfigModules();

    $default_module = 'CPR';

    foreach ($modules as $m) {
      if ($CLICSHOPPING_CompliancePolicyRules->getConfigModuleInfo($m, 'is_installed') === true) {
        $default_module = $m;
        break;
      }
    }

    // One invoice, one fiscal jurisdiction: several active countries is a configuration error.
    $conflict = InvoiceFooterRenderer::conflictingCountries();

    if ($conflict !== []) {
      Registry::get('MessageStack')->add($CLICSHOPPING_CompliancePolicyRules->getDef('alert_country_conflict', ['countries' => implode(', ', $conflict)]), 'warning', 'CompliancePolicyRules');
    }

    $this->page->data['current_module'] = (isset($_GET['module']) && \in_array($_GET['module'], $modules, true)) ? $_GET['module'] : $default_module;
  }
}
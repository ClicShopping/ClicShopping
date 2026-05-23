<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\OM\Registry;

/**
 * Class Configure
 *
 * This action class is responsible for handling the configuration of the Products app in the admin interface.
 * It sets up the configuration page, determines the current module to be configured, and loads necessary language definitions.
 */
class Configure extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * Execute the action to display the configuration page for the Products app.
   *
   * This method checks user access, sets the appropriate file for the configuration page,
   * loads language definitions, retrieves available configuration modules, and determines
   * the current module to be displayed based on user input or defaults.
   */
  public function execute()
  {
    $CLICSHOPPING_Products = Registry::get('Products');

    AdministratorAdmin::checkUserAccess();

    $this->page->setFile('configure.php');
    $this->page->data['action'] = 'Configure';

    $CLICSHOPPING_Products->loadDefinitions('ClicShoppingAdmin/configure');

    $modules = $CLICSHOPPING_Products->getConfigModules();

    $default_module = 'PD';

    foreach ($modules as $m) {
      if ($CLICSHOPPING_Products->getConfigModuleInfo($m, 'is_installed') === true) {
        $default_module = $m;
        break;
      }
    }

    $this->page->data['current_module'] = (isset($_GET['module']) && \in_array($_GET['module'], $modules)) ? $_GET['module'] : $default_module;
  }
}
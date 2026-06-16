<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\COD\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\OM\Registry;

/**
 * Configure action class for Cash on Delivery (COD) payment module administration.
 * 
 * This class handles the configuration page for the COD payment integration,
 * managing module selection, access control, and configuration interface setup
 * within the ClicShoppingAdmin environment.
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
class Configure extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * Execute the configuration action.
   * 
   * Sets up the configuration page for the COD module, including:
   * - Administrator access verification
   * - Loading configuration definitions
   * - Determining available and installed modules
   * - Setting the current module (default: 'CO') for display
   * 
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_COD = Registry::get('COD');

    AdministratorAdmin::checkUserAccess();

    $this->page->setFile('configure.php');
    $this->page->data['action'] = 'Configure';

    $CLICSHOPPING_COD->loadDefinitions('ClicShoppingAdmin/configure');

    $modules = $CLICSHOPPING_COD->getConfigModules();

    $default_module = 'CO';

    foreach ($modules as $m) {
      if ($CLICSHOPPING_COD->getConfigModuleInfo($m, 'is_installed') === true) {
        $default_module = $m;
        break;
      }
    }

    $this->page->data['current_module'] = (isset($_GET['module']) && \in_array($_GET['module'], $modules)) ? $_GET['module'] : $default_module;
  }
}
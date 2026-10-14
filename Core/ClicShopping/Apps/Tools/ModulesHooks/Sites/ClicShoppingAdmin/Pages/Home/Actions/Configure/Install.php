<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ModulesHooks\Sites\ClicShoppingAdmin\Pages\Home\Actions\Configure;

use ClicShopping\Apps\Tools\ModulesHooks\Sql\MariaDb\MariaDb;
use ClicShopping\OM\Registry;

/**
 * Install action for Sites module configuration.
 * Handles the Install process with centralized functionality.
 */
class Install extends \ClicShopping\OM\Domains\ConfigureActionsAbstract
{
    /**
   * Execute the installation process for Sites module
   */
  public function execute()
  {
    $this->init();
    
    $current_module = $this->getCurrentModule();
    
    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/install');
    
    $m = $this->getConfigModule($current_module);
    $m->install();
    
    // Install database menu - add condition to select MariaDb or PostgreSQL
    Registry::set('MariaDb', new MariaDb());
    $CLICSHOPPING_MariaDb = Registry::get('MariaDb');
    $CLICSHOPPING_MariaDb->execute();
    
    $this->addSuccessMessage($this->app->getDef('alert_module_install_success'));
    $this->redirectToConfigure($current_module);
  }
}

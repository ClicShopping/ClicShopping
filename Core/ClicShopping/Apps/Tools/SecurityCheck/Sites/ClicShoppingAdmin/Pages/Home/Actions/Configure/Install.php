<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Tools\SecurityCheck\Sites\ClicShoppingAdmin\Pages\Home\Actions\Configure;

use ClicShopping\Apps\Tools\SecurityCheck\Sql\MariaDb\MariaDb;
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

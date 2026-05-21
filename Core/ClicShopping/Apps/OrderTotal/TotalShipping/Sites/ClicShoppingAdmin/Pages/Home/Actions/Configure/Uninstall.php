<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\OrderTotal\TotalShipping\Sites\ClicShoppingAdmin\Pages\Home\Actions\Configure;

/**
 * Uninstall action for Sites module configuration.
 * Handles the Uninstall process with centralized functionality.
 */
class Uninstall extends \ClicShopping\OM\Domains\ConfigureActionsAbstract
{

    /**
   * Execute the uninstallation process for Sites module
   */
  public function execute()
  {
    $this->init();
    
    $current_module = $this->getCurrentModule();
    $m = $this->getConfigModule($current_module);
    $m->uninstall();

    $this->clearMenuCache();
    $this->addSuccessMessage($this->app->getDef('alert_module_uninstall_success'));
    $this->redirectToConfigure($current_module);
  }
}
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

/**
 * Process action for Sites module configuration.
 * Handles the configuration processing with centralized functionality.
 */
class Process extends \ClicShopping\OM\Domains\ConfigureActionsAbstract
{
  /**
   * Execute the configuration processing for Sites module
   */
  public function execute()
  {
    $this->init();
    
    $current_module = $this->getCurrentModule();
    $m = $this->getConfigModule($current_module);
    
    foreach ($m->getParameters() as $key) {
      $p = mb_strtolower($key);
      
      if (isset($_POST[$p])) {
        $this->app->saveCfgParam($key, $_POST[$p]);
      }
    }
    
    $this->addSuccessMessage($this->app->getDef('alert_cfg_saved_success'));
    $this->redirectToConfigure($current_module);
  }
}

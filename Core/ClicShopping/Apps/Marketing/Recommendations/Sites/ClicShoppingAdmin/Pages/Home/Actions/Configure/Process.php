<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Sites\ClicShoppingAdmin\Pages\Home\Actions\Configure;

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

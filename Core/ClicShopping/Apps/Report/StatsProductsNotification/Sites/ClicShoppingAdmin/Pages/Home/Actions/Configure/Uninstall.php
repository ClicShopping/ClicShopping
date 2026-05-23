<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Report\StatsProductsNotification\Sites\ClicShoppingAdmin\Pages\Home\Actions\Configure;

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
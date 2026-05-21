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

use ClicShopping\OM\Registry;

/**
 * Delete action for Sites module configuration.
 * Handles the Delete process with centralized functionality.
 */
class Delete extends \ClicShopping\OM\Domains\ConfigureActionsAbstract
{

    /**
   * Execute the deletion process for Sites module
   */
  public function execute()
  {
    $this->init();
    
    $current_module = $this->getCurrentModule();
    $m = $this->getConfigModule($current_module);
    $m->uninstall();
    
    // Remove menu if method exists
    if (method_exists($this, 'removeMenu')) {
      $this->removeMenu();
    }
    
    $this->clearMenuCache();
    $this->addSuccessMessage($this->app->getDef('alert_module_uninstall_success'));
    $this->redirectToConfigure($current_module);
  }

  private static function removeMenu(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_order_total_shipping']);

    if ($Qcheck->fetch()) {

      $QMenuId = $CLICSHOPPING_Db->prepare('select id
                                        from :table_administrator_menu
                                        where app_code = :app_code
                                      ');

      $QMenuId->bindValue(':app_code', 'app_order_total_shipping');
      $QMenuId->execute();

      $menu = $QMenuId->fetchAll();

      $menu1 = \count($menu);

      for ($i = 0, $n = $menu1; $i < $n; $i++) {
        $CLICSHOPPING_Db->delete('administrator_menu_description', ['id' => (int)$menu[$i]['id']]);
      }

      $CLICSHOPPING_Db->delete('administrator_menu', ['app_code' => 'app_order_total_shipping']);
    }
  }
}
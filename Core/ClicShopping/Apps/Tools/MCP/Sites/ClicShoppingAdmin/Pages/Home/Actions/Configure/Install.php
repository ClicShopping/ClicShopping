<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\ClicShoppingAdmin\Pages\Home\Actions\Configure;

use ClicShopping\OM\Cache;
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
    
    // Install database menu if method exists
    if (method_exists($this, 'installDbMenuAdministration')) {
      self::installDbMenuAdministration();
    }
    
    $this->addSuccessMessage($this->app->getDef('alert_module_install_success'));
    $this->redirectToConfigure($current_module);
  }

  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_MCP = Registry::get('MCP');
    $CLICSHOPPING_Language = Registry::get('Language');
    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_tools_mcp']);

    if ($Qcheck->fetch() === false) {

      $sql_data_array = ['sort_order' => 3,
        'link' => 'index.php?A&Tools\MCP&MCP',
        'image' => '',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_tools_mcp'
      ];

      $insert_sql_data = ['parent_id' => 163];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();

      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {

        $language_id = $languages[$i]['id'];

        $sql_data_array = ['label' => $CLICSHOPPING_MCP->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

      Cache::clear('menu-administrator');
    }
}
}

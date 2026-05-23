<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ActionsRecorder\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the administration menu and loads the required definitions for the Actions Recorder.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_ActionsRecorder = Registry::get('ActionsRecorder');
    $CLICSHOPPING_ActionsRecorder->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs the menu entry into the administrator menu for the Actions Recorder application.
   *
   * This method checks if the menu item for the Actions Recorder application already exists
   * in the administrator menu. If it does not exist, it adds the menu item with the required
   * properties and links, as well as its translations for all supported languages. After
   * successfully adding the menu item, the administrator menu cache is cleared to reflect the changes.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_ActionsRecorder = Registry::get('ActionsRecorder');
    $CLICSHOPPING_Language = Registry::get('Language');
    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_tools_actions_recorder']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 1,
        'link' => 'index.php?A&Tools\ActionsRecorder&ActionsRecorder',
        'image' => 'menu.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_tools_actions_recorder'
      ];

      $insert_sql_data = ['parent_id' => 178];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_ActionsRecorder->getDef('title_menu')];

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
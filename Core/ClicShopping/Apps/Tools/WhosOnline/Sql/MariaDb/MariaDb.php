<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\WhosOnline\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation operations for the WhosOnline module.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_WhosOnline = Registry::get('WhosOnline');
    $CLICSHOPPING_WhosOnline->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installMenuAdministration();
  }

  /**
   * Installs the menu administration configuration for the "Who's Online" tool.
   * This method checks if the menu entry exists in the database and creates it if necessary.
   * It also handles the insertion of multi-language labels for the menu entry
   * and clears the administrator menu cache.
   *
   * @return void
   */
  private static function installMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_WhosOnline = Registry::get('WhosOnline');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_tools_whos_online']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = ['sort_order' => 30,
        'link' => 'index.php?A&Tools\WhosOnline&WhosOnline',
        'image' => 'whos_online.gif',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_tools_whos_online'
      ];

      $insert_sql_data = ['parent_id' => 163];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_WhosOnline->getDef('title_menu')];

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
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

class Status
{
  /**
   * Updates the status of a language in the database.
   *
   * This method allows for enabling or disabling a language based on the provided status value.
   * If the language being disabled is the default language and it is not 'en', the default language
   * configuration is updated to 'en'.
   *
   * @param int $languages_id The ID of the language to update.
   * @param int $status The desired status of the language (1 to enable, 0 to disable).
   * @return int|bool Returns the result of the database operation, or -1 if the status is invalid.
   */
  public static function getLanguageStatus(int $languages_id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status === 1) {
      return $CLICSHOPPING_Db->save('languages', ['status' => 1], ['languages_id' => (int)$languages_id]);
    } elseif ($status === 0) {
      $Qcheck = $CLICSHOPPING_Db->prepare('select code
                                             from :table_languages
                                             where languages_id = :languages_id
                                            ');
      $Qcheck->bindValue(':languages_id', $languages_id);
      $Qcheck->execute();

      if ($Qcheck->value('code') == DEFAULT_LANGUAGE && $Qcheck->value('code') != 'en') {
        $Qupdate = $CLICSHOPPING_Db->prepare('update :table_configuration
                                                set configuration_value = :configuration_value
                                                where configuration_key = :configuration_key
                                               ');
        $Qupdate->bindValue(':configuration_value', 'en');
        $Qupdate->bindValue(':configuration_key', 'DEFAULT_LANGUAGE');

        $Qupdate->execute();
      }

      return $CLICSHOPPING_Db->save('languages', ['status' => 0], ['languages_id' => (int)$languages_id]);
    } else {
      return -1;
    }
  }
}

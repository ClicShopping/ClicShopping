<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

class LanguageAdmin
{
  /**
   * Retrieves the latest language ID from the database.
   *
   * @return int The latest language ID.
   */
  public static function getLatestLanguageID(): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->prepare('select languages_id
                                           from :table_languages
                                           order by languages_id desc
                                           limit 1
                                        ');
    $Qcheck->execute();

    $language_id = $Qcheck->valueInt('languages_id');

    return $language_id;
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Classes;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class CreateAccount
{
  /**
   * Retrieves the ISO code (2 characters) of the country associated with the predefined country ID.
   *
   * @return string Returns the 2-character ISO code of the specified country.
   */
  public static function getCountryPro(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QcountryPro = $CLICSHOPPING_Db->prepare('select countries_iso_code_2
                                                 from :table_countries
                                                 where countries_id = :countries_id
                                                ');
    $QcountryPro->bindInt(':countries_id', (int)ACCOUNT_COUNTRY_PRO);
    $QcountryPro->execute();

    $default_country_pro = $QcountryPro->value('countries_iso_code_2');

    return $default_country_pro;
  }

  /**
   * Retrieves the origin URL based on the navigation history snapshot.
   *
   * If a snapshot exists in the navigation history, the method will return
   * the URL from the snapshot and then reset the snapshot. If no snapshot
   * exists, it will return a default redirect URL.
   *
   * @return string The origin URL based on navigation history or default redirect.
   */
  public static function getOriginHref()
  {
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');

    if ($CLICSHOPPING_NavigationHistory->hasSnapshot()) {
      $origin_href = $CLICSHOPPING_NavigationHistory->getSnapshotURL();
      $CLICSHOPPING_NavigationHistory->resetSnapshot();
    } else {
      $origin_href = CLICSHOPPING::redirect();
    }

    return $origin_href;
  }
}
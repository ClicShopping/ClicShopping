<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\WhosOnline\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

class WhosOnlineAdmin
{
  /**
   * Counts the total number of unique users currently online.
   *
   * @return int Returns the total count of distinct users online.
   */
  public static function getCountWhosOnline(): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (isset($_SESSION['admin'])) {
      static::delete();

      $QwhosOnline = $CLICSHOPPING_Db->prepare('select distinct session_id,
                                                                  customer_id,
                                                                  full_name,
                                                                  ip_address,
                                                                  time_entry,
                                                                  time_last_click,
                                                                  last_page_url
                                                   from :table_whos_online
                                                ');
      $QwhosOnline->execute();

      $whosOnlineNumber = $QwhosOnline->rowCount();

      return $whosOnlineNumber;
    }
  }

  /**
   * Deletes expired session data from the `whos_online` table.
   *
   * This method removes records from the database where the `time_last_click` timestamp
   * is less than or equal to the defined expiration threshold.
   *
   * @return void
   */
  private static function delete(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

// Session time before expiration
    $xx_mins_ago = (time() - 900);

// Delete data after expiration time
    $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                            from :table_whos_online
                                            where  time_last_click <= :time_last_click
                                          ');
    $Qdelete->bindValue(':time_last_click', $xx_mins_ago);
    $Qdelete->execute();
  }

  /**
   * Removes entries from the 'whos_online' table in the database based on inactivity.
   *
   * This method identifies records in the 'whos_online' table where the last activity
   * occurred more than 15 minutes ago (calculated as the current time minus 900 seconds)
   * and deletes those entries.
   *
   * @return void
   */
  public static function removeWhoOnline(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $xx_mins_ago = (time() - 900);

    $Qclean = $CLICSHOPPING_Db->prepare('delete
                                           from :table_whos_online
                                           where time_last_click = :time_last_click
                                          ');
    $Qclean->bindValue(':time_last_click', $xx_mins_ago);
    $Qclean->execute();
  }
}
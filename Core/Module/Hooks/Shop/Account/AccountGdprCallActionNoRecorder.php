<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Account;

use ClicShopping\OM\Registry;

class AccountGdprCallActionNoRecorder
{

  /**
   * Deletes entries from the action recorder database table for the current customer if the 'action_recorder' POST parameter is set.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (isset($_POST['action_recorder'])) {
      $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                             from :table_action_recorder
                                             where user_name = :user_name
                                            ');
      $Qdelete->bindValue(':user_name', $CLICSHOPPING_Customer->getEmailAddress());
      $Qdelete->execute();
    }
  }
}

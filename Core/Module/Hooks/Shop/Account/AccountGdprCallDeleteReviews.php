<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Account;

use ClicShopping\OM\Registry;

class AccountGdprCallDeleteReviews
{

  /**
   * Deletes all reviews associated with the currently logged-in customer if the delete_all_reviews POST parameter is set.
   *
   * This method retrieves all review IDs for the logged-in customer and subsequently deletes the reviews
   * and their associated descriptions from the database.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (isset($_POST['delete_all_reviews'])) {
      $Qcheck = $CLICSHOPPING_Db->prepare('select reviews_id
                                            from :table_reviews
                                            where customers_id = :customers_id
                                           ');
      $Qcheck->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qcheck->execute();

      while ($Qcheck->fetch()) {
        $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                                 from :table_reviews
                                                 where reviews_id = :reviews_id
                                               ');
        $Qdelete->bindInt(':reviews_id', $Qcheck->valueInt('reviews_id'));
        $Qdelete->execute();

        $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                                 from :table_reviews_description
                                                 where reviews_id = :reviews_id
                                               ');
        $Qdelete->bindInt(':reviews_id', $Qcheck->valueInt('reviews_id'));
        $Qdelete->execute();
      }
    }
  }
}

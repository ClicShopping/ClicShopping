<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Customers\Members\Sites\ClicShoppingAdmin\Pages\Home\Actions\Members;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class DeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {

    $CLICSHOPPING_Members = Registry::get('Members');

    if (isset($_GET['cID'])) {
      $customers_id = HTML::sanitize($_GET['cID']);


      if ($_POST['delete_reviews'] == 'on') {

        $Qreviews = $CLICSHOPPING_Members->db->prepare('select reviews_id
                                                           from :table_reviews
                                                           where customers_id = :customers_id
                                                           ');
        $Qreviews->bindInt(':customers_id', $customers_id);
        $Qreviews->execute();

        while ($Qreviews->fetch() !== false) {
          $Qdelete = $CLICSHOPPING_Members->db->prepare('delete
                                                           from :table_reviews_description
                                                           where reviews_id = :reviews_id
                                                          ');
          $Qdelete->bindInt(':reviews_id', $Qreviews->valueInt('reviews_id'));
          $Qdelete->execute();
        }

        $Qdelete = $CLICSHOPPING_Members->db->prepare('delete
                                                          from :table_reviews
                                                          where customers_id = :customers_id
                                                        ');
        $Qdelete->bindInt(':customers_id', $customers_id);
        $Qdelete->execute();
      } else {
        $Qupdate = $CLICSHOPPING_Members->db->prepare('update :table_reviews
                                                         set customers_id = :customers_id
                                                         where customers_id = :customers_id1
                                                        ');

        $Qupdate->bindValue(':customers_id', null);
        $Qupdate->bindInt(':customers_id1', $customers_id);

        $Qupdate->execute();
      }

      $Qdelete = $CLICSHOPPING_Members->db->prepare('delete
                                                        from :table_address_book
                                                        where customers_id = :customers_id
                                                      ');
      $Qdelete->bindInt(':customers_id', $customers_id);
      $Qdelete->execute();

      $Qdelete = $CLICSHOPPING_Members->db->prepare('delete
                                                      from :table_customers
                                                      where customers_id = :customers_id
                                                    ');
      $Qdelete->bindInt(':customers_id', $customers_id);
      $Qdelete->execute();

      $Qdelete = $CLICSHOPPING_Members->db->prepare('delete
                                                        from :table_customers_info
                                                        where customers_info_id = :customers_id
                                                      ');
      $Qdelete->bindInt(':customers_id', $customers_id);
      $Qdelete->execute();

      $Qdelete = $CLICSHOPPING_Members->db->prepare('delete
                                                        from :table_customers_basket
                                                        where customers_id = :customers_id
                                                      ');
      $Qdelete->bindInt(':customers_id', $customers_id);
      $Qdelete->execute();

      $Qdelete = $CLICSHOPPING_Members->db->prepare('delete
                                                        from :table_customers_basket_attributes
                                                        where customers_id = :customers_id
                                                      ');
      $Qdelete->bindInt(':customers_id', $customers_id);
      $Qdelete->execute();

      $Qdelete = $CLICSHOPPING_Members->db->prepare('delete
                                                      from :table_whos_online
                                                      where customer_id = :customers_id
                                                    ');
      $Qdelete->bindInt(':customers_id', $customers_id);
      $Qdelete->execute();
    }

    $CLICSHOPPING_Members->redirect('Members');
  }
}
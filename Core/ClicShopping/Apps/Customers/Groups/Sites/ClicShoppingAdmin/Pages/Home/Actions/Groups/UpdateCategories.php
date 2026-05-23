<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Customers\Groups\Sites\ClicShoppingAdmin\Pages\Home\Actions\Groups;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class UpdateCategories extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Groups = Registry::get('Groups');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (isset($_POST['customers_groups_id'])) {
      $customers_group_id = HTML::sanitize($_POST['customers_groups_id']);
    }

    if (isset($_POST['upddiscount']) && empty($_POST['upddiscount'])) {
      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_groups_categories_error_zero'), 'error');

      $CLICSHOPPING_Groups->redirect('Edit&cID=' . $customers_group_id);
    } else {
      if (isset($_POST['upddiscount'])) {
        $new_discount = HTML::sanitize($_POST['upddiscount']);

        if (isset($_POST['catID'])) {
          $category_id = HTML::sanitize($_POST['catID']);


          $Qupdate = $CLICSHOPPING_Groups->db->prepare('update :table_groups_to_categories
                                                        set discount = :discount
                                                        where customers_group_id = :customers_group_id
                                                        and categories_id = :categories_id
                                                       ');
          $Qupdate->bindValue(':discount', $new_discount);
          $Qupdate->bindInt(':customers_group_id', (int)$customers_group_id);
          $Qupdate->bindInt(':categories_id', (int)$category_id);
          $Qupdate->execute();

          $CLICSHOPPING_Hooks->call('CustomersGroup', 'UpdateCategories');

          $CLICSHOPPING_Groups->redirect('Edit&cID=' . (int)$customers_group_id . '#tab4');
        }
      }
    }
  }
}
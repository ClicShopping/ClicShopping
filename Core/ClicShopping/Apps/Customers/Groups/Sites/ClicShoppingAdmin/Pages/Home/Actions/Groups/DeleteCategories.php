<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Sites\ClicShoppingAdmin\Pages\Home\Actions\Groups;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class DeleteCategories extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Groups = Registry::get('Groups');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_POST['catID'])) {
      $category_id = HTML::sanitize($_POST['catID']);

      if (isset($_POST['customers_groups_id'])) {
        $customers_groups_id = HTML::sanitize($_POST['customers_groups_id']);

        $Qdelete = $CLICSHOPPING_Groups->db->prepare('delete
                                                       from :table_groups_to_categories
                                                       where customers_group_id = :customers_group_id
                                                       and categories_id = :categories_id
                                                      ');
        $Qdelete->bindInt(':customers_group_id', (int)$customers_groups_id);
        $Qdelete->bindInt(':categories_id', (int)$category_id);
        $Qdelete->execute();

        $CLICSHOPPING_Hooks->call('CustomersGroup', 'DeleteCategories');

        $CLICSHOPPING_Groups->redirect('Edit&cID=' . $customers_groups_id . '#tab4');
      }
    }
  }
}
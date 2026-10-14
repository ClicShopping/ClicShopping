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

class Delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Groups = Registry::get('Groups');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_GET['cID'])) {
      $group_id = HTML::sanitize($_GET['cID']);
    } else {
      $group_id = null;
    }

    if (!\is_null($group_id)) {
      $Qdelete = $CLICSHOPPING_Groups->db->prepare('delete
                                                      from :table_groups_to_categories
                                                      where customers_group_id = :customers_group_id
                                                    ');
      $Qdelete->bindInt(':customers_group_id', (int)$group_id);
      $Qdelete->execute();

      $Qdelete = $CLICSHOPPING_Groups->db->prepare('delete
                                                      from :table_customers_groups
                                                      where customers_group_id = :customers_group_id
                                                    ');
      $Qdelete->bindInt(':customers_group_id', (int)$group_id);
      $Qdelete->execute();

      $Qdelete = $CLICSHOPPING_Groups->db->prepare('delete
                                                      from :table_products_groups
                                                      where customers_group_id = :customers_group_id
                                                    ');
      $Qdelete->bindInt(':customers_group_id', (int)$group_id);
      $Qdelete->execute();

// Réassigne les clients du groupe supprimé vers le groupe par défaut (0) pour éviter
// qu'ils pointent vers un groupe inexistant (intégrité référentielle).
      $Qreset = $CLICSHOPPING_Groups->db->prepare('update :table_customers
                                                     set customers_group_id = 0
                                                     where customers_group_id = :customers_group_id
                                                   ');
      $Qreset->bindInt(':customers_group_id', (int)$group_id);
      $Qreset->execute();

      $CLICSHOPPING_Hooks->call('CustomersGroup', 'Delete');
    }

    $CLICSHOPPING_Groups->redirect('Groups');
  }
}
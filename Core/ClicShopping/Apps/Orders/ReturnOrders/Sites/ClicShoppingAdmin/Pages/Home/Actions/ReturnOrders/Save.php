<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\ClicShoppingAdmin\Pages\Home\Actions\ReturnOrders;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Save extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $return_id = isset($_POST['rId']) ? (int)HTML::sanitize($_POST['rId']) : 0;
    $return_status_id = isset($_POST['return_status']) ? (int)HTML::sanitize($_POST['return_status']) : 0;
    $notify = isset($_POST['notify']) ? (int)HTML::sanitize($_POST['notify']) : 0;
    $comment = HTML::sanitize($_POST['comment'] ?? '');

    $return_reason = isset($_POST['return_reason']) ? (int)HTML::sanitize($_POST['return_reason']) : 0;
    $return_action = isset($_POST['return_action']) ? (int)HTML::sanitize($_POST['return_action']) : 0;
    $return_reason_opened = isset($_POST['return_reason_opened']) ? (int)HTML::sanitize($_POST['return_reason_opened']) : 0;

    $sql_data_array = [
      'return_id' => $return_id,
      'return_status_id' => $return_status_id,
      'notify' => $notify,
      'comment' => $comment,
      'date_added' => 'now()',
      'admin_user_name' => AdministratorAdmin::getUserAdmin()
    ];

    $CLICSHOPPING_ReturnOrders->db->save('return_orders_history', $sql_data_array);

    $Qupdate = $CLICSHOPPING_ReturnOrders->db->prepare('update :table_return_orders
                                                          set return_reason_id  = :return_reason,
                                                          return_action_id = :return_action,
                                                          opened = :return_reason_opened,    
                                                          date_modified = now()
                                                          where return_id = :return_id
                                                         ');
    $Qupdate->bindInt(':return_id', $return_id);
    $Qupdate->bindInt(':return_reason', $return_reason);
    $Qupdate->bindInt(':return_action', $return_action);
    $Qupdate->bindInt(':return_reason_opened', $return_reason_opened);
    $Qupdate->execute();

    $CLICSHOPPING_Hooks->call('ReturnOrders', 'Save');

    $CLICSHOPPING_ReturnOrders->redirect('ReturnOrders&' . (isset($_GET['page']) ? 'page=' . (int)$_GET['page'] . '' : ''));
  }
}
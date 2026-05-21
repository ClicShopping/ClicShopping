<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */


namespace ClicShopping\Apps\Customers\Customers\Sites\ClicShoppingAdmin\Pages\Home\Actions\Customers;

use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customers = Registry::get('Customers');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_POST['selected']) && isset($_GET['DeleteAll'])) {
      foreach ($_POST['selected'] as $id) {
        $CLICSHOPPING_Customers->db->delete('address_book', ['customers_id' => $id]);
        $CLICSHOPPING_Customers->db->delete('customers', ['customers_id' => $id]);
        $CLICSHOPPING_Customers->db->delete('customers_info', ['customers_info_id' => $id]);
        $CLICSHOPPING_Customers->db->delete('customers_basket', ['customers_id' => $id]);
        $CLICSHOPPING_Customers->db->delete('customers_basket_attributes', ['customers_id' => $id]);
      }

      $CLICSHOPPING_Hooks->call('Customers', 'DeleteCustomers');
    }

    $CLICSHOPPING_Customers->redirect('Customers', 'page=' . $page);
  }
}
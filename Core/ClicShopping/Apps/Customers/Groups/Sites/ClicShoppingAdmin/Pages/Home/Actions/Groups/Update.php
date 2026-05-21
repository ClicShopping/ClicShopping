<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */


namespace ClicShopping\Apps\Customers\Groups\Sites\ClicShoppingAdmin\Pages\Home\Actions\Groups;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Groups = Registry::get('Groups');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (isset($_POST['customer_group_id'])) {
      $customers_groups_id = HTML::sanitize($_POST['customer_group_id']);
      }

      if (isset($_POST['customers_group_name'])) {
        $customers_groups_name = HTML::sanitize($_POST['customers_group_name']);
      }

      if (isset($_POST['customers_group_discount'])) {
        $customers_groups_discount = HTML::sanitize($_POST['customers_group_discount']);
      }

      if (isset($_POST['color_bar'])) {
        $color_bar = HTML::sanitize($_POST['color_bar']);
      }

      if (isset($_POST['customers_group_quantity_default'])) {
        $customers_group_quantity_default = HTML::sanitize($_POST['customers_group_quantity_default']);
      }

      $group_payment_unallowed = '';
      $group_shipping_unallowed = '';

// Supprimer (|| $customers_group_discount ==  0) dans la condition IF pour pouvoir cree un groupe a 0% par defaut
      if (empty($customers_groups_name)) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Groups->getDef('entry_groups_name_error'), 'error');
        $CLICSHOPPING_Groups->redirect('Edit&cID=' . $customers_groups_id);

      } else {
        if (empty($customers_groups_discount)) {
// valeur discount mis a zero si le champs est vide
          $customers_groups_discount = '0.00';
        }

// Module de paiement autorise
        if (isset($_POST['payment_unallowed'])) {
          $group_payment_unallowed = '';

          foreach ($_POST['payment_unallowed'] as $key => $val) {
            if (isset($val)) {
              $group_payment_unallowed .= $val . ',';
            }
          }

          $group_payment_unallowed = substr($group_payment_unallowed, 0, \strlen($group_payment_unallowed) - 1);
        }

// Module de livraison autorise
        if (isset($_POST['shipping_unallowed'])) {
          $group_shipping_unallowed = '';

          foreach ($_POST['shipping_unallowed'] as $key => $val) {
            if (isset($val)) {
              $group_shipping_unallowed .= $val . ',';
            }
          }

          $group_shipping_unallowed = substr($group_shipping_unallowed, 0, \strlen($group_shipping_unallowed) - 1);
        }

// Assujetti ou non a la TVA
        $group_order_taxe = (int)HTML::sanitize($_POST['group_order_taxe']);

// Affichage des prix en HT ou en TTC (Mis automatiquement en false "HT" si l'on coche non-assujetti a la TVA)
        if ($group_order_taxe == 1) {
          $group_tax = 'false';
        } else {
          $group_tax = HTML::sanitize($_POST['group_tax']);
        }

        $Qupdate = $CLICSHOPPING_Groups->db->prepare('update :table_customers_groups
                                                      set customers_group_name = :customers_group_name,
                                                          customers_group_discount = :customers_group_discount,
                                                          color_bar = :color_bar,
                                                          group_order_taxe = :group_order_taxe,
                                                          group_payment_unallowed = :group_payment_unallowed,
                                                          group_shipping_unallowed = :group_shipping_unallowed,
                                                          group_tax = :group_tax,
                                                          customers_group_quantity_default = :customers_group_quantity_default
                                                      where customers_group_id = :customers_group_id
                                                    ');
        $Qupdate->bindValue(':customers_group_name', $customers_groups_name);
        $Qupdate->bindDecimal(':customers_group_discount', $customers_groups_discount);
        $Qupdate->bindValue(':color_bar', $color_bar);
        $Qupdate->bindInt(':group_order_taxe', $group_order_taxe);
        $Qupdate->bindValue(':group_payment_unallowed', $group_payment_unallowed);
        $Qupdate->bindValue(':group_shipping_unallowed', $group_shipping_unallowed);
        $Qupdate->bindValue(':group_tax', $group_tax);
        $Qupdate->bindInt(':customers_group_quantity_default', (int)$customers_group_quantity_default);
        $Qupdate->bindInt(':customers_group_id', (int)$customers_groups_id);
        $Qupdate->execute();

        $CLICSHOPPING_Hooks->call('CustomersGroup', 'Update');

        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Groups->getDef('entry_groups_name_success'), 'success');
        $CLICSHOPPING_Groups->redirect('Groups');
      }
    }
  }
}
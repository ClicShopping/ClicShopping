<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Orders\Orders\Orders as OrdersApp;

class Orders extends \ClicShopping\OM\Modules\AdminDashboardAbstract
{
  public mixed $lang;
  public mixed $app;
  public $group;

  protected function init()
  {
    if (!Registry::exists('Orders')) {
      Registry::set('Orders', new OrdersApp());
    }

    $this->app = Registry::get('Orders');
    $this->lang = Registry::get('Language');

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/orders');

    $this->title = $this->app->getDef('module_admin_dashboard_orders_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_orders_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_ORDERS_APP_STATUS')) {
      $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_ORDERS_APP_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_ORDERS_APP_STATUS == 'True');
    }
  }

  public function getOutput()
  {
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $Qorder = $this->app->db->prepare('select o.orders_id,
                                                 o.customers_group_id,
                                                 o.customers_id,
                                                 o.customers_name,
                                                 greatest(o.date_purchased,
                                                 ifnull(o.last_modified, 0)) as date_last_modified,
                                                 s.orders_status_name,
                                                 s.language_id,
                                                 o.erp_invoice,
                                                 ot.text as order_total
                                          from :table_orders o,
                                               :table_orders_total ot,
                                               :table_orders_status s
                                          where o.orders_id = ot.orders_id
                                          and (ot.class = :class or ot.class = :class1)
                                          and o.orders_status = s.orders_status_id
                                          and (o.orders_status <> 3 and o.orders_status <> 4)
                                          and  s.language_id = :language_id
                                          order by date_last_modified desc
                                          limit :limit
                                         ');
    $Qorder->bindValue(':class', 'ot_total');
    $Qorder->bindValue(':class1', 'TO');
    $Qorder->bindInt(':language_id', (int)$this->lang->getId());
    $Qorder->bindInt(':limit', MODULE_ADMIN_DASHBOARD_ORDERS_APP_LIMIT);
    $Qorder->execute();

    $content_width = 'col-md-' . (int)MODULE_ADMIN_DASHBOARD_ORDERS_APP_CONTENT_WIDTH;

    $output = '<div class="' . $content_width . '">';
    $output .= '<div class="mt-1"></div>';
    $output .= '<table
        id="table"
        data-toggle="table"
        data-icons-prefix="bi"
        data-icons="icons"
        data-sort-name="date"
        data-sort-order="asc"
        data-toolbar="#toolbar"
        data-buttons-class="primary"
        data-show-toggle="true"
        data-show-columns="true"
        data-mobile-responsive="true"
        data-check-on-init="true">';

    $output .= '<thead class="dataTableHeadingRow">';
    $output .= '<tr>';
    $output .= '<th data-field="date">' . $this->app->getDef('module_admin_dashboard_orders_app_date') . '</th>';
    $output .= '<th data-field="order" class="text-center">' . $this->app->getDef('module_admin_dashboard_orders_app_order') . '</th>';
    $output .= '<th data-field="language" class="text-center">' . $this->app->getDef('module_admin_dashboard_orders_app_language') . '</th>';
    $output .= '<th data-field="total" class="text-center">' . $this->app->getDef('module_admin_dashboard_orders_app_total') . '</th>';
    $output .= '<th data-field="erp" class="text-center">' . $this->app->getDef('module_admin_dashboard_orders_app_erp_status') . '</th>';
    $output .= '<th data-field="status" class="text-center">' . $this->app->getDef('module_admin_dashboard_orders_app_order_status') . '</th>';
    $output .= '<th data-field="action" data-switchable="false" class="text-center">' . $this->app->getDef('module_admin_dashboard_orders_app_order_action') . '</th>';
    $output .= '</tr>';
    $output .= '</thead>';
    $output .= '<tbody>';

    while ($orders = $Qorder->fetch()) {
      $output .= '  <tr class="dataTableRow backgroundBlank">' .
        '    <th scope="row">' . DateTime::toShort($orders['date_last_modified']) . '</th>' .
        '    <td>' . HTML::link(CLICSHOPPING::link(null, 'A&Customers\Customers&Edit&cID=' . (int)$orders['customers_id']), HTML::outputProtected(Hash::displayDecryptedDataText($orders['customers_name']))) . '</a></td>' .
        '    <td>' . $this->lang->getLanguagesName($Qorder->valueInt('language_id')) . '</td>' .
        '    <td>' . strip_tags($orders['order_total']) . '</td>';

      if ($orders['erp_invoice'] == 1) {
        $output .= ' <td class="text-center">' . HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'icons/odoo_order.png', $this->app->getDef('image_orders_erp')) . '</td>';
      } elseif ($orders['erp_invoice'] == 2) {
        $output .= ' <td class="text-center">' . HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'icons/odoo_invoice.png', $this->app->getDef('image_orders_invoice_manual_erp')) . '</td>';
      } else {
        $output .= ' <td class="text-center"></td>';
      }
      $output .=
        '    <td>' . HTML::outputProtected($orders['orders_status_name']) . '</td>' .
        '    <td class="text-end">
          ' . HTML::link(CLICSHOPPING::link(null, 'A&Orders\Orders&Edit&oID=' . (int)$orders['orders_id']), '<h4><i class="bi bi-pencil" title="' . $this->app->getDef('module_admin_dashboard_orders_app_icon_edit_order') . '"></i></h4>');
      HTML::link(CLICSHOPPING::link(null, 'A&Customers\Customers&Edit&cID=' . (int)$orders['customers_id']), '<h4><i class="bi bi-person" title="' . $this->app->getDef('module_admin_dashboard_orders_app_icon_edit_customer') . '"></i></h4>');
      HTML::link(CLICSHOPPING::link(null, 'A&Orders\Orders&cID=' . $orders['customers_id']), '<h4><i class="bi bi-cart2" title="' . $this->app->getDef('module_admin_dashboard_orders_app_icon_view_customers_all_orders') . '"></i></h4>');

      $output .= '</td>';
      $output .= '</tr>';
    }

    $output .= '</tbody>';
    $output .= '</table>';
    $output .= '</div>';

    return $output;
  }

  public function Install()
  {

    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_ORDERS_APP_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to display the latest orders ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'How many orders do you want to display ?',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_ORDERS_APP_LIMIT',
        'configuration_value' => '10',
        'configuration_description' => 'Please specify the number of orders to display',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Select the width to display',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_ORDERS_APP_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 to 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Sort Order',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_ORDERS_APP_SORT_ORDER',
        'configuration_value' => '60',
        'configuration_description' => 'Sort order of display. Lowest is displayed first',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  public function keys()
  {
    return ['MODULE_ADMIN_DASHBOARD_ORDERS_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_ORDERS_APP_LIMIT',
      'MODULE_ADMIN_DASHBOARD_ORDERS_APP_CONTENT_WIDTH',
      'MODULE_ADMIN_DASHBOARD_ORDERS_APP_SORT_ORDER'
    ];
  }
}

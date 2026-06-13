<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Orders\Orders\Orders as OrdersApp;

class OrderMap extends \ClicShopping\OM\Modules\AdminDashboardAbstract
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

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/order_map');

    $this->title = $this->app->getDef('module_admin_dashboard_order_map_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_order_map_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_ORDER_MAP_APP_STATUS')) {
      $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_ORDER_MAP_APP_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_ORDER_MAP_APP_STATUS == 'True');
    }
  }

  public function getOutput()
  {
    $link = CLICSHOPPING::link('ajax/map.php');
    $text_sale_total_ht = $this->app->getDef('text_sale_total_ht');
    $text_order_number = $this->app->getDef('text_order_number');
    $text_orders_status = $this->app->getDef('text_orders_status');
    $text_order_delivery = $this->app->getDef('text_order_delivery');

    $content_width = 'col-md-' . (int)MODULE_ADMIN_DASHBOARD_ORDER_MAP_APP_CONTENT_WIDTH;

    $output = '<div class="col-12 ' . $content_width . ' d-flex" style="padding-right:0.5rem; padding-top:0.5rem; height:310px;">';
    $output .= '<div class="card flex-fill w-100">';
    $output .= '<div id="vmap"></div>';
    $output .= '<div class="mt-1"></div>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';

    $output .= '
<script>
$(document).ready(function() {
  $.ajax({
    url:  \'' . $link . '\',
    dataType: \'json\',
    success: function(json) {
      data = [];

      for (i in json) {
        data[i] = json[i][\'total\'];
      }

      $(\'#vmap\').vectorMap({
        map: \'world_en\',
        backgroundColor: \'#FFFFFF\',
        borderColor: \'#FFFFFF\',
        color: \'#9FD5F1\',
        hoverOpacity: 0.7,
        selectedColor: \'#666666\',
        enableZoom: true,
        showTooltip: true,
        values: data,
        normalizeFunction: \'polynomial\',
        onLabelShow: function(event, label, code) {
          if (json[code]) {
            label.html(\'<strong>\' + label.text() + \'</strong><br />\' + \'' . $text_order_number . ' \' + json[code][\'total\'] + \'<br />\' + \'' . $text_sale_total_ht . ' \' + json[code][\'amount\'] + \'<br />\' + \'' . $text_orders_status . ' \' + \'' . $text_order_delivery . '\');
          }
        },
        onResize: function (element, width, height) {
            console.log(\'Map Size: \' +  width + \'x\' +  height);
          }
      });
    },
        error: function(xhr, ajaxOptions, thrownError) {
            alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        }
  });
});
</script>
      ';

    return $output;
  }

  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_ORDER_MAP_APP_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to display the latest orders ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Select the width to display',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_ORDER_MAP_APP_CONTENT_WIDTH',
        'configuration_value' => '6',
        'configuration_description' => 'Select a number between 1 to 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Sort Order',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_ORDER_MAP_APP_SORT_ORDER',
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
    return ['MODULE_ADMIN_DASHBOARD_ORDER_MAP_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_ORDER_MAP_APP_CONTENT_WIDTH',
      'MODULE_ADMIN_DASHBOARD_ORDER_MAP_APP_SORT_ORDER'
    ];
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class bm_manufacturers
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;
  public $pages;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->title = CLICSHOPPING::getDef('module_boxes_manufacturers_title');
    $this->description = CLICSHOPPING::getDef('module_boxes_manufacturers_description');

    if (\defined('MODULE_BOXES_MANUFACTURERS_STATUS')) {
      $this->sort_order = defined('MODULE_BOXES_MANUFACTURERS_SORT_ORDER') ? (int)MODULE_BOXES_MANUFACTURERS_SORT_ORDER : 0;
      $this->enabled = defined('MODULE_BOXES_MANUFACTURERS_STATUS') && MODULE_BOXES_MANUFACTURERS_STATUS == 'True';
      $this->pages = defined('MODULE_BOXES_MANUFACTURERS_DISPLAY_PAGES') ? MODULE_BOXES_MANUFACTURERS_DISPLAY_PAGES : '';
      $this->group = (defined('MODULE_BOXES_MANUFACTURERS_CONTENT_PLACEMENT') && MODULE_BOXES_MANUFACTURERS_CONTENT_PLACEMENT == 'Left Column') ? 'boxes_column_left' : 'boxes_column_right';
    }
  }

  public function getData()
  {
    $CLICSHOPPING_Manufacturers = Registry::get('Manufacturers');

    $data = '';

    $manufacturers = $CLICSHOPPING_Manufacturers->getAll();

    if (!empty($manufacturers)) {
// Display a list
      if (defined('MODULE_BOXES_MANUFACTURERS_MANUFACTURERS_LIST') && MODULE_BOXES_MANUFACTURERS_MANUFACTURERS_LIST == 'list') {
        if (\count($manufacturers) <= (defined('MODULE_BOXES_MANUFACTURERS_MAX_MANUFACTURERS_LIST') ? (int)MODULE_BOXES_MANUFACTURERS_MAX_MANUFACTURERS_LIST : 0)) {
          $manufacturers_list = '<ul style="list-style: none; margin: 0; padding: 0;">';

          foreach ($manufacturers as $m) {
            $manufacturer_url = $CLICSHOPPING_Manufacturers->getManufacturerUrlRewrited()->getManufacturerUrl((int)$m['id']);

            $manufacturers_name = ((\strlen($m['name']) > (defined('MODULE_BOXES_MANUFACTURERS_MAX_DISPLAY_MANUFACTURER_NAME_LEN') ? (int)MODULE_BOXES_MANUFACTURERS_MAX_DISPLAY_MANUFACTURER_NAME_LEN : 0)) ? substr($m['name'], 0, defined('MAX_DISPLAY_MANUFACTURER_NAME_LEN') ? MAX_DISPLAY_MANUFACTURER_NAME_LEN : 0) . '..' : $m['name']);
            if (isset($_GET['manufacturersId']) && ($_GET['manufacturersId'] == $m['id'])) {
              $manufacturers_name = '<strong>' . $manufacturers_name . '</strong>';
            }

            $manufacturers_list .= '<li>' . HTML::link($manufacturer_url, $manufacturers_name) . '</li>';
          }

          $manufacturers_list .= '</ul>';

          $data = $manufacturers_list;
        }
      } else {
// Display a drop-down
        $manufacturers_array = [];

        if (\count($manufacturers) < 2) {
          $manufacturers_array[] = ['id' => '',
            'text' => CLICSHOPPING::getDef('pull_down_default')
          ];
        }

        foreach ($manufacturers as $m) {
          $manufacturers_name = ((\strlen($m['name']) > (defined('MODULE_BOXES_MANUFACTURERS_MAX_DISPLAY_MANUFACTURER_NAME_LEN') ? (int)MODULE_BOXES_MANUFACTURERS_MAX_DISPLAY_MANUFACTURER_NAME_LEN : 0)) ? substr($m['name'], 0, defined('MAX_DISPLAY_MANFACTURER_NAME_LEN') ? MAX_DISPLAY_MANFACTURER_NAME_LEN : 0) . '..' : $m['name']);

          $manufacturers_array[] = ['id' => $m['id'],
            'text' => $manufacturers_name
          ];
        }

        $data = HTML::form('manufacturers', CLICSHOPPING::link(), 'get', null, ['session_id' => true]);
        $data .= '<label for="manufacturerDropDown" class="visually-hidden"></label>';
        $data .= HTML::selectField('manufacturersId', $manufacturers_array, (isset($_GET['manufacturersId']) ? $_GET['manufacturersId'] : ''), 'onchange="this.form.submit();" id="manufacturerDropDown" class="boxePullDownManufacturer" size="' . (defined('MODULE_BOXES_MANUFACTURERS_MANUFACTURERS_LIST') ? MODULE_BOXES_MANUFACTURERS_MANUFACTURERS_LIST : '') . '"');
        $data .= '</form>';
        $data .= '<div class="mt-1"></div>';
      }
    }

    return $data;
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Service = Registry::get('Service');
    $CLICSHOPPING_Banner = Registry::get('Banner');

    $manufacturer_banner = '';

    if ($CLICSHOPPING_Service->isStarted('Banner')) {
      if ($banner = $CLICSHOPPING_Banner->bannerExists('dynamic', SITE_THEMA . '_manufacturer')) {
        $manufacturer_banner = $CLICSHOPPING_Banner->displayBanner('static', $banner) . '<br /><br />';
      }
    }

    $output = $this->getData();

    if (!empty($output)) {
      ob_start();
      require($CLICSHOPPING_Template->getTemplateModules('/modules_boxes/content/manufacturers'));
      $data = ob_get_clean();

      $CLICSHOPPING_Template->addBlock($data, $this->group);
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_BOXES_MANUFACTURERS_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_BOXES_MANUFACTURERS_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please choose where the boxe must be displayed',
        'configuration_key' => 'MODULE_BOXES_MANUFACTURERS_CONTENT_PLACEMENT',
        'configuration_value' => 'Right Column',
        'configuration_description' => 'Choose where the boxe must be displayed',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'Left Column\', \'Right Column\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the number of brands to display',
        'configuration_key' => 'MODULE_BOXES_MANUFACTURERS_MAX_MANUFACTURERS_LIST',
        'configuration_value' => '5',
        'configuration_description' => 'Indicate the number of brand to display.',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'How do you want to display the brands ?',
        'configuration_key' => 'MODULE_BOXES_MANUFACTURERS_MANUFACTURERS_LIST',
        'configuration_value' => 'dropdown',
        'configuration_description' => 'Do yo want display a list or a dropdown',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'dropdown\', \'list\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the width of brand words',
        'configuration_key' => 'MODULE_BOXES_MANUFACTURERS_MAX_DISPLAY_MANUFACTURER_NAME_LEN',
        'configuration_value' => '30',
        'configuration_description' => 'Width of brand words.',
        'configuration_group_id' => '6',
        'sort_order' => '5',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_BOXES_MANUFACTURERS_SORT_ORDER',
        'configuration_value' => '120',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '6',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Indicate the page where the module is displayed',
        'configuration_key' => 'MODULE_BOXES_MANUFACTURERS_DISPLAY_PAGES',
        'configuration_value' => 'all',
        'configuration_description' => 'Select the pages where the boxe must be present.',
        'configuration_group_id' => '6',
        'sort_order' => '7',
        'set_function' => 'clic_cfg_set_select_pages_list',
        'date_added' => 'now()'
      ]
    );
  }

  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  public function keys()
  {
    return array('MODULE_BOXES_MANUFACTURERS_STATUS',
      'MODULE_BOXES_MANUFACTURERS_CONTENT_PLACEMENT',
      'MODULE_BOXES_MANUFACTURERS_MAX_MANUFACTURERS_LIST',
      'MODULE_BOXES_MANUFACTURERS_MANUFACTURERS_LIST',
      'MODULE_BOXES_MANUFACTURERS_MAX_DISPLAY_MANUFACTURER_NAME_LEN',
      'MODULE_BOXES_MANUFACTURERS_SORT_ORDER',
      'MODULE_BOXES_MANUFACTURERS_DISPLAY_PAGES');
  }
}

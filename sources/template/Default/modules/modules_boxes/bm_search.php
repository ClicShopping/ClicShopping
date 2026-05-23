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

class bm_search
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

    $this->title = CLICSHOPPING::getDef('module_boxes_search_title');
    $this->description = CLICSHOPPING::getDef('module_boxes_search_description');

    if (\defined('MODULE_BOXES_SEARCH_STATUS')) {
      $this->sort_order = \defined('MODULE_BOXES_SEARCH_SORT_ORDER') ? (int)MODULE_BOXES_SEARCH_SORT_ORDER : 0;
      $this->enabled = (\defined('MODULE_BOXES_SEARCH_STATUS') && MODULE_BOXES_SEARCH_STATUS == 'True');
      $this->pages = \defined('MODULE_BOXES_SEARCH_DISPLAY_PAGES') ? MODULE_BOXES_SEARCH_DISPLAY_PAGES : '';
      $this->group = ((\defined('MODULE_BOXES_SEARCH_CONTENT_PLACEMENT') && MODULE_BOXES_SEARCH_CONTENT_PLACEMENT == 'Left Column') ? 'boxes_column_left' : 'boxes_column_right');
    }
  }

  public function execute()
  {

    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Service = Registry::get('Service');
    $CLICSHOPPING_Banner = Registry::get('Banner');

    $search_banner = '';

    if ($CLICSHOPPING_Service->isStarted('Banner')) {
      if ($banner = $CLICSHOPPING_Banner->bannerExists('dynamic', \defined('MODULE_BOXES_SEARCH_BANNER_GROUP') ? MODULE_BOXES_SEARCH_BANNER_GROUP : '')) {
        $search_banner = $CLICSHOPPING_Banner->displayBanner('static', $banner) . '<br /><br />';
      } else {
        $search_banner = '';
      }
    }

    $data = '<!-- Boxe Search start -->' . "\n";

    $output = HTML::form('quick_find', CLICSHOPPING::link(null, 'Search&Q'), 'post', 'id="quick_find"', ['session_id' => true]);
    $output .= '<div class="input-group">';
    $output .= '<label for="inpuBoxeSearch" class="visually-hidden">' . CLICSHOPPING::getDef('module_boxes_search_box_title') . '</label>';
    $output .= HTML::inputField('keywords', '', 'aria-required="true" placeholder="' . CLICSHOPPING::getDef('module_boxes_search_box_title') . '"', 'search');
    $output .= '<span class="input-group-btn"><button type="submit" class="btn btn-search btn-sm"><i class="bi bi-search"></i></button></span>';
    $output .= '</div>';
    $output .= HTML::hiddenField('search_in_description', '1');
    $output .= '</form>';


    ob_start();
    require_once($CLICSHOPPING_Template->getTemplateModules('/modules_boxes/content/search'));
    $data .= ob_get_clean();

    $data .= '<!-- Boxe Search end -->' . "\n";

    $CLICSHOPPING_Template->addBlock($data, $this->group);
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_BOXES_SEARCH_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_BOXES_SEARCH_STATUS',
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
        'configuration_key' => 'MODULE_BOXES_SEARCH_CONTENT_PLACEMENT',
        'configuration_value' => 'Right Column',
        'configuration_description' => 'Choose where the boxe must be displayed',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'Left Column\', \'Right Column\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the banner group for the image',
        'configuration_key' => 'MODULE_BOXES_SEARCH_BANNER_GROUP',
        'configuration_value' => SITE_THEMA . '_boxe_search',
        'configuration_description' => 'Indicate the banner group<br /><br /><strong>Note :</strong><br /><i>The group must be created or selected whtn you create a banner in Marketing / banner</i>',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_BOXES_SEARCH_SORT_ORDER',
        'configuration_value' => '120',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Indicate the page where the module is displayed',
        'configuration_key' => 'MODULE_BOXES_SEARCH_DISPLAY_PAGES',
        'configuration_value' => 'all',
        'configuration_description' => 'Select the pages where the boxe must be present.',
        'configuration_group_id' => '6',
        'sort_order' => '5',
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
    return array('MODULE_BOXES_SEARCH_STATUS',
      'MODULE_BOXES_SEARCH_CONTENT_PLACEMENT',
      'MODULE_BOXES_SEARCH_BANNER_GROUP',
      'MODULE_BOXES_SEARCH_SORT_ORDER',
      'MODULE_BOXES_SEARCH_DISPLAY_PAGES'
    );
  }
}

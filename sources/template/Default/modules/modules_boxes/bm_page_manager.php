<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class bm_page_manager
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

    $this->title = CLICSHOPPING::getDef('module_boxes_page_manager_title');
    $this->description = CLICSHOPPING::getDef('module_boxes_page_manager_description');

    if (\defined('MODULE_BOXES_PAGE_MANAGER_STATUS')) {
      $this->sort_order = \defined('MODULE_BOXES_PAGE_MANAGER_SORT_ORDER') ? (int)MODULE_BOXES_PAGE_MANAGER_SORT_ORDER : 0;
      $this->enabled = (\defined('MODULE_BOXES_PAGE_MANAGER_STATUS') && MODULE_BOXES_PAGE_MANAGER_STATUS == 'True');
      $this->pages = \defined('MODULE_BOXES_PAGE_MANAGER_DISPLAY_PAGES') ? MODULE_BOXES_PAGE_MANAGER_DISPLAY_PAGES : '';
      $this->group = (defined('MODULE_BOXES_PAGE_MANAGER_CONTENT_PLACEMENT') && MODULE_BOXES_PAGE_MANAGER_CONTENT_PLACEMENT == 'Left Column') ? 'boxes_column_left' : 'boxes_column_right';
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_PageManagerShop = Registry::get('PageManagerShop');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Service = Registry::get('Service');
    $CLICSHOPPING_Banner = Registry::get('Banner');

    $Qpages = $CLICSHOPPING_Db->prepare('select count(*) as count
                                          from :table_pages_manager
                                          where status = 1
                                          and page_box = 0
                                          and page_type = 4
                                          and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                         ');
    $Qpages->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());

    $Qpages->execute();

    if ($Qpages->valueInt('count') > 0) {
      $page_manager_banner = '';

      if ($CLICSHOPPING_Service->isStarted('Banner')) {
        if ($banner = $CLICSHOPPING_Banner->bannerExists('dynamic', \defined('MODULE_BOXES_PAGE_MANAGER_BANNER_GROUP') ? MODULE_BOXES_PAGE_MANAGER_BANNER_GROUP : '')) {
          $page_manager_banner = $CLICSHOPPING_Banner->displayBanner('static', $banner) . '<br /><br />';
        }
      }

      $data = '<!-- boxe page manager  start-->' . "\n";

      $link = $CLICSHOPPING_PageManagerShop->pageManagerDisplayBox();

      ob_start();
      require($CLICSHOPPING_Template->getTemplateModules('/modules_boxes/content/page_manager'));

      $data .= ob_get_clean();

      $data .= '<!-- Boxe page manager  main end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($data, $this->group);
    }
  }


  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_BOXES_PAGE_MANAGER_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_BOXES_PAGE_MANAGER_STATUS',
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
        'configuration_key' => 'MODULE_BOXES_PAGE_MANAGER_CONTENT_PLACEMENT',
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
        'configuration_key' => 'MODULE_BOXES_PAGE_MANAGER_BANNER_GROUP',
        'configuration_value' => SITE_THEMA . '_boxe_page_manager',
        'configuration_description' => 'Indicate the banner group<br /><br /><strong>Note :</strong><br /><i>The group must be created or selected whtn you create a banner in Marketing / banner</i>',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_BOXES_PAGE_MANAGER_SORT_ORDER',
        'configuration_value' => '100',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Indicate the page where the module is displayed',
        'configuration_key' => 'MODULE_BOXES_PAGE_MANAGER_DISPLAY_PAGES',
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
    return array('MODULE_BOXES_PAGE_MANAGER_STATUS',
      'MODULE_BOXES_PAGE_MANAGER_CONTENT_PLACEMENT',
      'MODULE_BOXES_PAGE_MANAGER_BANNER_GROUP',
      'MODULE_BOXES_PAGE_MANAGER_SORT_ORDER',
      'MODULE_BOXES_PAGE_MANAGER_DISPLAY_PAGES'
    );
  }
}

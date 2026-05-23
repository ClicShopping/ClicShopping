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

class bm_manufacturer_info
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
    $this->title = CLICSHOPPING::getDef('module_boxes_manufacturer_info_title');
    $this->description = CLICSHOPPING::getDef('module_boxes_manufacturer_info_description');

    if (\defined('MODULE_BOXES_MANUFACTURER_INFO_STATUS')) {
      $this->sort_order = defined('MODULE_BOXES_MANUFACTURER_INFO_SORT_ORDER') ? (int)MODULE_BOXES_MANUFACTURER_INFO_SORT_ORDER : 0;
      $this->enabled = (defined('MODULE_BOXES_MANUFACTURER_INFO_STATUS') && MODULE_BOXES_MANUFACTURER_INFO_STATUS == 'True');
      $this->group = (defined('MODULE_BOXES_MANUFACTURER_INFO_CONTENT_PLACEMENT') && MODULE_BOXES_MANUFACTURER_INFO_CONTENT_PLACEMENT == 'Left Column') ? 'boxes_column_left' : 'boxes_column_right';
    }
  }

  public function execute()
  {

    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Service = Registry::get('Service');
    $CLICSHOPPING_Banner = Registry::get('Banner');
    $CLICSHOPPING_Manufacturers = Registry::get('Manufacturers');

    if ($CLICSHOPPING_ProductsCommon->getId()) {
      $Qmanufacturers = $CLICSHOPPING_Db->prepare('select m.manufacturers_id,
                                                           m.manufacturers_name,
                                                           m.manufacturers_image,
                                                           mi.manufacturers_url,
                                                           m.manufacturers_status
                                                     from :table_manufacturers m left join :table_manufacturers_info mi on (m.manufacturers_id = mi.manufacturers_id and mi.languages_id = :languages_id),
                                                          :table_products p,
                                                          :table_products_to_categories p2c,
                                                          :table_categories c
                                                     where p.products_id = :products_id
                                                     and p.manufacturers_id = m.manufacturers_id
                                                     and m.manufacturers_status = 0
                                                     and p.products_id = p2c.products_id
                                                     and p2c.categories_id = c.categories_id
                                                     and c.status = 1
                                                     ');

      $Qmanufacturers->bindInt(':languages_id', $CLICSHOPPING_Language->getId());
      $Qmanufacturers->bindInt(':products_id', $CLICSHOPPING_ProductsCommon->getId());
      $Qmanufacturers->execute();

      if ($Qmanufacturers->fetch()) {
        $manufacturer_info_string = '';
        $manufacturer_infos_banner = '';

        $manufacturer_url = $CLICSHOPPING_Manufacturers->getManufacturerUrlRewrited()->getManufacturerUrl($Qmanufacturers->valueInt('manufacturers_id'));

        if (!empty($Qmanufacturers->value('manufacturers_image'))) {
          $manufacturer_info_string .= '<span class="col-md-12 text-center">' . HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $Qmanufacturers->value('manufacturers_image'), HTML::outputProtected($Qmanufacturers->value('manufacturers_name'))) . '</span>';
        }

        if (!empty($Qmanufacturers->value('manufacturers_url'))) {
          $manufacturer_info_string .= '<div class="col-md-12">-&nbsp;' . HTML::link(CLICSHOPPING::link('redirect.php', 'action=manufacturer&manufacturersId=' . $Qmanufacturers->valueInt('manufacturers_id'), true, false), sprintf(CLICSHOPPING::getDef('module_boxes_manufacturer_info_box_homepage'), $Qmanufacturers->value('manufacturers_name')), '" target="_blank" rel="noopener"') . '</div>';
        }

        $manufacturer_info_string .= '<div class="col-md-12">-&nbsp;' . HTML::link($manufacturer_url, CLICSHOPPING::getDef('module_boxes_manufacturer_info_box_other_products')) . '</div>';

        $manufacturer_infos_banner = '';

        if ($CLICSHOPPING_Service->isStarted('Banner')) {
          if ($banner = $CLICSHOPPING_Banner->bannerExists('dynamic', defined('MODULE_BOXES_MANUFACTURER_INFO_BANNER_GROUP') ? MODULE_BOXES_MANUFACTURER_INFO_BANNER_GROUP : '')) {
            $manufacturer_infos_banner = $CLICSHOPPING_Banner->displayBanner('static', $banner) . '<br /><br />';
          }
        }

        $data = '<!-- boxe manufacturer Info start-->' . "\n";

        ob_start();
        require_once($CLICSHOPPING_Template->getTemplateModules('/modules_boxes/content/manufacturer_info'));

        $data .= ob_get_clean();

        $data .= '<!-- Boxe manufacturer Info end -->' . "\n";

        $CLICSHOPPING_Template->addBlock($data, $this->group);
      }
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_BOXES_MANUFACTURER_INFO_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_BOXES_MANUFACTURER_INFO_STATUS',
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
        'configuration_key' => 'MODULE_BOXES_MANUFACTURER_INFO_CONTENT_PLACEMENT',
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
        'configuration_key' => 'MODULE_BOXES_MANUFACTURER_INFO_BANNER_GROUP',
        'configuration_value' => SITE_THEMA . '_boxe_manufacturers_info',
        'configuration_description' => 'Indicate the banner group<br /><br /><strong>Note :</strong><br /><i>The group must be created or selected whtn you create a banner in Marketing / banner</i>',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_BOXES_MANUFACTURER_INFO_SORT_ORDER',
        'configuration_value' => '120',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
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
    return array('MODULE_BOXES_MANUFACTURER_INFO_STATUS',
      'MODULE_BOXES_MANUFACTURER_INFO_CONTENT_PLACEMENT',
      'MODULE_BOXES_MANUFACTURER_INFO_BANNER_GROUP',
      'MODULE_BOXES_MANUFACTURER_INFO_SORT_ORDER'
    );
  }
}

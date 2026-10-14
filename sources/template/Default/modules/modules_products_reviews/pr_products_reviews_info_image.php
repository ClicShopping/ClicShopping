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

class pr_products_reviews_info_image
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('modules_products_reviews_info_image_title');
    $this->description = CLICSHOPPING::getDef('modules_products_reviews_info_image_description');


    if (\defined('MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_STATUS')) {
      $this->sort_order = \defined('MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_SORT_ORDER') ? (int)MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_SORT_ORDER : 0;
      $this->enabled = \defined('MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_STATUS') ? (MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    $content_width = \defined('MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_CONTENT_WIDTH') ? (int)MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_CONTENT_WIDTH : 0;
    $text_position = \defined('MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_CONTENT_POSITION') ? MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_CONTENT_POSITION : 'float-none';

    if (isset($_GET['Products'], $_GET['ReviewsInfo'])) {
      $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
      $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');

      $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($CLICSHOPPING_ProductsCommon->getID());

      $reviews_image = HTML::link($products_name_url, HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $CLICSHOPPING_ProductsCommon->getProductsImage()));
      $products_name = HTML::link($products_name_url, $CLICSHOPPING_ProductsCommon->getProductsName());
      $products_price = $CLICSHOPPING_ProductsCommon->getCustomersPrice();

      $data = '<!-- pr_products_reviews_info_image start -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_reviews_info_image'));

      $data .= ob_get_clean();

      $data .= '<!-- pr_products_reviews_info_image end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($data, $this->group);
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the module',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_CONTENT_WIDTH',
        'configuration_value' => '4',
        'configuration_description' => 'Select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Where Do you want to display the module ?',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_CONTENT_POSITION',
        'configuration_value' => 'float-end',
        'configuration_description' => 'Select where you want display the module',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\', \'float-none\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_SORT_ORDER',
        'configuration_value' => '40',
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
    return array('MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_STATUS',
      'MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_CONTENT_WIDTH',
      'MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_CONTENT_POSITION',
      'MODULES_PRODUCTS_REVIEWS_INFO_IMAGE_SORT_ORDER'
    );
  }
}

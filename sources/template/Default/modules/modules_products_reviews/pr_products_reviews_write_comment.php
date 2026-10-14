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

class pr_products_reviews_write_comment
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

    $this->title = CLICSHOPPING::getDef('modules_products_reviews_write_comment_title');
    $this->description = CLICSHOPPING::getDef('modules_products_reviews_write_comment_description');

    if (\defined('MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_STATUS')) {
      $this->sort_order = \defined('MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_SORT_ORDER') ? (int)MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_SORT_ORDER : 0;
      $this->enabled = \defined('MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_STATUS') ? (MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $content_width = \defined('MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_CONTENT_WIDTH') ? (int)MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_CONTENT_WIDTH : 0;
    $text_position = \defined('MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_POSITION') ? MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_POSITION : 'float-none';

    if (isset($_GET['Products'], $_GET['ReviewsWrite']) && !isset($_GET['Success'])) {
      $customer_name = HTML::outputProtected($CLICSHOPPING_Customer->getFirstName() . ' ' . $CLICSHOPPING_Customer->getLastName());
      $customer_group_id = $CLICSHOPPING_Customer->getCustomersGroupID();
      $comment = HTML::textAreaField('review', null, 80, 10, 'required aria-required="true" id="productsReview"');
      $min_caracters_to_write = \defined('REVIEW_TEXT_MIN_LENGTH') ? (int)REVIEW_TEXT_MIN_LENGTH : 0;

      $data = '<!-- pr_products_reviews_write_comment start -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_reviews_write_comment'));

      $data .= ob_get_clean();

      $data .= '<!-- pr_products_reviews_write_comment end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($data, $this->group);
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_STATUS',
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
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_POSITION',
        'configuration_value' => 'float-start',
        'configuration_description' => 'Select where you want display the module',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\', \'float-none\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_SORT_ORDER',
        'configuration_value' => '00',
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
    return array('MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_STATUS',
      'MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_CONTENT_WIDTH',
      'MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_POSITION',
      'MODULES_PRODUCTS_REVIEWS_WRITE_COMMENT_SORT_ORDER'
    );
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\Apps\Customers\Reviews\Classes\Shop\Reviews;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
  use ClicShopping\OM\Hash;
  use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class pr_products_reviews_info_content
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

    $this->title = CLICSHOPPING::getDef('modules_products_reviews_info_content_title');
    $this->description = CLICSHOPPING::getDef('modules_products_reviews_info_content_description');

    if (\defined('MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_STATUS')) {
      $this->sort_order = \defined('MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_SORT_ORDER') ? (int)MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_SORT_ORDER : 0;
      $this->enabled = \defined('MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_STATUS') ? (MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Reviews = Registry::get('Reviews');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (isset($_GET['Products'], $_GET['ReviewsInfo'], $_GET['reviews_id'])) {
      $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
      $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');

      $content_width = \defined('MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_WIDTH') ? (int)MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_WIDTH : 0;
      $reviews_id = HTML::sanitize($_GET['reviews_id']);
      $reviews = $CLICSHOPPING_Reviews->getDataReviews($reviews_id);

      $delete_comment = \defined('MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_DELETE_COMMENT') ? MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_DELETE_COMMENT : 'False';
      $text_postion = \defined('MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_POSITION') ? MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_POSITION : 'float-none';

      $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($CLICSHOPPING_ProductsCommon->getID());

      $products_name = HTML::link($products_name_url, $CLICSHOPPING_ProductsCommon->getProductsName());

      $delete_reviews = '';


      if ($reviews !== false) {
        $reviews_text = $reviews['reviews_text'];
        $reviews_rating = $reviews['reviews_rating'];

        if ($reviews['customers_id'] == $CLICSHOPPING_Customer->getID()) {
          $delete_reviews .= HTML::form('reviews', CLICSHOPPING::link(null, 'Products&ReviewsInfo&Delete&products_id=' . $CLICSHOPPING_ProductsCommon->getID() . '&reviews_id=' . $reviews_id), 'post', 'id="Reviews"', ['tokenize' => true, 'action' => 'process']);
          $delete_reviews .= HTML::button(null, 'bi bi-trash2-fill', null, 'danger', null, 'md');
          $delete_reviews .= '</form>';
        }

        $customer_name = '<h5>*** ' . HTML::outputProtected(substr(HASH::displayDecryptedDataText($reviews['customers_name']), 4, -4)) . ' ***</h5>';
        $date_added = DateTime::toLong($reviews['date_added']);
        $customer_text = HTML::breakString(nl2br(HTML::outputProtected($reviews_text)), 60, '-<br />');
        $customer_rating = '<span class="productsInfoReviewsContentRating" itemprop="ratingValue">' . HTML::stars($reviews_rating) . '</span>';

        if (\defined('MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_SENTIMENT_TAG') && MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_SENTIMENT_TAG == 'True') {
          $customer_tag = $reviews['customers_tag'];
          $customer_tag = explode(',', $customer_tag);
        }

        $data = '<!-- pr_products_reviews_info_content start -->' . "\n";

        ob_start();
        require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_reviews_info_content'));

        $data .= ob_get_clean();

        $data .= '<!-- pr_products_reviews_info_content end -->' . "\n";
      } else {
        $data = '<div class="alert alert-info" role="alert">' . CLICSHOPPING::getDef('modules_products_reviews_info_content_text_no_review') . '</div>';
      }

      $CLICSHOPPING_Template->addBlock($data, $this->group);
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_STATUS',
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
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Where Do you want to display the module ?',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_POSITION',
        'configuration_value' => 'float-start',
        'configuration_description' => 'Select where you want display the module',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\', \'float-none\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Allow the customer to delete the comment ?',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_DELETE_COMMENT',
        'configuration_value' => 'True',
        'configuration_description' => 'The regulation allow the customer to decide to have access at his information',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );


    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the customer sentiment tage ?',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_SENTIMENT_TAG',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this option in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_SORT_ORDER',
        'configuration_value' => '30',
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
    return array('MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_STATUS',
      'MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_WIDTH',
      'MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_POSITION',
      'MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_DELETE_COMMENT',
      'MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_SENTIMENT_TAG',
      'MODULES_PRODUCTS_REVIEWS_INFO_CONTENT_SORT_ORDER'
    );
  }
}

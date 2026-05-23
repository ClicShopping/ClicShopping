<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
  use ClicShopping\OM\Hash;
  use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class pr_products_reviews_listing_content
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

    $this->title = CLICSHOPPING::getDef('modules_products_reviews_listing_content_title');
    $this->description = CLICSHOPPING::getDef('modules_products_reviews_listing_content_description');

    if (\defined('MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_STATUS')) {
      $this->sort_order = \defined('MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_SORT_ORDER') ? (int)MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_SORT_ORDER : 0;
      $this->enabled = \defined('MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_STATUS') ? (MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Reviews = Registry::get('Reviews');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (isset($_GET['Products'], $_GET['Reviews'])) {
      $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

      $Qreviews = $CLICSHOPPING_Reviews->getData();

      $content_width = \defined('MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_WIDTH') ? (int)MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_WIDTH : 0;
      $text_position = \defined('MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_POSITION') ? MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_POSITION : 'float-none';

      $data = '<!-- pr_products_reviews_listing_content start -->' . "\n";
      $data .= '<div class="col-md-' . $content_width . '">';
      $data .= '<div class="contentText">';
      $data .= '<div class="mt-1"></div>';
      $data .= '<div class="page-title"><h3>' . CLICSHOPPING::getDef('modules_products_reviews_listing_content_comment') . '</h3></div>';

      if ($CLICSHOPPING_Reviews->getPageSetTotalRows() > 0) {
        if ((PREV_NEXT_BAR_LOCATION == '1') || (PREV_NEXT_BAR_LOCATION == '3')) {
          $data .= '<div class="col-md-12">';
          $data .= '<div class="col-md-6 pagenumber hidden-xs">';
          $data .= $Qreviews->getPageSetLabel(CLICSHOPPING::getDef('text_display_number_of_items'));
          $data .= '</div>';
          $data .= '<div class="col-md-6">';
          $data .= '<div class="float-end pagenav">' . $Qreviews->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info')), 'Shop') . '</div>';
          $data .= '<span class="float-end">' . CLICSHOPPING::getDef('modules_products_reviews_listing_content_text_result_page') . '</span>';
          $data .= '</div>';
          $data .= '</div>';
          $data .= '<div class="clearfix"></div>';
          $data .= '<div class="mt-1"></div>';
        }
      }

      $data .= '<div class="d-flex flex-wrap ">';
      $delete_reviews = '';
      $delete_comment = \defined('MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_DELETE_COMMENT') ? MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_DELETE_COMMENT : 'False';

      while ($Qreviews->fetch()) {
        $date_reviews = CLICSHOPPING::getDef('text_review_date_added') . ' ' . DateTime::toLong($Qreviews->value('date_added'));
        $customer_name = CLICSHOPPING::getDef('text_review_by', ['customer_name' => '*** ' . HTML::outputProtected(substr(HASH::displayDecryptedDataText($Qreviews->value('customers_name')), 4, -4)) . ' ***']);
        $customer_review = '<a href="' . CLICSHOPPING::link(null, 'Products&ReviewsInfo&products_id=' . $CLICSHOPPING_ProductsCommon->getID() . '&reviews_id=' . $Qreviews->valueInt('reviews_id')) . '">' . $customer_name . '</a>';
        $delete_reviews = '';

        if ($Qreviews->valueInt('customers_id') == $CLICSHOPPING_Customer->getID()) {
          $delete_reviews .= HTML::form('reviews', CLICSHOPPING::link(null, 'Products&Reviews&Delete&products_id=' . $CLICSHOPPING_ProductsCommon->getID() . '&reviews_id=' . $Qreviews->valueInt('reviews_id')), 'post', 'id="Reviews"', ['tokenize' => true, 'action' => 'process']);
          $delete_reviews .= HTML::button(null, 'bi bi-trash2-fill', null, 'danger', null, 'md');
          $delete_reviews .= '</form>';
        }

        $review_text = HTML::breakString(HTML::outputProtected($Qreviews->value('reviews_text')), 60, '-<br />') . ((\strlen($Qreviews->value('reviews_text')) >= 100) ? '..' : '');
        $review_star = HTML::stars($Qreviews->valueInt('reviews_rating'));

        if (\defined('MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_SENTIMENT_TAG') && MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_SENTIMENT_TAG == 'True') {
          $customer_tag = $Qreviews->value('customers_tag');
          $customer_tag = explode(',', $customer_tag);
        }

        ob_start();
        require($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_reviews_listing_content'));

        $data .= ob_get_clean();
      }

      if ($Qreviews->getPageSetTotalRows() > 0) {
        if ((PREV_NEXT_BAR_LOCATION == '2') || (PREV_NEXT_BAR_LOCATION == '3')) {
          $data .= '<div class="col-md-12">';
          $data .= '<div class="col-md-6 pagenumber hidden-xs">';
          $data .= $Qreviews->getPageSetLabel(CLICSHOPPING::getDef('text_display_number_of_items'));
          $data .= '</div>';
          $data .= '<div class="col-md-6">';
          $data .= '<div class="float-end pagenav"><ul class="pagination">' . $Qreviews->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info')), 'Shop') . '</ul></div>';
          $data .= '</div>';
          $data .= '<div class="col-md-6">';
          $data .= '<span class="float-end">' . CLICSHOPPING::getDef('modules_products_reviews_listing_content_text_result_page') . '</span>';
          $data .= '</div>';
          $data .= '</div>';
          $data .= '<div>';
          $data .= '<div class="clearfix"></div>';
          $data .= '<div class="mt-1"></div>';
        }
      }

      $data .= '</div>';
      $data .= '</div>';
      $data .= '</div>';
      $data .= '<!-- pr_products_reviews_listing_content end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($data, $this->group);
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_STATUS',
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
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_POSITION',
        'configuration_value' => 'float-none',
        'configuration_description' => 'Select where you want display the module',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\', \'float-none\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Allow the customer to delete the comment ?',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_DELETE_COMMENT',
        'configuration_value' => 'True',
        'configuration_description' => 'The regulation allow the customer to decide to have access at his information',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the AI customer sentiment tage ?',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_SENTIMENT_TAG',
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
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_SORT_ORDER',
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
    return array('MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_STATUS',
      'MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_WIDTH',
      'MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_POSITION',
      'MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_DELETE_COMMENT',
      'MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_SENTIMENT_TAG',
      'MODULES_PRODUCTS_REVIEWS_LISTING_CONTENT_SORT_ORDER',
    );
  }
}

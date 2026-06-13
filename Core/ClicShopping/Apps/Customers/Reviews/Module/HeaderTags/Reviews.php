<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Module\HeaderTags;

use ClicShopping\Apps\Customers\Reviews\Reviews as ReviewsApp;
use ClicShopping\Apps\Marketing\SEO\Classes\Shop\SeoShop as SeoShopReviews;
use ClicShopping\OM\Registry;

class Reviews extends \ClicShopping\OM\Modules\HeaderTagsAbstract
{
  public mixed $lang;
  public mixed $app;
  private mixed $template;
  public $group;

  /**
   * Initializes the module by checking and setting up required application components and configurations.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('ReviewsApp')) {
      Registry::set('ReviewsApp', new ReviewsApp());
    }

    $this->app = Registry::get('ReviewsApp');
    $this->lang = Registry::get('Language');
    $this->group = 'header_tags'; // could be header_tags or footer_scripts

    $this->app->loadDefinitions('Module/HeaderTags/products_reviews');

    $this->title = $this->app->getDef('module_header_tags_products_reviews_title');
    $this->description = $this->app->getDef('module_header_tags_products_reviews_description');

    if (\defined('MODULE_HEADER_TAGS_PRODUCT_REVIEWS_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_PRODUCT_REVIEWS_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_HEADER_TAGS_PRODUCT_REVIEWS_STATUS == 'True');
    }
  }

  /**
   * Checks whether the feature or functionality is enabled.
   *
   * @return bool Returns true if enabled, otherwise false.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates the output for SEO meta tags, including title, description, and keywords,
   * when specific $_GET parameters are set.
   *
   * @return string The generated SEO meta tags including title, description, and keywords wrapped in HTML tags.
   */
  public function getOutput()
  {
    if (isset($_GET['Reviews']) || isset($_GET['ReviewsInfo']) || isset($_GET['ReviewsWrite'])) {
      $this->template = Registry::get('Template');

      if (!Registry::exists('SeoShopReviews')) {
        Registry::set('SeoShopReviews', new SeoShopReviews());
      }

      $CLICSHOPPING_SEOShop = Registry::get('SeoShopReviews');

      $title = $CLICSHOPPING_SEOShop->getSeoReviewsTitle();
      $description = $CLICSHOPPING_SEOShop->getSeoReviewsDescription();
      $keywords = $CLICSHOPPING_SEOShop->getSeoReviewsKeywords();

      $title = $this->template->setTitle($title) . ' ' . $this->template->getTitle();
      $description = $this->template->setDescription($description) . ' ' . $this->template->getDescription();
      $keywords = $this->template->setKeywords($keywords) . ', ' . $this->template->getKeywords();

      $output =
        <<<EOD
          <title>{$title}</title>
          <meta name="description" content="{$description}" />
          <meta name="keywords"  content="{$keywords}" />
          <meta name="news_keywords" content="{$keywords}" />
        EOD;

      return $output;
    }
  }

  /**
   * Installs the module by adding required configuration settings to the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_REVIEWS_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to install this module ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );


    $this->app->db->save('configuration', [
        'configuration_title' => 'Display sort order',
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_REVIEWS_SORT_ORDER',
        'configuration_value' => '185',
        'configuration_description' => 'Display sort order (The lower is displayed in first)',
        'configuration_group_id' => '6',
        'sort_order' => '215',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves the configuration keys used by the module.
   *
   * @return array The list of configuration keys.
   */
  public function keys()
  {
    return [
      'MODULE_HEADER_TAGS_PRODUCT_REVIEWS_STATUS',
      'MODULE_HEADER_TAGS_PRODUCT_REVIEWS_SORT_ORDER'
    ];
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Featured\Module\HeaderTags;

use ClicShopping\Apps\Marketing\Featured\Featured as FeaturedApp;
use ClicShopping\Apps\Marketing\SEO\Classes\Shop\SeoShop as SeoShopFeatured;
use ClicShopping\OM\Registry;

class Featured extends \ClicShopping\OM\Modules\HeaderTagsAbstract
{
  public mixed $lang;
  public mixed $app;
  private mixed $template;

  /**
   * Initializes the module by setting up the necessary registry entries, loading definitions,
   * and configuring the module's properties such as title, description, sort order, and status.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('Featured')) {
      Registry::set('Featured', new FeaturedApp());
    }

    $this->app = Registry::get('Featured');
    $this->lang = Registry::get('Language');
    $this->group = 'header_tags'; // could be header_tags or footer_scripts

    $this->app->loadDefinitions('Module/HeaderTags/products_featured');

    $this->title = $this->app->getDef('module_header_tags_products_featured_title');
    $this->description = $this->app->getDef('module_header_tags_products_featured_description');

    if (\defined('MODULE_HEADER_TAGS_PRODUCT_FEATURED_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_PRODUCT_FEATURED_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_HEADER_TAGS_PRODUCT_FEATURED_STATUS == 'True');
    }
  }

  /**
   * Checks whether the functionality is enabled.
   *
   * @return bool Returns true if enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates the HTML output for meta tags including title, description, and keywords
   * for a featured product page, if specific GET parameters are set.
   *
   * @return string The generated HTML meta tags as a string.
   */
  public function getOutput()
  {
    if (isset($_GET['Products'], $_GET['Featured'])) {
      $this->template = Registry::get('Template');

      if (!Registry::exists('SeoShopFeatured')) {
        Registry::set('SeoShopFeatured', new SeoShopFeatured());
      }

      $CLICSHOPPING_SEOShop = Registry::get('SeoShopFeatured');

      $title = $CLICSHOPPING_SEOShop->getSeoFeaturedTitle();
      $description = $CLICSHOPPING_SEOShop->getSeoFeaturedDescription();
      $keywords = $CLICSHOPPING_SEOShop->getSeoFeaturedKeywords();

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
   * Installs the module by saving its configuration settings into the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_FEATURED_STATUS',
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
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_FEATURED_SORT_ORDER',
        'configuration_value' => '166',
        'configuration_description' => 'Display sort order (The lower is displayed in first)',
        'configuration_group_id' => '6',
        'sort_order' => '215',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves the configuration keys for the module.
   *
   * @return array An array of strings representing the configuration keys.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_PRODUCT_FEATURED_STATUS',
      'MODULE_HEADER_TAGS_PRODUCT_FEATURED_SORT_ORDER'
    ];
  }
}

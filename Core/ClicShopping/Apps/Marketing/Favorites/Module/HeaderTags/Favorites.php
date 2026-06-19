<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Favorites\Module\HeaderTags;

use ClicShopping\Apps\Marketing\Favorites\Favorites as FavoritesApp;
use ClicShopping\Apps\Marketing\SEO\Classes\Shop\SeoShop as SeoShopFavorites;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Domains\HeaderTagsAbstract;

class Favorites extends HeaderTagsAbstract
{
  public mixed $lang;
  public mixed $app;
  private mixed $template;

  /**
   * Initializes the module by setting up the application instance, loading necessary definitions,
   * and configuring the module parameters like title, description, sort order, and enabled status.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('Favorites')) {
      Registry::set('Favorites', new FavoritesApp());
    }

    $this->group = 'header_tags'; // could be header_tags or footer_scripts
    $this->app = Registry::get('Favorites');

    $this->app->loadDefinitions('Module/HeaderTags/products_favorites');

    $this->title = $this->app->getDef('module_header_tags_products_favorites_title');
    $this->description = $this->app->getDef('module_header_tags_products_favorites_description');

    if (\defined('MODULE_HEADER_TAGS_PRODUCT_FAVORITES_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_PRODUCT_FAVORITES_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_HEADER_TAGS_PRODUCT_FAVORITES_STATUS == 'True');
    }
  }

  /**
   * Checks whether the current module is enabled.
   *
   * @return bool True if the module is enabled, otherwise false.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates and returns the HTML output for SEO-related meta tags and title based on
   * Products and Favorites data if provided in the GET request.
   *
   * @return string The HTML output containing the title, description, keywords, and news keywords
   *                meta tags, formatted as per the provided data and template settings.
   */
  public function getOutput()
  {
    if (isset($_GET['Products'], $_GET['Favorites'])) {
      $this->template = Registry::get('Template');

      if (!Registry::exists('SeoShopFavorites')) {
        Registry::set('SeoShopFavorites', new SeoShopFavorites());
      }

      $CLICSHOPPING_SEOShop = Registry::get('SeoShopFavorites');

      $title = $CLICSHOPPING_SEOShop->getSeoFavoritesTitle();
      $description = $CLICSHOPPING_SEOShop->getSeoFavoritesDescription();
      $keywords = $CLICSHOPPING_SEOShop->getSeoFavoritesKeywords();

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
   * Installs the required configuration settings for the module.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_FAVORITES_STATUS',
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
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_FAVORITES_SORT_ORDER',
        'configuration_value' => '165',
        'configuration_description' => 'Display sort order (The lower is displayed in first)',
        'configuration_group_id' => '6',
        'sort_order' => '215',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves the configuration keys associated with the module.
   *
   * @return array An array of configuration keys used by the module.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_PRODUCT_FAVORITES_STATUS',
      'MODULE_HEADER_TAGS_PRODUCT_FAVORITES_SORT_ORDER'
    ];
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Module\HeaderTags;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Products\Products as ProductsApp;
use ClicShopping\OM\Domains\HeaderTagsAbstract;

class ProductsNews extends HeaderTagsAbstract
{
  public mixed $lang;
  public mixed $app;
  public mixed $group;
  
  /**
   * Initializes the module by setting up required dependencies, loading definitions,
   * and configuring properties such as title, description, sort order, and status.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('Products')) {
      Registry::set('Products', new ProductsApp());
    }

    $this->app = Registry::get('Products');
    $this->lang = Registry::get('Language');
    $this->group = 'header_tags'; // could be header_tags or footer_scripts

    $this->app->loadDefinitions('Module/HeaderTags/products_news');

    $this->title = $this->app->getDef('module_header_tags_products_news_title');
    $this->description = $this->app->getDef('module_header_tags_products_news_description');

    if (\defined('MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_NEWS_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_NEWS_SORT_ORDER;
      $this->enabled = (MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_NEWS_STATUS == 'True');
    }
  }

  /**
   * Checks whether the module is enabled.
   *
   * @return bool Returns true if the module is enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates and returns the HTML output for the page's title, meta description, and meta keywords
   * based on the SEO configuration for "Products" and "Products New" pages.
   *
   * The method retrieves SEO-related data such as titles, descriptions, and keywords from the database.
   * If specific language-related SEO fields are empty, it falls back to default language values.
   * These values are sanitized and combined with the template's title, description, and keywords
   * as well as the store name. It then returns the assembled output as an HTML string.
   *
   * @return string|false Returns an HTML string containing the title, meta description, and meta keywords tags if SEO is enabled
   *                      and the required conditions are met. Returns false if SEO is disabled or the conditions are not satisfied.
   */
  public function getOutput()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (!\defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS') || CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Products'], $_GET['ProductsNew'])) {
      $Qsubmit = $this->app->db->prepare('select seo_id,
                                                  language_id,
                                                  seo_defaut_language_title,
                                                  seo_defaut_language_keywords,
                                                  seo_defaut_language_description,
                                                  seo_language_products_new_title,
                                                  seo_language_products_new_keywords,
                                                  seo_language_products_new_description
                                          from :table_seo
                                          where seo_id = 1
                                          and language_id = :language_id
                                        ');
      $Qsubmit->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
      $Qsubmit->execute();

      $store_name = HTML::sanitize(STORE_NAME);

      if (empty($Qsubmit->value('seo_language_products_new_title'))) {
        $title = HTML::sanitize($Qsubmit->value('seo_defaut_language_title')) . ', ' . $CLICSHOPPING_Template->getTitle();
      } else {
        $title = HTML::sanitize($Qsubmit->value('seo_language_products_new_title')) . ', ' . $CLICSHOPPING_Template->getTitle();
      }

      if (empty($Qsubmit->value('seo_language_products_new_description'))) {
        $description = HTML::sanitize($Qsubmit->value('seo_defaut_language_description')) . ', ' . $CLICSHOPPING_Template->getDescription() . ', ' . $store_name;
      } else {
        $description = HTML::sanitize($Qsubmit->value('seo_language_products_new_description')) . ', ' . $CLICSHOPPING_Template->getDescription() . ', ' . $store_name;
      }

      if (empty($Qsubmit->value('seo_language_products_new_keywords'))) {
        $keywords = HTML::sanitize($Qsubmit->value('seo_defaut_language_keywords')) . ', ' . $CLICSHOPPING_Template->getKeywords() . ', ' . $store_name;
      } else {
        $keywords = HTML::sanitize($Qsubmit->value('seo_language_products_new_keywords')) . ', ' . $CLICSHOPPING_Template->getKeywords() . ', ' . $store_name;
      }

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
   * Installs the module by saving configuration settings into the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_NEWS_STATUS',
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
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_NEWS_SORT_ORDER',
        'configuration_value' => '162',
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
   * @return array An array of configuration keys for the module.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_NEWS_STATUS',
      'MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_NEWS_SORT_ORDER'
    ];
  }
}

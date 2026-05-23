<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Module\HeaderTags;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Marketing\SEO\Classes\Shop\SeoShop as SeoShopSearch;
use ClicShopping\Apps\Marketing\SEO\SEO as SEOApp;

class AdvancedSearchResult extends \ClicShopping\OM\Modules\HeaderTagsAbstract
{
  public mixed $lang;
  public mixed $app;
  private mixed $template;

  /**
   * Initializes the class by setting up necessary application-wide objects,
   * loading definitions, and establishing module-specific configurations such as title,
   * description, status, and sorting order.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('SEO')) {
      Registry::set('SEO', new SEOApp());
    }

    $this->app = Registry::get('SEO');
    $this->lang = Registry::get('Language');
    $this->group = 'header_tags'; // could be header_tags or footer_scripts

    $this->app->loadDefinitions('Module/header_tag/advanced_search_result');

    $this->title = $this->app->getDef('module_header_tags_advanced_search_result_title');
    $this->description = $this->app->getDef('module_header_tags_advanced_search_result_description');

    if (\defined('MODULE_HEADER_TAGS_ADVANCED_SEARCH_RESULT_STATUS')) {
      $this->sort_order = MODULE_HEADER_TAGS_ADVANCED_SEARCH_RESULT_SORT_ORDER;
      $this->enabled = (MODULE_HEADER_TAGS_ADVANCED_SEARCH_RESULT_STATUS == 'True');
    }
  }

  /**
   * Checks if the current instance is enabled.
   *
   * @return bool True if the instance is enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates and returns the HTML meta information based on the search query and keywords.
   *
   * It creates meta tags for title, description, and keywords by combining sanitized inputs
   * and existing template values. If necessary, initializes the SeoShopSearch registry.
   *
   * @return string Returns the generated meta tags including title, description, and keywords.
   */
  public function getOutput()
  {
    if (isset($_POST['Search'], $_POST['Q'], $_POST['keywords'])) {
      $this->template = Registry::get('Template');

      if (!Registry::exists('SeoShopSearch')) {
        Registry::set('SeoShopSearch', new SeoShopSearch());
      }

      $CLICSHOPPING_SEOShop = Registry::get('SeoShopSearch');

      $title = $CLICSHOPPING_SEOShop->getSeoIndexTitle();
      $description = $CLICSHOPPING_SEOShop->getSeoIndexDescription();
      $keywords = $CLICSHOPPING_SEOShop->getSeoIndexKeywords();

      $keywords_clean = HTML::sanitize($_POST['keywords']);

      $title = $keywords_clean . ',' . $this->template->setTitle($title) . ' ' . $this->template->getTitle();
      $description = $keywords_clean . ',' . $this->template->setDescription($description) . ', ' . $this->template->getDescription();
      $keywords = $keywords_clean . ',' . $this->template->setKeywords($keywords) . ', ' . $this->template->getKeywords();

      $output =
        <<<EOD
    <title>{$title}</title>
    <meta name="description" content="{$description}" />
    <meta name="keywords" content="{$keywords}" />
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
  public function install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_ADVANCED_SEARCH_RESULT_STATUS',
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
        'configuration_key' => 'MODULE_HEADER_TAGS_ADVANCED_SEARCH_RESULT_SORT_ORDER',
        'configuration_value' => '45',
        'configuration_description' => 'Display sort order (The lower is displayed in first)',
        'configuration_group_id' => '6',
        'sort_order' => '5',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes configuration entries from the database table by executing a delete query.
   *
   * This method constructs a SQL DELETE statement to remove rows from the
   * :table_configuration table based on the provided configuration keys.
   *
   * @return int|bool Returns the number of affected rows if the query is executed successfully,
   *                  or false on failure.
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Retrieves the configuration keys for the module.
   *
   * @return array An array of configuration keys.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_ADVANCED_SEARCH_RESULT_STATUS',
      'MODULE_HEADER_TAGS_ADVANCED_SEARCH_RESULT_SORT_ORDER'
    ];
  }
}

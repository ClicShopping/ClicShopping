<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Module\HeaderTags;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\SEO\Classes\Shop\SeoShop as SeoShopIindex;
use ClicShopping\Apps\Marketing\SEO\SEO as SEOApp;

class Index extends \ClicShopping\OM\Modules\HeaderTagsAbstract
{
  public mixed $lang;
  public mixed $app;
  public mixed $group;
  private mixed $template;

  /**
   * Initializes the module by setting necessary properties and loading definitions.
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

    $this->app->loadDefinitions('Module/header_tag/index');

    $this->title = $this->app->getDef('module_header_tags_index_title');
    $this->description = $this->app->getDef('module_header_tags_index_description');

    if (\defined('MODULE_HEADER_TAGS_INDEX_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_INDEX_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_HEADER_TAGS_INDEX_STATUS == 'True');
    }
  }

  /**
   * Checks if the current instance is enabled.
   *
   * @return bool Returns true if the instance is enabled, otherwise false.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates and returns the HTML output for meta tags such as title, description, and keywords
   * based on the current URI or specific conditions.
   *
   * @return string The generated HTML output for the meta tags.
   */
  public function getOutput()
  {
    $this->template = Registry::get('Template');

    $output = '';

    if (HTTP::getUri() === CLICSHOPPING::getConfig('http_path', 'Shop') || HTTP::getUri() === CLICSHOPPING::getConfig('http_path', 'Shop') . 'index.php') {
      if (!Registry::exists('SeoShopIindex')) {
        Registry::set('SeoShopIindex', new SeoShopIindex());
      }

      $CLICSHOPPING_SEOShop = Registry::get('SeoShopIindex');

      $title = $CLICSHOPPING_SEOShop->getSeoIndexTitle();
      $description = $CLICSHOPPING_SEOShop->getSeoIndexDescription();
      $keywords = $CLICSHOPPING_SEOShop->getSeoIndexKeywords();

      $title = $this->template->setTitle($title) . ' ' . $this->template->getTitle();
      $description = $this->template->setDescription($description) . ', ' . $this->template->getDescription();
      $keywords = $this->template->setKeywords($keywords) . ', ' . $this->template->getKeywords();

      $output =
        <<<EOD
    <title>{$title}</title>
    <meta name="description" content="{$description}" />
    <meta name="keywords" content="{$keywords}" />
    <meta name="news_keywords" content="{$keywords}" />
EOD;
    } elseif (isset($_GET['Account'])) {
      if (!Registry::exists('SeoShopIindex')) {
        Registry::set('SeoShopIindex', new SeoShopIindex());
      }

      $CLICSHOPPING_SEOShop = Registry::get('SeoShopIindex');

      $title = $CLICSHOPPING_SEOShop->getSeoIndexTitle();
      $description = $CLICSHOPPING_SEOShop->getSeoIndexDescription();
      $keywords = $CLICSHOPPING_SEOShop->getSeoIndexKeywords();

      $title = $this->template->setTitle($title) . ', ' . $this->template->getTitle();
      $description = $this->template->setDescription($description) . ', ' . $this->template->getDescription();
      $keywords = $this->template->setKeywords($keywords) . ', ' . $this->template->getKeywords();

      $output =
        <<<EOD
    <title>{$title}</title>
    <meta name="description" content="{$description}" />
    <meta name="keywords" content="{$keywords}" />
    <meta name="news_keywords" content="{$keywords}" />
EOD;
    }

    return $output;
  }

  /**
   * Installs the module by inserting configuration settings into the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_INDEX_STATUS',
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
        'configuration_key' => 'MODULE_HEADER_TAGS_INDEX_SORT_ORDER',
        'configuration_value' => '161',
        'configuration_description' => 'Display sort order (The lower is displayed in first)',
        'configuration_group_id' => '6',
        'sort_order' => '215',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes configuration entries from the database where the configuration keys match
   * the keys returned by the keys() method.
   *
   * Uses the database connection from the `Registry` to execute the delete command.
   *
   * @return int|false Returns the number of affected rows, or false on failure.
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Returns an array of configuration keys used by the module.
   *
   * @return array An array containing the configuration keys for the module.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_INDEX_STATUS',
      'MODULE_HEADER_TAGS_INDEX_SORT_ORDER'
    ];
  }
}

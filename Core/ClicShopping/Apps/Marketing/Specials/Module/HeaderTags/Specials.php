<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Specials\Module\HeaderTags;

use ClicShopping\Apps\Marketing\Specials\Specials as SpecialsApp;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\SEO\Classes\Shop\SeoShop as SeoShopSpecials;
use ClicShopping\OM\Domains\HeaderTagsAbstract;

class Specials extends HeaderTagsAbstract
{
  public mixed $lang;
  public mixed $app;
  private mixed $template;

  /**
   * Initializes the module by setting up registry entries, definitions, and configurations.
   * Ensures that necessary components such as `Specials` and language data are loaded.
   * Configures the module's properties including title, description, sort order, and enabled status based on constants.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('Specials')) {
      Registry::set('Specials', new SpecialsApp());
    }

    $this->app = Registry::get('Specials');
    $this->lang = Registry::get('Language');
    $this->group = 'header_tags'; // could be header_tags or footer_scripts

    $this->app->loadDefinitions('Module/HeaderTags/products_specials');

    $this->title = $this->app->getDef('module_header_tags_products_specials_title');
    $this->description = $this->app->getDef('module_header_tags_products_specials_description');

    if (\defined('MODULE_HEADER_TAGS_PRODUCT_SPECIALS_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_PRODUCT_SPECIALS_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_HEADER_TAGS_PRODUCT_SPECIALS_STATUS == 'True');
    }
  }

  /**
   * Checks if the current module or feature is enabled.
   *
   * @return bool Returns true if the module or feature is enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates and returns the output for SEO-related meta tags when specific GET parameters are set.
   *
   * The method constructs the meta tag components (title, description, keywords, and news_keywords)
   * by retrieving and combining data from the SeoShopSpecials and Template registries.
   *
   * @return string The generated HTML meta tags as a string, including title, description,
   *                keywords, and news_keywords, formatted for SEO purposes.
   *                Returns null if required GET parameters are not set.
   */
  public function getOutput()
  {
    if (isset($_GET['Products'], $_GET['Specials'])) {
      $this->template = Registry::get('Template');

      if (!Registry::exists('SeoShopSpecials')) {
        Registry::set('SeoShopSpecials', new SeoShopSpecials());
      }

      $CLICSHOPPING_SEOShop = Registry::get('SeoShopSpecials');

      $title = $CLICSHOPPING_SEOShop->getSeoSpecialsTitle();
      $description = $CLICSHOPPING_SEOShop->getSeoSpecialsDescription();
      $keywords = $CLICSHOPPING_SEOShop->getSeoSpecialsKeywords();

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
   * Installs the configuration settings for the module into the database.
   *
   * This method adds required configuration settings into the database to enable
   * the proper functioning and customization of the module.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_SPECIALS_STATUS',
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
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_SPECIALS_SORT_ORDER',
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
   * Retrieves the list of configuration keys relevant to the module.
   *
   * @return array An array of configuration keys used by the module.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_PRODUCT_SPECIALS_STATUS',
      'MODULE_HEADER_TAGS_PRODUCT_SPECIALS_SORT_ORDER'
    ];
  }
}

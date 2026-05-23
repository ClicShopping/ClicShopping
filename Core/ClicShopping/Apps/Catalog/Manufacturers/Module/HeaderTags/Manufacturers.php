<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Module\HeaderTags;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Manufacturers\Classes\Shop\Manufacturers as ManufacturersShop;
use ClicShopping\Apps\Catalog\Manufacturers\Manufacturers as ManufacturersApp;

class Manufacturers extends \ClicShopping\OM\Modules\HeaderTagsAbstract
{
  private mixed $lang;
  public mixed $app;
  private mixed $template;

  /**
   * Initializes the necessary properties and configurations for the class.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('Manufacturers')) {
      Registry::set('Manufacturers', new ManufacturersApp());
    }

    $this->app = Registry::get('Manufacturers');
    $this->lang = Registry::get('Language');
    $this->group = 'header_tags'; // could be header_tags or footer_scripts

    if (CLICSHOPPING::getSite() === 'ClicShoppingAdmin') {
      $this->app->loadDefinitions('Module/HeaderTags/manufacturers');

      $this->title = $this->app->getDef('module_header_tags_manufacturers_title');
      $this->description = $this->app->getDef('module_header_tags_manufacturers_description');
    }

    if (\defined('MODULE_HEADER_TAGS_MANUFACTURERS_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_MANUFACTURERS_SORT_ORDER;
      $this->enabled = (MODULE_HEADER_TAGS_MANUFACTURERS_STATUS == 'True');
    }
  }

  /**
   * Checks if the current module or functionality is enabled.
   *
   * @return bool Returns true if enabled, otherwise false.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates and returns the HTML meta tags for the manufacturers page, including title, description, and keywords.
   *
   * @return string|bool The formatted meta tags as a string, or false if the manufacturers module is disabled or the manufacturers ID is invalid.
   */
  public function getOutput()
  {
    $this->template = Registry::get('Template');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!\defined('CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS') || CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['manufacturersId']) && is_numeric($_GET['manufacturersId'])) {
      Registry::set('ManufacturersShop', new ManufacturersShop());

      $this->manufacturers_shop = Registry::get('ManufacturersShop');

      $id = $this->manufacturers_shop->getID();

      if (is_numeric($id) && !\is_null($id)) {
        $manufacturers_title = $this->manufacturers_shop->getTitle($id);

        $QmetaInfo = $CLICSHOPPING_Db->prepare('select manufacturer_seo_title,
                                                         manufacturer_seo_description,
                                                         manufacturer_seo_keyword
                                                 from :table_manufacturers_info
                                                 where manufacturers_id = :manufacturers_id
                                                 and languages_id = :language_id
                                               ');
        $QmetaInfo->bindInt(':manufacturers_id', $id);
        $QmetaInfo->bindInt(':language_id', $this->lang->getId());
        $QmetaInfo->execute();

        $Qsubmit = $CLICSHOPPING_Db->prepare('select seo_id,
                                                        language_id,
                                                        seo_defaut_language_title,
                                                        seo_defaut_language_keywords,
                                                        seo_defaut_language_description
                                               from :table_seo
                                               where seo_id = 1
                                               and language_id = :language_id
                                              ');

        $Qsubmit->bindInt(':language_id', $this->lang->getId());

        if (!empty($QmetaInfo->value('manufacturer_seo_title'))) {
          $title = $manufacturers_title . ', ' . $QmetaInfo->value('manufacturer_seo_title') . ', ' . HTML::outputProtected(STORE_NAME);
        } elseif (!empty($Qsubmit->value('seo_defaut_language_title'))) {
          $title = $manufacturers_title . ', ' . HTML::sanitize($Qsubmit->value('seo_defaut_language_title')) . ', ' . HTML::outputProtected(STORE_NAME);
        } else {
          $title = $manufacturers_title . ', ' . HTML::outputProtected(STORE_NAME);
        }

        if (!empty($QmetaInfo->value('manufacturer_seo_description'))) {
          $description = $manufacturers_title . ', ' . $QmetaInfo->value('manufacturer_seo_description') . ', ' . HTML::outputProtected(STORE_NAME);
        } elseif (!empty($Qsubmit->value('seo_defaut_language_description'))) {
          $description = $manufacturers_title . ', ' . HTML::sanitize($Qsubmit->value('seo_defaut_language_description')) . ', ' . HTML::outputProtected(STORE_NAME);
        } else {
          $description = $manufacturers_title . ',' . HTML::outputProtected(STORE_NAME);
        }

        if (!empty($QmetaInfo->value('manufacturer_seo_keyword'))) {
          $keywords = $manufacturers_title . ', ' . $QmetaInfo->value('manufacturer_seo_keyword');
        } elseif (!empty($Qsubmit->value('seo_defaut_language_keywords'))) {
          $keywords = $manufacturers_title . ', ' . HTML::sanitize($Qsubmit->value('seo_defaut_language_keywords'));
        } else {
          $keywords = $manufacturers_title . ',' . HTML::outputProtected(STORE_NAME);
        }

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
  }

  /**
   * Installs the module by adding its configuration settings to the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_MANUFACTURERS_STATUS',
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
        'configuration_key' => 'MODULE_HEADER_TAGS_MANUFACTURERS_SORT_ORDER',
        'configuration_value' => '172',
        'configuration_description' => 'Display sort order (The lower is displayed in first)',
        'configuration_group_id' => '6',
        'sort_order' => '215',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Returns an array of configuration keys used by the module.
   *
   * @return array An array of configuration key identifiers.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_MANUFACTURERS_STATUS',
      'MODULE_HEADER_TAGS_MANUFACTURERS_SORT_ORDER'
    ];
  }
}

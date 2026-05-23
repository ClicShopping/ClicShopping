<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Module\HeaderTags;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Communication\PageManager\PageManager as PageManagerApp;

class Sitemap extends \ClicShopping\OM\Modules\HeaderTagsAbstract
{
  public mixed $lang;
  public mixed $template;
  public mixed $app;

  /**
   * Initializes the module by setting up application dependencies, defining language configurations,
   * setting module properties such as title and description, and enabling/disabling the module
   * based on predefined constants.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('PageManager')) {
      Registry::set('PageManager', new PageManagerApp());
    }

    $this->app = Registry::get('PageManager');
    $this->lang = Registry::get('Language');
    $this->group = 'header_tags'; // could be header_tags or footer_scripts

    $this->app->loadDefinitions('Module/HeaderTags/sitemap');

    $this->title = $this->app->getDef('module_header_tags_sitemap_title');
    $this->description = $this->app->getDef('module_header_tags_sitemap_description');

    if (\defined('MODULE_HEADER_TAGS_SITEMAP_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_SITEMAP_SORT_ORDER;
      $this->enabled = (MODULE_HEADER_TAGS_SITEMAP_STATUS == 'True');
    }
  }

  /**
   * Checks if the current module or component is enabled.
   *
   * @return bool Returns true if the module/component is enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates and returns the output for the header tags including title, description, and keywords
   * based on the Page Manager and SEO configurations.
   *
   * @return string|false Returns the header tags output as a string, or false if the CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS
   *                      is not defined or set to 'False', or if the required parameters are not present.
   */
  public function getOutput()
  {
    $this->template = Registry::get('Template');

    if (!\defined('CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS') || CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Info'], $_GET['SiteMap'])) {
      $Qsubmit = $this->app->db->prepare('select seo_id,
                                                    language_id,
                                                    seo_defaut_language_title,
                                                    seo_defaut_language_keywords,
                                                    seo_defaut_language_description
                                             from :table_seo
                                             where seo_id = 1
                                             and language_id = :language_id
                                             ');

      $Qsubmit->bindInt(':language_id', $this->lang->getId());
      $Qsubmit->execute();

      $QpageManager = $this->app->db->prepare('select pages_id,
                                                        language_id,
                                                        pages_title,
                                                        page_manager_head_title_tag,
                                                        page_manager_head_desc_tag,
                                                        page_manager_head_keywords_tag
                                                   from :table_pages_manager_description
                                                   where pages_id = :pages_id
                                                   and language_id = :language_id
                                                  ');

      $QpageManager->bindInt(':language_id', $this->lang->getId());
      $QpageManager->bindInt(':pages_id', 8);
      $QpageManager->execute();

      if (empty($QpageManager->value('page_manager_head_title_tag'))) {
        $pages_title = HTML::sanitize($QpageManager->value('pages_title'));
      } else {
        $head_title = HTML::sanitize($QpageManager->value('page_manager_head_title_tag'));
        $pages_title_name = HTML::sanitize($QpageManager->value('pages_title'));
        $pages_title = $head_title . ', ' . $pages_title_name;
      }

      if (empty($QpageManager->value('page_manager_head_title_tag'))) {
        if (empty($Qsubmit->value('page_manager_head_title_tag'))) {
          $title = $pages_title . ', ' . HTML::sanitize($Qsubmit->value('seo_defaut_language_title'));
        } else {
          $title = $pages_title;
        }
      } else {
        $title = $pages_title . ', ' . HTML::sanitize($Qsubmit->value('seo_defaut_language_title'));
      }

      if (empty($QpageManager->value('page_manager_head_desc_tag'))) {
        if (empty($Qsubmit->value('page_manager_head_desc_tag'))) {
          $description = $pages_title . ', ' . HTML::sanitize($Qsubmit->value('seo_defaut_language_description'));
        } else {
          $description = $pages_title . ', ' . $QpageManager->value('page_manager_head_desc_tag');
        }
      } else {
        $description = $pages_title . ', ' . HTML::sanitize($Qsubmit->value('seo_defaut_language_description'));
      }

      if (empty($QpageManager->value('page_manager_head_keywords_tag'))) {
        if (empty($Qsubmit->value('page_manager_head_keywords_tag'))) {
          $keywords = $pages_title . HTML::sanitize($Qsubmit->value('seo_defaut_language_keywords'));
        } else {
          $keywords = $pages_title . $QpageManager->value('page_manager_head_keywords_tag');
        }
      } else {
        $keywords = $pages_title . HTML::sanitize($Qsubmit->value('seo_defaut_language_keywords'));
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

  /**
   * Installs the module by saving the necessary configuration settings into the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_SITEMAP_STATUS',
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
        'configuration_key' => 'MODULE_HEADER_TAGS_SITEMAP_SORT_ORDER',
        'configuration_value' => '158',
        'configuration_description' => 'Display sort order (The lower is displayed in first)',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves the configuration keys for the module.
   *
   * @return array An array of configuration key names used by the module.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_SITEMAP_STATUS',
      'MODULE_HEADER_TAGS_SITEMAP_SORT_ORDER'
    ];
  }
}

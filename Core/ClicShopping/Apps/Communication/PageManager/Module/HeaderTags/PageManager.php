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
use ClicShopping\OM\Domains\HeaderTagsAbstract;

class PageManager extends HeaderTagsAbstract
{
  public mixed $app;
  public mixed $template;
  public mixed $group;
  private mixed $lang;

  /**
   * Initializes the PageManager module by setting up necessary configurations,
   * loading language definitions, and determining the module's status and sort order.
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

    $this->app->loadDefinitions('Module/HeaderTags/page_manager');

    $this->title = $this->app->getDef('module_header_tags_page_manager_title');
    $this->description = $this->app->getDef('module_header_tags_page_manager_description');

    if (\defined('MODULE_HEADER_TAGS_PAGE_MANAGER_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_PAGE_MANAGER_SORT_ORDER;
      $this->enabled = (MODULE_HEADER_TAGS_PAGE_MANAGER_STATUS == 'True');
    }
  }

  /**
   * Checks whether the current module or feature is enabled.
   *
   * @return bool Returns true if the module or feature is enabled, otherwise false.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates and returns the HTML head output for the page, including title, meta description,
   * and meta keywords by using existing data from the database.
   *
   * @return string|false The formatted HTML head output string if conditions are met, or false
   *                      if the application is disabled or required GET parameters are missing.
   */
  public function getOutput()
  {
    if (!\defined('CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS') || CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Info'], $_GET['Content'], $_GET['pagesId'])) {
      $this->template = Registry::get('Template');

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
      $QpageManager->bindInt(':pages_id', (int)$_GET['pagesId']);
      $QpageManager->execute();

      $p_title = HTML::sanitize($QpageManager->value('pages_title'));

      $title = implode(', ', array_unique(array_filter(array_map('trim', [
        $QpageManager->value('page_manager_head_title_tag'),
        $p_title,
        $Qsubmit->value('seo_defaut_language_title')
      ]))));

      $description = implode(', ', array_unique(array_filter(array_map('trim', [
        $QpageManager->value('page_manager_head_desc_tag'),
        $p_title,
        $Qsubmit->value('seo_defaut_language_description')
      ]))));

      $keywords = implode(', ', array_unique(array_filter(array_map('trim', [
        $QpageManager->value('page_manager_head_keywords_tag'),
        $p_title,
        $Qsubmit->value('seo_defaut_language_keywords')
      ]))));

      // Escape for safe output in the <head> (attribute injection / stored XSS).
      $title = HTML::outputProtected($title);
      $description = HTML::outputProtected($description);
      $keywords = HTML::outputProtected($keywords);

      return <<<EOD
    <title>{$title}</title>
    <meta name="description" content="{$description}" />
    <meta name="keywords"  content="{$keywords}" />
    <meta name="news_keywords" content="{$keywords}" />
EOD;
    }
  }

  /**
   * Installs the module by inserting configuration entries into the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_PAGE_MANAGER_STATUS',
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
        'configuration_key' => 'MODULE_HEADER_TAGS_PAGE_MANAGER_SORT_ORDER',
        'configuration_value' => '155',
        'configuration_description' => 'Display sort order (The lower is displayed in first)',
        'configuration_group_id' => '6',
        'sort_order' => '160',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves an array of configuration keys related to the module.
   *
   * @return array List of configuration keys.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_PAGE_MANAGER_STATUS',
      'MODULE_HEADER_TAGS_PAGE_MANAGER_SORT_ORDER'
    ];
  }
}

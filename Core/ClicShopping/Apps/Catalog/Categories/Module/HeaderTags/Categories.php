<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Module\HeaderTags;

use ClicShopping\Apps\Catalog\Categories\Categories as CategoriesApp;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Categories extends \ClicShopping\OM\Modules\HeaderTagsAbstract
{
  public mixed $app;
  public mixed $group;
  private mixed $lang;
  private mixed $template;

  protected function init()
  {
    if (!Registry::exists('Categories')) {
      Registry::set('Categories', new CategoriesApp());
    }

    $this->app = Registry::get('Categories');
    $this->lang = Registry::get('Language');
    $this->group = 'header_tags'; // could be header_tags or footer_scripts

    $this->app->loadDefinitions('Module/HeaderTags/products_categories');

    $this->title = $this->app->getDef('module_header_tags_products_categories_title');
    $this->description = $this->app->getDef('module_header_tags_products_categories_description');

    if (\defined('MODULE_HEADER_TAGS_PRODUCT_CATEGORIES_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_PRODUCT_CATEGORIES_SORT_ORDER;
      $this->enabled = (MODULE_HEADER_TAGS_PRODUCT_CATEGORIES_STATUS == 'True');
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function getOutput()
  {
    $this->template = Registry::get('Template');
    $CLICSHOPPING_Category = Registry::get('Category');

    if (!\defined('CLICSHOPPING_APP_CATEGORIES_CT_STATUS') || CLICSHOPPING_APP_CATEGORIES_CT_STATUS == 'False') {
      return false;
    }

    $current_category_id = $CLICSHOPPING_Category->getPath();

    if (CLICSHOPPING::getBaseNameIndex()) {
// $categories is set in OM.php to add the category to the breadcrumb
// $categories is not set so a database query is needed
      if ($current_category_id > 0) {
        $Qsubmit = $this->app->db->prepare('select seo_id,
                                                    language_id,
                                                    seo_defaut_language_title,
                                                    seo_defaut_language_keywords,
                                                    seo_defaut_language_description
                                              from :table_seo
                                              where seo_id = 1
                                              and language_id = :language_id
                                            ');
        $Qsubmit->bindInt(':language_id', (int)$this->lang->getId());
        $Qsubmit->execute();

        $Qcategories = $this->app->db->prepare('select categories_name,
                                                         categories_head_title_tag,
                                                         categories_head_desc_tag,
                                                         categories_head_keywords_tag
                                                  from :table_categories_description
                                                  where categories_id = :categories_id
                                                  and language_id = :language_id
                                                  limit 1
                                                ');

        $Qcategories->bindInt(':categories_id', (int)$current_category_id);
        $Qcategories->bindInt(':language_id', (int)$this->lang->getId());
        $Qcategories->execute();

      if ($Qcategories->rowCount() > 0) {
        $cat_name = HTML::sanitize($Qcategories->value('categories_name'));
        $store_name = HTML::outputProtected(STORE_NAME);

        // --- Traitement du Titre ---
        $title_parts = [];
        if (!empty($Qcategories->value('categories_head_title_tag'))) {
          $title_parts[] = HTML::sanitize($Qcategories->value('categories_head_title_tag'));
        }
        $title_parts[] = $cat_name;
        if (!empty($Qsubmit->value('seo_defaut_language_title'))) {
          $title_parts[] = HTML::sanitize($Qsubmit->value('seo_defaut_language_title'));
        }
        $title_parts[] = $store_name;
        // Nettoyage et fusion
        $final_title = implode(', ', array_unique(array_filter(array_map('trim', $title_parts))));
        $this->template->setTitle($final_title);

        // --- Traitement de la Description ---
        $desc_parts = [];
        if (!empty($Qcategories->value('categories_head_desc_tag'))) {
          $desc_parts[] = HTML::sanitize($Qcategories->value('categories_head_desc_tag'));
        }
        $desc_parts[] = $cat_name;
        if (!empty($Qsubmit->value('seo_defaut_language_description'))) {
          $desc_parts[] = HTML::sanitize($Qsubmit->value('seo_defaut_language_description'));
        }
        $desc_parts[] = $store_name;
        $final_desc = implode(', ', array_unique(array_filter(array_map('trim', $desc_parts))));
        $this->template->setDescription($final_desc);

        // --- Traitement des Mots-clés ---
        $key_parts = [];
        if (!empty($Qcategories->value('categories_head_keywords_tag'))) {
          $key_parts[] = $Qcategories->value('categories_head_keywords_tag');
        }
        $key_parts[] = $cat_name;
        if (!empty($Qsubmit->value('seo_defaut_language_keywords'))) {
          $key_parts[] = HTML::sanitize($Qsubmit->value('seo_defaut_language_keywords'));
        }
        $final_keywords = implode(', ', array_unique(array_filter(array_map('trim', $key_parts))));
        $this->template->setKeywords($final_keywords);

        $output = <<<EOD
    <title>{$this->template->getTitle()}</title>
    <meta name="description" content="{$this->template->getDescription()}" />
    <meta name="keywords" content="{$this->template->getKeywords()}" />
    <meta name="news_keywords" content="{$this->template->getKeywords()}" />
EOD;

          return $output;
        }
      }
    }
  }

  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_CATEGORIES_STATUS',
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
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_CATEGORIES_SORT_ORDER',
        'configuration_value' => '162',
        'configuration_description' => 'Display sort order (The lower is displayed in first)',
        'configuration_group_id' => '6',
        'sort_order' => '215',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  public function keys()
  {
    return ['MODULE_HEADER_TAGS_PRODUCT_CATEGORIES_STATUS',
      'MODULE_HEADER_TAGS_PRODUCT_CATEGORIES_SORT_ORDER'
    ];
  }
}

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

class ProductsDescription extends HeaderTagsAbstract
{
  public mixed $lang;
  public mixed $app;
  public string $group;

  /**
   * Initializes the module by setting up required registry entries, language, and configuration properties.
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

    $this->app->loadDefinitions('Module/HeaderTags/products_description');

    $this->title = $this->app->getDef('module_header_tags_products_description_title');
    $this->description = $this->app->getDef('module_header_tags_products_description_description');

    if (\defined('MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_DESCRIPTION_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_DESCRIPTION_SORT_ORDER;
      $this->enabled = (MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_DESCRIPTION_STATUS == 'True');
    }
  }

  /**
   * Checks whether the current module or feature is enabled.
   *
   * @return bool Returns true if enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates and returns the SEO output consisting of title, description, and keywords
   * for a product based on the product's information and language settings.
   *
   * @return string|false Returns the generated SEO output as a string if the product
   * information is properly retrieved; returns false otherwise.
   */
  public function getOutput()
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    if (!\defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS') || CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Id']) || isset($_GET['products_id'])) {
      if ($CLICSHOPPING_ProductsCommon->getID()) {
        $products_id = $CLICSHOPPING_ProductsCommon->getID();

        $Qsubmit = $this->app->db->prepare('select seo_id,
                                                    language_id,
                                                    seo_defaut_language_title,
                                                    seo_defaut_language_keywords,
                                                    seo_defaut_language_description,
                                                    seo_language_products_info_title,
                                                    seo_language_products_info_keywords,
                                                    seo_language_products_info_description
                                              from :table_seo
                                              where seo_id = 1
                                              and language_id = :language_id
                                            ');
        $Qsubmit->bindInt(':language_id', $CLICSHOPPING_Language->getId());
        $Qsubmit->execute();

        $QproductInfo = $this->app->db->prepare('select pd.products_head_title_tag,
                                                           pd.products_head_keywords_tag,
                                                           pd.products_head_desc_tag
                                                    from :table_products p,
                                                         :table_products_description pd
                                                    where p.products_status = 1
                                                    and p.products_view = 1
                                                    and p.products_id = :products_id
                                                    and pd.products_id = p.products_id
                                                    and pd.language_id = :language_id
                                                  ');
        $QproductInfo->bindInt(':products_id', $products_id);
        $QproductInfo->bindInt(':language_id', $CLICSHOPPING_Language->getId());
        $QproductInfo->execute();

        $QcategoryInfo = $this->app->db->prepare('select cd.categories_name
                                                    from :table_products_to_categories ptc,
                                                         :table_categories_description cd
                                                    where ptc.products_id = :products_id
                                                    and ptc.categories_id = cd.categories_id
                                                    and cd.language_id = :language_id
                                                    limit 1
                                                  ');

        $QcategoryInfo->bindInt(':products_id', $products_id);
        $QcategoryInfo->bindInt(':language_id', $CLICSHOPPING_Language->getId());
        $QcategoryInfo->execute();

        // Préparation des variables de base
        $products_name = HTML::sanitize($CLICSHOPPING_ProductsCommon->getProductsName($products_id));
        $categories_name = HTML::sanitize($QcategoryInfo->value('categories_name'));
        $store_name = HTML::sanitize(STORE_NAME);

        // --- TITRE ---
        $title_parts = [];
        if (!empty($QproductInfo->value('products_head_title_tag'))) {
          $title_parts[] = HTML::sanitize($QproductInfo->value('products_head_title_tag'));
        }
        $title_parts[] = $products_name;
        $title_parts[] = $categories_name;

        $seo_title_custom = $Qsubmit->value('seo_language_products_info_title');
        $title_parts[] = !empty($seo_title_custom) ? HTML::sanitize($seo_title_custom) : HTML::sanitize($Qsubmit->value('seo_defaut_language_title'));
        $title_parts[] = $store_name;

        $final_title = implode(', ', array_unique(array_filter(array_map('trim', $title_parts))));

        // --- DESCRIPTION ---
        $desc_parts = [];
        if (!empty($QproductInfo->value('products_head_desc_tag'))) {
          $desc_parts[] = HTML::sanitize($QproductInfo->value('products_head_desc_tag'));
        }
        $desc_parts[] = $products_name;
        $desc_parts[] = $categories_name;

        $seo_desc_custom = $Qsubmit->value('seo_language_products_info_description');
        $desc_parts[] = !empty($seo_desc_custom) ? HTML::sanitize($seo_desc_custom) : HTML::sanitize($Qsubmit->value('seo_defaut_language_description'));

        $final_description = implode(', ', array_unique(array_filter(array_map('trim', $desc_parts))));

        // --- MOTS-CLÉS ---
        $key_parts = [];
        if (!empty($QproductInfo->value('products_head_keywords_tag'))) {
          $key_parts[] = HTML::sanitize($QproductInfo->value('products_head_keywords_tag'));
        }
        $key_parts[] = $products_name;
        $key_parts[] = $categories_name;

        $seo_key_custom = $Qsubmit->value('seo_language_products_info_keywords');
        $key_parts[] = !empty($seo_key_custom) ? HTML::sanitize($seo_key_custom) : HTML::sanitize($Qsubmit->value('seo_defaut_language_keywords'));

        $final_keywords = implode(', ', array_unique(array_filter(array_map('trim', $key_parts))));

        $output = <<<EOD
    <title>{$final_title}</title>
    <meta name="description" content="{$final_description}" />
    <meta name="keywords"  content="{$final_keywords}" />
    <meta name="news_keywords" content="{$final_keywords}" />
EOD;
        return $output;
      }
    }
  }

  /**
   * Installs the module by adding necessary configuration values into the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_DESCRIPTION_STATUS',
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
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_DESCRIPTION_SORT_ORDER',
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
   * Retrieves an array of configuration keys related to the product description module.
   *
   * @return array Returns an array of configuration key names.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_DESCRIPTION_STATUS',
      'MODULE_HEADER_TAGS_PRODUCT_PRODUCTS_DESCRIPTION_SORT_ORDER'
    ];
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Catalog\Manufacturers\Sites\ClicShoppingAdmin\Pages\Home\Actions\Manufacturers;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Manufacturers');
  }

  public function execute()
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

    if (isset($_GET['mID'])) $manufacturers_id = HTML::sanitize($_GET['mID']);

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    $manufacturers_name = HTML::sanitize($_POST['manufacturers_name']);

    $sql_data_array = ['manufacturers_name' => $manufacturers_name];
    $update_sql_data = ['last_modified' => 'now()'];

    $sql_data_array = array_merge($sql_data_array, $update_sql_data);

    $this->app->db->save('manufacturers', $sql_data_array, ['manufacturers_id' => (int)$manufacturers_id]);

    if (isset($_POST['delete_image'])) {
      $sql_data_array = ['manufacturers_image' => ''];
      $this->app->db->save('manufacturers', $sql_data_array, ['manufacturers_id' => (int)$manufacturers_id]);
    } elseif (isset($_POST['manufacturers_image']) && !\is_null($_POST['manufacturers_image']) && ($_POST['manufacturers_image'] != 'none') && !empty($_POST['manufacturers_image'])) {
      $manufacturers_image = $_POST['manufacturers_image'];

      if (!empty($manufacturers_image) && !\is_null($manufacturers_image)) {
        $manufacturers_image = $CLICSHOPPING_Wysiwyg::getWysiwygImageAlone($manufacturers_image);
      }

      $sql_data_array = ['manufacturers_image' => $manufacturers_image];
      $this->app->db->save('manufacturers', $sql_data_array, ['manufacturers_id' => (int)$manufacturers_id]);
    }

    $languages = $CLICSHOPPING_Language->getLanguages();

    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $manufacturers_url_array = HTML::sanitize($_POST['manufacturers_url']);
      $manufacturer_description_array = $_POST['manufacturer_description'];
      $manufacturer_seo_title_array = HTML::sanitize($_POST['manufacturer_seo_title']);
      $manufacturer_seo_description_array = HTML::sanitize($_POST['manufacturer_seo_description']);
      $manufacturer_seo_keyword_array = HTML::sanitize($_POST['manufacturer_seo_keyword']);
      $language_id = $languages[$i]['id'];

      $sql_data_array = [
        'manufacturers_url' =>$manufacturers_url_array[$language_id],
        'manufacturer_description' => $manufacturer_description_array[$language_id],
        'manufacturer_seo_title' =>$manufacturer_seo_title_array[$language_id],
        'manufacturer_seo_description' =>$manufacturer_seo_description_array[$language_id],
        'manufacturer_seo_keyword' =>$manufacturer_seo_keyword_array[$language_id]
      ];

      $this->app->db->save('manufacturers_info', $sql_data_array, [
         'manufacturers_id' => (int)$manufacturers_id,
         'languages_id' => (int)$language_id
        ]
      );
    }

    $CLICSHOPPING_Hooks->call('Manufacturers', 'Update');

    $this->app->redirect('Manufacturers&page=' . $page . '&mID=' . $manufacturers_id);
  }
}
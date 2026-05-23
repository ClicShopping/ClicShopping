<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Sites\ClicShoppingAdmin\Pages\Home\Actions\ManufacturersPopUp;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Save extends \ClicShopping\OM\Domains\PagesActionsAbstract
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

    if (!empty($_POST['manufacturers_name'])) {
      $manufacturers_name = HTML::sanitize($_POST['manufacturers_name']);

      if (isset($_POST['manufacturers_image']) && !\is_null($_POST['manufacturers_image']) && ($_POST['manufacturers_image'] != 'none') && (!isset($_POST['delete_image']))) {
        $manufacturers_image = $_POST['manufacturers_image'];

        $manufacturers_image = $CLICSHOPPING_Wysiwyg::getWysiwygImageAlone($manufacturers_image);
      } else {
        $manufacturers_image = 'null';
      }

      $sql_data_array = ['manufacturers_name' => $manufacturers_name];

      if (!\is_null($manufacturers_image)) {
        $insert_image_sql_data = ['manufacturers_image' => $manufacturers_image];
        $sql_data_array = array_merge($sql_data_array, $insert_image_sql_data);
      }

      $insert_sql_data = ['date_added' => 'now()'];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $this->app->db->save('manufacturers', $sql_data_array);

      $manufacturers_id = $this->app->db->lastInsertId();

      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $manufacturers_url_array = $_POST['manufacturers_url'];
        $language_id = $languages[$i]['id'];

        $sql_data_array = ['manufacturers_id' => $manufacturers_id];


        $insert_sql_data = ['manufacturers_url' => HTML::sanitize($manufacturers_url_array[$language_id]),
          'languages_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $this->app->db->save('manufacturers_info', $sql_data_array);

        $CLICSHOPPING_Hooks->call('Manufacturer', 'Insert');
      }

      Cache::clear('manufacturers');

      echo 'Success';
    } else {
      echo 'Error <br />';
    }

    exit;
  }
}
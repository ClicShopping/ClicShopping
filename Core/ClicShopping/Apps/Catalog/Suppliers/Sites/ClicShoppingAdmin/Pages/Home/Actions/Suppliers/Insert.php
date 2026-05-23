<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Catalog\Suppliers\Sites\ClicShoppingAdmin\Pages\Home\Actions\Suppliers;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Suppliers');
  }

  public function execute()
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

    $suppliers_name = HTML::sanitize($_POST['suppliers_name']);

    if (isset($_POST['suppliers_manager'])) {
      $suppliers_manager = HTML::sanitize($_POST['suppliers_manager']);
    } else {
      $suppliers_manager = '';
    }

    if (isset($_POST['suppliers_phone'])) {
      $suppliers_phone = HTML::sanitize($_POST['suppliers_phone']);
    } else {
      $suppliers_phone = '';
    }

    if (isset($_POST['suppliers_email_address'])) {
      $suppliers_email_address = HTML::sanitize($_POST['suppliers_email_address']);
    } else {
      $suppliers_email_address = '';
    }

    if (isset($_POST['suppliers_fax'])) {
      $suppliers_fax = HTML::sanitize($_POST['suppliers_fax']);
    } else {
      $suppliers_fax = '';
    }

    if (isset($_POST['suppliers_address'])) {
      $suppliers_address = HTML::sanitize($_POST['suppliers_address']);
    } else {
      $suppliers_address = '';
    }

    if (isset($_POST['suppliers_suburb'])) {
      $suppliers_suburb = HTML::sanitize($_POST['suppliers_suburb']);
    } else {
      $suppliers_suburb = '';
    }

    if (isset($_POST['suppliers_postcode'])) {
      $suppliers_postcode = HTML::sanitize($_POST['suppliers_postcode']);
    } else {
      $suppliers_postcode = '';
    }

    if (isset($_POST['suppliers_city'])) {
      $suppliers_city = HTML::sanitize($_POST['suppliers_city']);
    } else {
      $suppliers_city = '';
    }

    $suppliers_states = HTML::sanitize($_POST['suppliers_states']);
    $suppliers_country_id = HTML::sanitize($_POST['suppliers_country_id']);

    if (isset($_POST['suppliers_image'])) {
      $suppliers_image = $_POST['suppliers_image'];
    } else {
      $suppliers_image = '';
    }

    if (isset($_POST['suppliers_notes'])) {
      $suppliers_notes = HTML::sanitize($_POST['suppliers_notes']);
    } else {
      $suppliers_notes = '';
    }

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_POST['suppliers_image']) && !\is_null($_POST['suppliers_image']) && !empty($_POST['suppliers_image']) && (!isset($_POST['delete_image']))) {
      $suppliers_image = $CLICSHOPPING_Wysiwyg::getWysiwygImageAlone($suppliers_image);
    } else {
      $suppliers_image = null;
    }

    $sql_data_array = [
      'suppliers_name' => $suppliers_name,
      'suppliers_manager' => $suppliers_manager,
      'suppliers_phone' => $suppliers_phone,
      'suppliers_email_address' => $suppliers_email_address,
      'suppliers_fax' => $suppliers_fax,
      'suppliers_address' => $suppliers_address,
      'suppliers_suburb' => $suppliers_suburb,
      'suppliers_postcode' => $suppliers_postcode,
      'suppliers_city' => $suppliers_city,
      'suppliers_states' => $suppliers_states,
      'suppliers_country_id' => (int)$suppliers_country_id,
      'suppliers_notes' => $suppliers_notes
    ];

    if ($suppliers_image != 'null') {
      $insert_image_sql_data = ['suppliers_image' => $suppliers_image];
      $sql_data_array = array_merge($sql_data_array, $insert_image_sql_data);
    }

    $insert_sql_data = ['date_added' => 'now()'];

    $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

    $this->app->db->save('suppliers', $sql_data_array);

    $suppliers_id = $this->app->db->lastInsertId();

    $languages = $CLICSHOPPING_Language->getLanguages();

    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $suppliers_url_array = $_POST['suppliers_url'];
      $language_id = $languages[$i]['id'];

      $sql_data_array = ['suppliers_url' => HTML::sanitize($suppliers_url_array[$language_id])];

      $insert_sql_data = [
        'suppliers_id' => (int)$suppliers_id,
        'languages_id' => (int)$language_id
      ];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $this->app->db->save('suppliers_info', $sql_data_array);
    }

    $CLICSHOPPING_Hooks->call('Suppliers', 'Insert');

    $this->app->redirect('Suppliers&page=' . $page . '&mID=' . $suppliers_id);
  }
}
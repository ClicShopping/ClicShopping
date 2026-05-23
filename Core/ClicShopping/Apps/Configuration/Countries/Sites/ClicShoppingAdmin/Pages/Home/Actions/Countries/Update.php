<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Countries\Sites\ClicShoppingAdmin\Pages\Home\Actions\Countries;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Countries');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    $countries_id = HTML::sanitize($_GET['cID']);
    $countries_name = HTML::sanitize($_POST['countries_name']);
    $countries_iso_code_2 = HTML::sanitize($_POST['countries_iso_code_2']);
    $countries_iso_code_3 = HTML::sanitize($_POST['countries_iso_code_3']);
    $address_format_id = HTML::sanitize($_POST['address_format_id']);
    $countries_status = 1;

    $sql_array = [
      'countries_name' => $countries_name,
      'countries_iso_code_2' => $countries_iso_code_2,
      'countries_iso_code_3' => $countries_iso_code_3,
      'address_format_id' => (int)$address_format_id,
      'status' => (int)$countries_status
    ];

    $this->app->db->save('countries', $sql_array, ['countries_id' => (int)$countries_id]);

    $this->app->redirect('Countries&page=' . $page . '&cID=' . $countries_id);
  }
}
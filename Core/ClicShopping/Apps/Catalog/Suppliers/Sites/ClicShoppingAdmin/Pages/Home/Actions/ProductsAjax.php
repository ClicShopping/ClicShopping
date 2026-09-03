<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ProductsAjax extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected $file = null;
  protected bool $use_site_template = false;

  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!\defined('CLICSHOPPING_APP_SUPPLIERS_CS_STATUS') || CLICSHOPPING_APP_SUPPLIERS_CS_STATUS == 'False') {
      http_response_code(400);
      exit;
    }

    if (!isset($_GET['q'])) {
      http_response_code(400);
      exit;
    }

    $terms = HTML::sanitize(mb_strtolower($_GET['q']));

    $Qcheck = $CLICSHOPPING_Db->prepare('SELECT DISTINCT suppliers_id AS id, 
                                                          suppliers_name AS name 
                                          FROM :table_suppliers 
                                          WHERE suppliers_name 
                                          LIKE :terms LIMIT 10'
    );
    $Qcheck->bindValue(':terms', '%' . $terms . '%');
    $Qcheck->execute();

    $list = $Qcheck->rowCount();

    if ($list > 0) {
      $array = [];

      while ($value = $Qcheck->fetch()) {
        $array[] = $value;
      }

      // JSON-encode the response
      $json_response = json_encode($array); // Return the JSON Array

      header('Content-Type: application/json');
      echo $json_response;
      exit;
    } else {
      // Return an empty array if no results are found
      header('Content-Type: application/json');
      echo json_encode([]);
      exit;
    }
  }
}
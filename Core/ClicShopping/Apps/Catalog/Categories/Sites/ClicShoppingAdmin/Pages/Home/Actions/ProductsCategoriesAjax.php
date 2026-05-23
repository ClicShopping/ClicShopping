<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class ProductsCategoriesAjax extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected $file = null;
  protected bool $use_site_template = false;

  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_CATEGORIES_CT_STATUS') || CLICSHOPPING_APP_CATEGORIES_CT_STATUS == 'False') {
      http_response_code(400);
      exit;
    }

    $CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');

    $array = $CLICSHOPPING_CategoriesAdmin->getCategoryTree();

# JSON-encode the response
    $json_response = json_encode($array); //Return the JSON Array

# Return the response
    echo $json_response;
    exit;
  }
}

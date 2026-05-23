<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\TaxClass\Sites\ClicShoppingAdmin\Pages\Home\Actions\TaxClass;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('TaxClass');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $tax_class_title = HTML::sanitize($_POST['tax_class_title']);
    $tax_class_description = HTML::sanitize($_POST['tax_class_description']);

    $this->app->db->save('tax_class', [
        'tax_class_title' => $tax_class_title,
        'tax_class_description' => $tax_class_description,
        'date_added' => 'now()'
      ]
    );

    $this->app->redirect('TaxClass&page=' . $page);
  }
}
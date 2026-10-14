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

class DeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('TaxClass');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $tax_class_id = HTML::sanitize($_GET['tID']);

    $this->app->db->delete('tax_class', ['tax_class_id' => (int)$tax_class_id]);

    $this->app->redirect('TaxClass&page=' . $page);
  }
}
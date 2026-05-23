<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Countries\Sites\ClicShoppingAdmin\Pages\Home\Actions\Countries;

use ClicShopping\OM\Registry;

class UpdateAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Countries');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (!\is_null($_POST['selected']) && isset($_POST['selected']) && \is_array($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        $Qupdate = $this->app->db->prepare('update :table_countries
                                               set status = 0
                                               where countries_id = :countries_id
                                              ');
        $Qupdate->bindInt(':countries_id', $id);
        $Qupdate->execute();
      }
    }

    $this->app->redirect('Countries&page=' . $page);
  }
}
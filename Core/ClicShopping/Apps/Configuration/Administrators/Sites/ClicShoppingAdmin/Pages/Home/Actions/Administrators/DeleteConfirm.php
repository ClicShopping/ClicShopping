<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Administrators\Sites\ClicShoppingAdmin\Pages\Home\Actions\Administrators;

use ClicShopping\OM\Registry;

class DeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Administrators');
  }

  public function execute()
  {

    $id = (int)$_GET['aID'];

    $Qcheck = $this->app->db->get('administrators', ['id', 'user_name'], ['id' => $id]);

    if ($_SESSION['admin']['id'] === $Qcheck->valueInt('id')) {
      unset($_SESSION['admin']);
    }

    $this->app->db->delete('administrators', ['id' => $id]);

    $this->app->redirect('Administrators');
  }
}
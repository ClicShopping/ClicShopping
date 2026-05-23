<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Tools\AdministratorMenu\Sites\ClicShoppingAdmin\Pages\Home\Actions\AdministratorMenu;

use ClicShopping\Apps\Tools\AdministratorMenu\Classes\ClicShoppingAdmin\AdministratorMenu;
use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class DeleteCategoryConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
  protected $cPath;
  protected $Id;

  public function __construct()
  {
    $this->app = Registry::get('AdministratorMenu');

    $this->Id = HTML::sanitize($_GET['id']);
    $this->cPath = HTML::sanitize($_GET['cPath']);
  }

  public function execute()
  {

    if (isset($this->Id)) {
      $categories = AdministratorMenu::getAdministratorMenuCategoryTree($this->Id, '', '0', '', true);

      for ($i = 0, $n = \count($categories); $i < $n; $i++) {
        AdministratorMenu::getRemoveAdministratorMenuCategory($categories[$i]['id']);
      }
    }

    Cache::clear('menu-administrator');

    $this->app->redirect('AdministratorMenu&cPath=' . $this->cPath);
  }
}
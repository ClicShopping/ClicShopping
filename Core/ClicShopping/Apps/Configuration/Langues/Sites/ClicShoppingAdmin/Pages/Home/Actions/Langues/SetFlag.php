<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Langues\Sites\ClicShoppingAdmin\Pages\Home\Actions\Langues;

use ClicShopping\Apps\Configuration\Langues\Classes\ClicShoppingAdmin\Status;
use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Langues');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    Status::getLanguageStatus($_GET['lid'], $_GET['flag']);

// Verifie si les status ne sont pas tous en off
    $QcountLanguages = $this->app->db->prepare('select count(status) as status
                                                  from :table_languages
                                                  where status = 1
                                                ');
    $QcountLanguages->execute();

    if ($QcountLanguages->value('status') == 0) {
      $Qupdate = $this->app->db->prepare('update :table_languages
                                            set status = 1
                                            where languages_id = :languages_id
                                          ');
      $Qupdate->bindInt(':languages_id', $_GET['lid']);
      $Qupdate->execute();
    }

    Cache::clear('languages-system-shop');
    Cache::clear('languages-system-admin');

    $this->app->redirect('Langues&page' . $page . '&lID=' . $_GET['lid']);
  }
}
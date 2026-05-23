<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Tools\DefineLanguage\Sites\ClicShoppingAdmin\Pages\Home\Actions\DefineLanguage;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class TableReset extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('DefineLanguage');
  }

  public function execute()
  {
// reset all definitions
    $this->app->db->exec('truncate :table_languages_definitions');

// reset cache
    Cache::clear('languages-defs');

    $this->app->redirect('DefineLanguage');
  }
}
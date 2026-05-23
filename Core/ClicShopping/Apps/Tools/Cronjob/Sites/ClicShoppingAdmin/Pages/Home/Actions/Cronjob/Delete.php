<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Tools\Cronjob\Sites\ClicShoppingAdmin\Pages\Home\Actions\Cronjob;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  private int $cronId;

  public function __construct()
  {
    $this->app = Registry::get('Cronjob');
    $this->cronId = HTML::sanitize($_GET['cronId']);
  }

  public function execute()
  {
    if (isset($_GET['Delete'])) {
      $this->app->db->delete('cron', ['cron_id' => (int)$this->cronId]);
    }

    $this->app->redirect('Cronjob');
  }
}
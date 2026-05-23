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

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function __construct()
  {
    $this->app = Registry::get('Cronjob');
  }

  /**
   *
   */
  private function Update(): void
  {
    $cron_id = HTML::sanitize($_GET['cronId']);
    $code = HTML::sanitize($_POST['code']);
    $cycle = HTML::sanitize($_POST['cycle']);
    $action = HTML::sanitize($_POST['action']);
    $description = HTML::sanitize($_POST['description']);

    if (isset($_POST['status'])) {
      $status = HTML::sanitize($_POST['status']);
    } else {
      $status = 0;
    }

    $sql_data_array = [
      'code' => $code,
      'cycle' => $cycle,
      'action' => $action,
      'status' => $status,
      'description' => $description,
      'date_modified' => 'now()'
    ];

    $update_array = [
      'cron_id' => $cron_id
    ];

    $this->app->db->save('cron', $sql_data_array, $update_array);
  }

  public function execute()
  {
    if (isset($_GET['Update'])) {
      $this->Update();
    }

    $this->app->redirect('Cronjob');
  }
}
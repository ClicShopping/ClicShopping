<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Cronjob\Sites\ClicShoppingAdmin\Pages\Home\Actions\Cronjob;

use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron;
use ClicShopping\OM\Registry;

class RunAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected string $code;

  public function __construct()
  {
    $this->app = Registry::get('Cronjob');
    $this->hooks = Registry::get('Hooks');
  }

  public function execute()
  {
    $time = time();

    $results = Cron::getCrons(null, null);

    foreach ($results as $result) {
      if ($result['status'] == 1 && (strtotime('+1 ' . $result['cycle'], strtotime($result['date_modified'])) < ($time + 10))) {
        Cron::updateCron($result['cron_id']);

        $this->hooks->call('Cronjob', 'Process');
      }
    }

    $this->app->redirect('Cronjob');
  }
}
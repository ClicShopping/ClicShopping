<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Cronjob\Sites\ClicShoppingAdmin\Pages\Home\Actions\Cronjob;

use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Run extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  protected string $code;
  protected mixed $app;
  protected int $id;
  protected mixed $hooks;

  public function __construct()
  {
    $this->app = Registry::get('Cronjob');
    $this->id = HTML::sanitize($_GET['cronId']);
    $this->hooks = Registry::get('Hooks');
  }

  public function execute()
  {
    if (isset($this->id)) {
      $time = time();

      $results = Cron::getCrons(null, $this->id);

      foreach ($results as $result) {
        if (strtotime('+1 ' . $result['cycle'], strtotime($result['date_modified'])) < ($time + 10)) {
          Cron::updateCron($result['cron_id']);

          $this->hooks->call('Cronjob', 'Process');
        }
      }
    }

    $this->app->redirect('Cronjob');
  }
}
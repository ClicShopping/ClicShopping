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

          // Generic Process hook + optional per-cron named hook (row `action`).
          // Running a single cron by id still sets $_GET['cronId'], so each hook
          // self-gates to the right code; the action dispatch reaches the named
          // hook (e.g. reputation) that Process alone would never call.
          $this->hooks->call('Cronjob', 'Process');

          $action = trim((string)($result['action'] ?? ''));
          if ($action !== '' && $action !== 'Process') {
            $this->hooks->call('Cronjob', $action);
          }
        }
      }
    }

    $this->app->redirect('Cronjob');
  }
}
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

        // Target the current (due, enabled) row so each App's Process self-gates
        // on cron_id == $_GET['cronId'] and only this cron's concern runs (F2).
        $_GET['cronId'] = (string)(int)$result['cron_id'];

        // Generic Process hook (every App's self-gated cron) + optional per-cron
        // named hook routed by the row's `action` column. See CJ::init() for the
        // rationale; additive, so rows whose action mirrors the code stay no-ops.
        $this->hooks->call('Cronjob', 'Process');

        $action = trim((string)($result['action'] ?? ''));
        if ($action !== '' && $action !== 'Process') {
          $this->hooks->call('Cronjob', $action);
        }
      }
    }

    $this->app->redirect('Cronjob');
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Cronjob\Sites\Shop\Pages\CJ;

use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron;
use ClicShopping\OM\Registry;

class CJ extends \ClicShopping\OM\Domains\PagesAbstract
{
  protected ?string $file = null;
  protected bool $use_site_template = false;

  /**
   * Initializes the cron job execution process by fetching cron job records,
   * validating their status and execution cycle, and updating them if necessary.
   * Also triggers the associated hooks for further processing.
   *
   * @return void
   */
  protected function init()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $time = time();

    $results = Cron::getCrons(null, null);

    foreach ($results as $result) {
      if ($result['status'] == 1 && (strtotime('+1 ' . $result['cycle'], strtotime($result['date_modified'])) < ($time + 10))) {
        Cron::updateCron($result['cron_id']);

        // Target the current (due, enabled) row so every App's Cronjob/Process
        // self-gates on cron_id == $_GET['cronId'] and only THIS cron's concern
        // runs. Without it the broadcast runs every concern on every due row,
        // ignoring their own status/cycle (F2). Same technique as Run.php.
        $_GET['cronId'] = (string)(int)$result['cron_id'];

        // Always dispatch the generic Process hook (every App's self-gated cron).
        $CLICSHOPPING_Hooks->call('Cronjob', 'Process');

        // Additionally dispatch a per-cron named hook when the row's `action`
        // column names one (e.g. action = 'ReputationUpdateProcessor'). This is
        // the original intent of the `action` column and lets an App expose
        // several independent crons without funnelling them through Process.
        // No-op for rows whose action just mirrors the code (no such hook), so
        // existing crons keep working unchanged.
        $action = trim((string)($result['action'] ?? ''));
        if ($action !== '' && $action !== 'Process') {
          $CLICSHOPPING_Hooks->call('Cronjob', $action);
        }
      }
    }
  }
}

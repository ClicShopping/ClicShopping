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

        $CLICSHOPPING_Hooks->call('Cronjob', 'Process');
      }
    }
  }
}

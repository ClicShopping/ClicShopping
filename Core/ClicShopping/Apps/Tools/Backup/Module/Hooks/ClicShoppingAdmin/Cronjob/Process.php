<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Backup\Module\Hooks\ClicShoppingAdmin\Cronjob;

use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;

use ClicShopping\Apps\Tools\Backup\Classes\ClicShoppingAdmin\Backup;

class Process implements HooksInterface
{
  /**
   * Executes a cron job for handling backup operations.
   * This method checks for a 'cronId' in the request, updates the cron status,
   * and performs a backup if the conditions are met.
   *
   * @return void
   */
  private static function cronJob(): void
  {
    $cron_id_gdpr = Cron::getCronCode('backup');

    if (isset($_GET['cronId'])) {
      $cron_id = HTML::sanitize($_GET['cronId']);

      Cron::updateCron($cron_id);

      if (isset($cron_id) && $cron_id_gdpr == $cron_id) {
        Backup::backupNow();
      }
    } else {
      Cron::updateCron($cron_id_gdpr);

      if (isset($cron_id_gdpr)) {
        Backup::backupNow();
      }
    }
  }

  /**
   * Executes the main functionality by calling the cronJob method.
   *
   * @return void
   */
  public function execute()
  {
    static::cronJob();
  }
}
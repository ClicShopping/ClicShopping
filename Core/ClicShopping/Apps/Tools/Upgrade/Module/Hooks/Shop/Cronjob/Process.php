<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Module\Hooks\Shop\Cronjob;

use ClicShopping\OM\HTML;

use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron;
use ClicShopping\Apps\Tools\Upgrade\Classes\ClicShoppingAdmin\Marketplace;
use ClicShopping\OM\Interfaces\HooksInterface;

class Process implements HooksInterface
{
  /**
   * Constructor method for the class.
   *
   * @return void
   */
  public function __construct()
  {
  }

  /**
   *
   * Executes the cron job process for the marketplace.
   * It checks if a specific cron ID is present in the query parameters and updates the respective cron.
   * If no specific cron ID is provided, it defaults to updating and executing the marketplace cron job.
   *
   * @return void
   */
  private function cronJob(): void
  {
    $cron_id_marketplace = Cron::getCronCode('marketplace');

    if (isset($_GET['cronId'])) {
      $cron_id = HTML::sanitize($_GET['cronId']);

      Cron::updateCron($cron_id);

      if (isset($cron_id) && $cron_id_marketplace == $cron_id) {
        Marketplace::Cronjob();
      }
    } else {
      Cron::updateCron($cron_id_marketplace);

      if (isset($cron_id_marketplace)) {
        Marketplace::Cronjob();
      }
    }
  }

  /**
   *
   * @return void
   */
  public function execute()
  {
    $this->cronJob();
  }
}
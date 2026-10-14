<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Module\Hooks\ClicShoppingAdmin\Cronjob;

use ClicShopping\Apps\Customers\Gdpr\Gdpr as GdprApp;
use ClicShopping\Apps\Customers\Gdpr\Classes\ClicShoppingAdmin\Gdpr;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

class Process implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Gdpr application component.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Gdpr')) {
      Registry::set('Gdpr', new GdprApp());
    }

    $this->app = Registry::get('Gdpr');
  }

  /**
   * Runs the GDPR retention purge from the admin (manual launch). The logic lives in
   * Gdpr::runCron() so the admin and Shop (external) cron entry points stay in sync.
   *
   * @return void
   */
  public function execute()
  {
    Gdpr::runCron();
  }
}

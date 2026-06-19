<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\ClicShoppingAdmin\Cronjob;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Cron;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron as Cronjob;

class Process implements HooksInterface
{
  public mixed $app;
  private mixed $cron;

  public function __construct()
  {
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGptApp());
    }
    $this->app = Registry::get('ChatGpt');

    if (!Registry::exists('Cron')) {
      Registry::set('Cron', new Cron());
    }
    $this->cron = Registry::get('Cron');
  }

  /**
   * Clears the currency cache.
   *
   * This method clears the cache for currencies by calling the appropriate method
   * from the ClicShopping application instance.
   *
   * @return void
   */
  public function updateAllEmbeddings(): void
  {
    $this->cron->updateAllEmbedding();
  }

  /**
   * Handles the execution of a cron job related to currency updates.
   *
   * This method checks for a 'cronId' parameter in the GET request, validates it,
   * and performs currency updates if the 'cronId' matches the predefined cron code.
   * If no 'cronId' is provided in the request, the method executes the update for all currencies
   * using the predefined cron ID.
   *
   * @return void
   */
  private function cronJob(): void
  {
    $cron_id_embedding = Cronjob::getCronCode('embeddings');

    if (isset($_GET['cronId'])) {
      $cron_id = HTML::sanitize($_GET['cronId']);
      
      if ($cron_id !== null && !empty($cron_id) && is_int($cron_id)) {
        Cronjob::updateCron($cron_id);

        if ($cron_id_embedding == $cron_id) {
          $this->cron->updateAllEmbeddings();
        }
      } else {
        // Log invalid cronId attempt
        error_log('Invalid cronId parameter detected: ' . (isset($_GET['cronId']) ? htmlspecialchars($_GET['cronId']) : 'empty'));
      }
    } else {
      Cronjob::updateCron($cron_id_embedding);

      if (isset($cron_id_embedding)) {
        $this->cron->updateAllEmbeddings();
      }
    }
  }

  /**
   * Executes the main process by calling the cron job and clearing the currency cache.
   *
   * @return void
   */
  public function execute()
  {
    $this->cronJob();
  }
}
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\Shop\Cronjob;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Cron;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron as Cronjob;

/**
 * Process — ChatGpt embeddings cron, Shop external entry point.
 *
 * Shop-side twin of the ClicShoppingAdmin/Cronjob/Process hook so the
 * `embeddings` cron (clic_cron code 'embeddings') is reachable through the
 * external ?cronjob&runall URL (which runs on the Shop site), not only from the
 * admin "Run" button. The reputation crons are dispatched separately via the
 * row `action` column (see CJ::init()), so this hook stays embeddings-only.
 */
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
   * Self-gates on the 'embeddings' cron code: runs only when triggered without
   * a specific cronId (full sweep) or when the passed cronId matches.
   *
   * @return void
   */
  private function cronJob(): void
  {
    $cron_id_embedding = Cronjob::getCronCode('embeddings');

    if (isset($_GET['cronId'])) {
      $cron_id = HTML::sanitize($_GET['cronId']);

      if ($cron_id !== null && !empty($cron_id) && is_numeric($cron_id)) {
        $cron_id = (int)$cron_id;
        Cronjob::updateCron($cron_id);

        if ($cron_id_embedding == $cron_id) {
          $this->cron->updateAllEmbeddings();
        }
      } else {
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
   * Entry point called by the framework.
   *
   * @return void
   */
  public function execute()
  {
    $this->cronJob();
  }
}

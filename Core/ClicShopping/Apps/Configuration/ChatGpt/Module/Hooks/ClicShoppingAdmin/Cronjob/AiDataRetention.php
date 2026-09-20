<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/*
 * AI journal retention cron job (AIACT-1, ex-OBS-1)
 * Recommended schedule: once per day
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\ClicShoppingAdmin\Cronjob;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\AiDataRetention as RetentionRunner;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron as Cronjob;

/**
 * Deletes AI agent journals older than the configured window.
 *
 * Gated on CLICSHOPPING_APP_CHATGPT_ASY_DATA_RETENTION_STATUS, on by default: the job deletes
 * nothing until the ai_data_retention cron row is enabled.
 */
class AiDataRetention implements HooksInterface
{
  /**
   * ChatGpt App instance
   * @var ChatGptApp
   */
  public mixed $app;

  /**
   * Initializes the cron job process
   */
  public function __construct()
  {
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGptApp());
    }

    $this->app = Registry::get('ChatGpt');
  }

  /**
   * Executes the main process for the cron job
   *
   * @return void
   */
  public function execute(): void
  {
    $this->cronJob();
  }

  /**
   * Runs the purge when the operator enabled it
   *
   * @return void
   */
  private function runRetention(): void
  {
    try {
      if (!RetentionRunner::isEnabled()) {
        error_log('[AiDataRetention] disabled in configuration - skipping');
        return;
      }

      $days = RetentionRunner::retentionDays();

      if ($days <= 0) {
        error_log('[AiDataRetention] retention window is not a positive number of days - skipping');
        return;
      }

      $deleted = (new RetentionRunner())->run($days);
      $total = array_sum($deleted);

      foreach ($deleted as $table => $count) {
        error_log(sprintf('[AiDataRetention] %s: %d row(s) deleted', $table, $count));
      }

      error_log(sprintf('[AiDataRetention] completed: %d row(s) deleted over %d day(s) retention', $total, $days));

    } catch (\Throwable $e) {
      error_log('[AiDataRetention] failed with error: ' . $e->getMessage());
    }
  }

  /**
   * Handles the execution of the cron job, self-gated on its own cron code
   *
   * @return void
   */
  private function cronJob(): void
  {
    $cron_id_retention = Cronjob::getCronCode('ai_data_retention');

    if (isset($_GET['cronId'])) {
      $cron_id = HTML::sanitize($_GET['cronId']);

      if (!empty($cron_id) && is_numeric($cron_id)) {
        $cron_id = (int)$cron_id;
        Cronjob::updateCron($cron_id);

        if ($cron_id_retention == $cron_id) {
          $this->runRetention();
        }
      } else {
        error_log('[AiDataRetention] invalid cronId parameter detected');
      }
    } else {
      Cronjob::updateCron($cron_id_retention);
      $this->runRetention();
    }
  }
}

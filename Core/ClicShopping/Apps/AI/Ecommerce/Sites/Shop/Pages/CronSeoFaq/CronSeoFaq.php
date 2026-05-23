<?php
/**
 * CronSeoFaq - External Cron Job Access Point
 *
 * This page allows external cron systems to trigger the SEO/FAQ batch processing.
 * Access: https://yoursite.com/index.php?CronSeoFaq
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Sites\Shop\Pages\CronSeoFaq;

use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron;
use ClicShopping\OM\Registry;

/**
 * CronSeoFaq Page
 *
 * External access point for the SEO/FAQ batch processing cron job.
 * This allows the cron to be triggered via HTTP request.
 */
class CronSeoFaq extends \ClicShopping\OM\Domains\PagesAbstract
{
  protected ?string $file = null;
  protected bool $use_site_template = false;

  /**
   * Initialize and execute the SEO/FAQ batch processing cron job
   *
   * This method checks if the cron job is enabled and due to run,
   * then triggers the associated hook for processing.
   *
   * @return void
   */
  protected function init()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $time = time();

    // Get the SEO/FAQ batch processor cron job
    $cronCode = 'seo_faq_batch_processor';
    $cronId = Cron::getCronCode($cronCode);

    if ($cronId > 0) {
      $results = Cron::getCrons(null, $cronId);

      foreach ($results as $result) {
        // Check if cron is enabled and due to run
        if ($result['status'] == 1 && (strtotime('+1 ' . $result['cycle'], strtotime($result['date_modified'])) < ($time + 10))) {
          // Update the cron last run time
          Cron::updateCron($result['cron_id']);

          // Trigger the cron job hook
          $CLICSHOPPING_Hooks->call('Ecommerce', 'ProcessSeoFaqBatch');

          echo "SEO/FAQ batch processing cron job executed successfully at " . date('Y-m-d H:i:s') . "\n";
        } else {
          if ($result['status'] == 0) {
            echo "SEO/FAQ batch processing cron job is disabled\n";
          } else {
            echo "SEO/FAQ batch processing cron job is not due to run yet\n";
            echo "Last run: " . $result['date_modified'] . "\n";
            echo "Next run: " . date('Y-m-d H:i:s', strtotime('+1 ' . $result['cycle'], strtotime($result['date_modified']))) . "\n";
          }
        }
      }
    } else {
      echo "SEO/FAQ batch processing cron job not found in database\n";
      echo "Please run the SQL migration: 2026_05_03_add_seo_faq_batch_cron.sql\n";
    }
  }
}

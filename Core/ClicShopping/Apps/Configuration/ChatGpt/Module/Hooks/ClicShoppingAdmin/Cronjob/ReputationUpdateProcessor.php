<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/*
 * Reputation update queue processor cron job
 * Recommended schedule: Every 5 minutes
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\ClicShoppingAdmin\Cronjob;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\EvaluationMonitor;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationTracker;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron as Cronjob;

/**
 * Handles the execution of the Reputation Update Queue Processor cron job.
 * 
 * This class serves as the entry point for the scheduled reputation update task,
 * which processes pending reputation update jobs from the queue.
 * 
 * Requirements: 15.1, 15.3
 */
class ReputationUpdateProcessor implements \ClicShopping\OM\Modules\HooksInterface
{
    /**
     * ChatGpt App instance
     * @var ChatGptApp
     */
    public mixed $app;
    
    /**
     * Evaluation Monitor instance
     * @var EvaluationMonitor
     */
    private EvaluationMonitor $evaluationMonitor;
    
    /**
     * Reputation Tracker instance
     * @var ReputationTracker
     */
    private ReputationTracker $reputationTracker;
    
    /**
     * Initializes the cron job process
     */
    public function __construct()
    {
        if (!Registry::exists('ChatGpt')) {
            Registry::set('ChatGpt', new ChatGptApp());
        }
        $this->app = Registry::get('ChatGpt');
        
        if (!Registry::exists('ReputationTracker')) {
            Registry::set('ReputationTracker', new ReputationTracker());
        }
        $this->reputationTracker = Registry::get('ReputationTracker');
        
        if (!Registry::exists('EvaluationMonitor')) {
            Registry::set('EvaluationMonitor', new EvaluationMonitor($this->reputationTracker, asyncEnabled: true));
        }
        $this->evaluationMonitor = Registry::get('EvaluationMonitor');
    }
    
    /**
     * Executes the main process for the cron job
     * 
     * This is the entry point called by the framework.
     * 
     * @return void
     */
    public function execute(): void
    {
        $this->cronJob();
    }
    
    /**
     * Handles the execution of the cron job
     *
     * This method checks for a 'cronId' parameter, validates it, and if it matches
     * the reputation update processor cron code, it triggers the processing logic.
     *
     * @return void
     */
    private function cronJob(): void
    {
        $cron_id_reputation_update = Cronjob::getCronCode('reputationUpdateProcessor');

        if (isset($_GET['cronId'])) {
            $cron_id = HTML::sanitize($_GET['cronId']);

            if ($cron_id !== null && !empty($cron_id) && is_numeric($cron_id)) {
                $cron_id = (int)$cron_id;
                Cronjob::updateCron($cron_id);

                if ($cron_id_reputation_update == $cron_id) {
                    $this->runReputationUpdateProcessor();
                }
            } else {
                // Log invalid cronId attempt for security monitoring
                error_log('[ReputationUpdateProcessor] Invalid cronId parameter detected: ' .
                         (isset($_GET['cronId']) ? htmlspecialchars($_GET['cronId']) : 'empty'));
            }
        } else {
            Cronjob::updateCron($cron_id_reputation_update);

            if (isset($cron_id_reputation_update)) {
                $this->runReputationUpdateProcessor();
            }
        }
    }
    
    /**
     * Runs the reputation update queue processor
     *
     * This method processes pending reputation update jobs from the queue.
     *
     * Requirements: 15.1, 15.3
     *
     * @return void
     */
    private function runReputationUpdateProcessor(): void
    {
        try {
            error_log('[ReputationUpdateProcessor] Starting queue processing');

            // Configuration
            $batchSize = 50; // Process up to 50 jobs per run
            $maxAttempts = 3; // Maximum retry attempts

            // Process pending jobs
            $results = $this->evaluationMonitor->processPendingJobs($batchSize, $maxAttempts);

            // Log results
            error_log(sprintf(
                '[ReputationUpdateProcessor] Completed: processed=%d, failed=%d, skipped=%d, time=%dms',
                $results['processed'],
                $results['failed'],
                $results['skipped'],
                $results['processing_time_ms']
            ));

            if (isset($results['error'])) {
                error_log('[ReputationUpdateProcessor] Error: ' . $results['error']);
            }

            // Cleanup old completed jobs (keep last 7 days)
            if ($results['processed'] > 0) {
                $deleted = $this->evaluationMonitor->cleanupOldJobs(daysToKeep: 7);
                error_log("[ReputationUpdateProcessor] Cleaned up {$deleted} old jobs");
            }

        } catch (\Exception $e) {
            // Log any unhandled exceptions during processing
            error_log('[ReputationUpdateProcessor] Failed with error: ' . $e->getMessage());
            error_log('[ReputationUpdateProcessor] Stack trace: ' . $e->getTraceAsString());
        }
    }
}

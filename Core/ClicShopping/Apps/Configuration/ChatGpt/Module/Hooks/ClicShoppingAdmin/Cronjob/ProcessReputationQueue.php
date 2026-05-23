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
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Jobs\JobQueue;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron as Cronjob;

/**
 * Handles the execution of the Reputation Update Queue Processor cron job.
 * 
 * This class serves as the entry point for processing the asynchronous reputation
 * update job queue. It processes pending jobs with retry logic and exponential backoff.
 * 
 * Requirements: 15.1, 15.3
 */
class ProcessReputationQueue implements \ClicShopping\OM\Modules\HooksInterface
{
    /**
     * ChatGpt App instance
     * @var ChatGptApp
     */
    public mixed $app;
    
    /**
     * Job Queue instance
     * @var JobQueue
     */
    private JobQueue $jobQueue;
    
    /**
     * Initializes the cron job process
     */
    public function __construct()
    {
        if (!Registry::exists('ChatGpt')) {
            Registry::set('ChatGpt', new ChatGptApp());
        }
        $this->app = Registry::get('ChatGpt');
        
        if (!Registry::exists('ReputationJobQueue')) {
            Registry::set('ReputationJobQueue', new JobQueue());
        }
        $this->jobQueue = Registry::get('ReputationJobQueue');
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
     * Runs the reputation update queue processor
     * 
     * This method processes pending reputation update jobs from the queue.
     * Jobs are processed in batches with retry logic for transient failures.
     * 
     * Requirements: 15.1, 15.3
     * 
     * @return void
     */
    private function processReputationQueue(): void
    {
        try {
            error_log('[ProcessReputationQueue] Starting queue processing');
            
            // Configuration
            $batchSize = 50; // Process up to 50 jobs per run
            
            // Process pending jobs
            $results = $this->jobQueue->processPending($batchSize);
            
            // Log results
            error_log(sprintf(
                '[ProcessReputationQueue] Completed: success=%d, failed=%d, retried=%d',
                $results['success'],
                $results['failed'],
                $results['retried']
            ));
            
            // Get queue statistics
            $stats = $this->jobQueue->getStatistics();
            error_log(sprintf(
                '[ProcessReputationQueue] Queue stats: pending=%d, processing=%d, completed=%d, failed=%d',
                $stats['pending'],
                $stats['processing'],
                $stats['completed'],
                $stats['failed']
            ));
            
            // Cleanup old completed jobs (keep last 7 days)
            if ($results['success'] > 0) {
                $deleted = $this->jobQueue->cleanupOldJobs(7);
                if ($deleted > 0) {
                    error_log("[ProcessReputationQueue] Cleaned up {$deleted} old jobs");
                }
            }
            
            // Alert if queue is backing up
            if ($stats['pending'] > 100) {
                error_log("[ProcessReputationQueue] WARNING: Queue backlog detected - {$stats['pending']} pending jobs");
            }
            
            // Alert if high failure rate
            if ($stats['failed'] > 10 && $stats['completed'] > 0) {
                $failureRate = $stats['failed'] / ($stats['completed'] + $stats['failed']);
                if ($failureRate > 0.1) { // More than 10% failure rate
                    error_log(sprintf(
                        '[ProcessReputationQueue] WARNING: High failure rate detected - %.1f%%',
                        $failureRate * 100
                    ));
                }
            }
            
        } catch (\Exception $e) {
            // Log any unhandled exceptions during processing
            error_log('[ProcessReputationQueue] Failed with error: ' . $e->getMessage());
            error_log('[ProcessReputationQueue] Stack trace: ' . $e->getTraceAsString());
        }
    }
    
    /**
     * Handles the execution of the cron job
     * 
     * This method checks for a 'cronId' parameter, validates it, and if it matches
     * the reputation queue processor cron code, it triggers the processing logic.
     * 
     * Requirements: 15.1, 15.3
     * 
     * @return void
     */
    private function cronJob(): void
    {
        $cron_id_reputation_queue = Cronjob::getCronCode('reputation_queue');
        
        if (isset($_GET['cronId'])) {
            $cron_id = HTML::sanitize($_GET['cronId']);
            
            if ($cron_id !== null && !empty($cron_id) && is_int($cron_id)) {
                Cronjob::updateCron($cron_id);
                
                if ($cron_id_reputation_queue == $cron_id) {
                    $this->processReputationQueue();
                }
            } else {
                // Log invalid cronId attempt for security monitoring
                error_log('[ProcessReputationQueue] Invalid cronId parameter detected: ' . 
                         (isset($_GET['cronId']) ? htmlspecialchars($_GET['cronId']) : 'empty'));
            }
        } else {
            Cronjob::updateCron($cron_id_reputation_queue);
            
            if (isset($cron_id_reputation_queue)) {
                $this->processReputationQueue();
            }
        }
    }
}

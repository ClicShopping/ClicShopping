<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/*
 * Daily reputation decay cron job
 * Recommended schedule: Once per day (e.g., 2 AM)
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\ClicShoppingAdmin\Cronjob;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationDecayScheduler;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron as Cronjob;

/**
 * Handles the execution of the Reputation Decay cron job.
 * 
 * This class serves as the entry point for the scheduled reputation decay task,
 * which applies decay to all critic reputations based on their recent performance.
 * 
 * Requirements: 4.2
 */
class ReputationDecay implements HooksInterface
{
    /**
     * ChatGpt App instance
     * @var ChatGptApp
     */
    public mixed $app;
    
    /**
     * Reputation Decay Scheduler instance
     * @var ReputationDecayScheduler
     */
    private ReputationDecayScheduler $scheduler;
    
    /**
     * Initializes the cron job process
     */
    public function __construct()
    {
        if (!Registry::exists('ChatGpt')) {
            Registry::set('ChatGpt', new ChatGptApp());
        }
        $this->app = Registry::get('ChatGpt');
        
        if (!Registry::exists('ReputationDecayScheduler')) {
            Registry::set('ReputationDecayScheduler', new ReputationDecayScheduler());
        }
        $this->scheduler = Registry::get('ReputationDecayScheduler');
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
     * Runs the reputation decay scheduler
     * 
     * This method encapsulates the core logic of the decay process.
     * It checks if decay is enabled before executing.
     * 
     * @return void
     */
    private function runReputationDecay(): void
    {
        try {
            // Check if reputation decay is enabled in configuration
            if (!\ClicShopping\AI\Config\ActorCriticConfig::isReputationDecayEnabled()) {
                error_log('[ReputationDecay] Decay is disabled in configuration - skipping execution');
                return;
            }
            
            // Check if decay should run based on schedule
            if (!$this->scheduler->shouldRunDecay()) {
                error_log('[ReputationDecay] Decay not needed at this time (already run recently)');
                return;
            }
            
            error_log('[ReputationDecay] Starting reputation decay execution');
            
            // Execute decay
            $summary = $this->scheduler->executeDecay();
            
            // Log summary
            error_log(sprintf(
                '[ReputationDecay] Completed: processed=%d, updated=%d, skipped=%d, errors=%d',
                $summary['processed'],
                $summary['updated'],
                $summary['skipped'],
                $summary['errors']
            ));
            
        } catch (\Exception $e) {
            // Log any unhandled exceptions during decay execution
            error_log('[ReputationDecay] Failed with error: ' . $e->getMessage());
            error_log('[ReputationDecay] Stack trace: ' . $e->getTraceAsString());
        }
    }
    
    /**
     * Handles the execution of the cron job
     * 
     * This method checks for a 'cronId' parameter, validates it, and if it matches
     * the reputation decay cron code, it triggers the decay logic.
     * 
     * Requirements: 4.2
     * 
     * @return void
     */
    private function cronJob(): void
    {
        $cron_id_reputation_decay = Cronjob::getCronCode('reputation_decay');
        
        if (isset($_GET['cronId'])) {
            $cron_id = HTML::sanitize($_GET['cronId']);
            
            if ($cron_id !== null && !empty($cron_id) && is_numeric($cron_id)) {
                $cron_id = (int)$cron_id;
                Cronjob::updateCron($cron_id);

                if ($cron_id_reputation_decay == $cron_id) {
                    $this->runReputationDecay();
                }
            } else {
                // Log invalid cronId attempt for security monitoring
                error_log('[ReputationDecay] Invalid cronId parameter detected: ' . 
                         (isset($_GET['cronId']) ? htmlspecialchars($_GET['cronId']) : 'empty'));
            }
        } else {
            Cronjob::updateCron($cron_id_reputation_decay);
            
            if (isset($cron_id_reputation_decay)) {
                $this->runReputationDecay();
            }
        }
    }
}


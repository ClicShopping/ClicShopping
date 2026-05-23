<?php
/**
 * Batch Processing CLI Script
 * 
 * Purpose: Run batch reputation updates and cache warming
 * Created: 2026-02-04
 * Requirements: 15.3
 * 
 * Usage:
 *   php run_batch_processing.php [--reputation] [--cache] [--all]
 * 
 * Options:
 *   --reputation  Process reputation updates in batches
 *   --cache       Warm reputation cache in batches
 *   --all         Run both reputation and cache processing
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Batch\BatchReputationProcessor;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Batch\BatchCacheWarmer;

define('PAGE_PARSE_START_TIME', microtime());
define('CLICSHOPPING_BASE_DIR', __DIR__ . '/../../../../../../');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('Shop');

echo "========================================\n";
echo "Batch Processing CLI\n";
echo "========================================\n\n";

// Parse command line arguments
$options = getopt('', ['reputation', 'cache', 'all', 'help']);

if (isset($options['help']) || empty($options)) {
    echo "Usage: php run_batch_processing.php [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --reputation  Process reputation updates in batches\n";
    echo "  --cache       Warm reputation cache in batches\n";
    echo "  --all         Run both reputation and cache processing\n";
    echo "  --help        Show this help message\n\n";
    exit(0);
}

$runReputation = isset($options['reputation']) || isset($options['all']);
$runCache = isset($options['cache']) || isset($options['all']);

// Process reputation updates
if ($runReputation) {
    echo "Processing Reputation Updates\n";
    echo str_repeat("-", 60) . "\n";
    
    try {
        $processor = new BatchReputationProcessor(50);
        
        echo "Fetching pending updates...\n";
        $results = $processor->processAllPending();
        
        echo "\nResults:\n";
        echo "  Total: {$results['total']}\n";
        echo "  Success: {$results['success']}\n";
        echo "  Failed: {$results['failed']}\n";
        
        if (!empty($results['errors'])) {
            echo "\nErrors:\n";
            foreach (array_slice($results['errors'], 0, 10) as $error) {
                echo "  - $error\n";
            }
            if (count($results['errors']) > 10) {
                echo "  ... and " . (count($results['errors']) - 10) . " more errors\n";
            }
        }
        
        // Show statistics
        echo "\nStatistics:\n";
        $stats = $processor->getStatistics();
        echo "  Total Critics: {$stats['total_critics']}\n";
        echo "  Total Outcomes: {$stats['total_outcomes']}\n";
        echo "  Avg Alignment: " . number_format($stats['avg_alignment'], 3) . "\n";
        echo "  Threshold Rate: " . number_format($stats['threshold_rate'] * 100, 1) . "%\n";
        echo "  Acceptance Rate: " . number_format($stats['acceptance_rate'] * 100, 1) . "%\n";
        echo "  Batch Size: {$stats['batch_size']}\n";
        
        echo "\n✓ Reputation processing completed\n\n";
        
    } catch (Exception $e) {
        echo "\n✗ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// Warm cache
if ($runCache) {
    echo "Warming Reputation Cache\n";
    echo str_repeat("-", 60) . "\n";
    
    try {
        $warmer = new BatchCacheWarmer(100);
        
        echo "Warming cache for active critics...\n";
        $results = $warmer->warmAllActive();
        
        echo "\nResults:\n";
        echo "  Total: {$results['total']}\n";
        echo "  Success: {$results['success']}\n";
        echo "  Already Cached: {$results['cached']}\n";
        echo "  Failed: {$results['failed']}\n";
        echo "  Duration: " . number_format($results['duration_ms'], 2) . "ms\n";
        
        if (!empty($results['errors'])) {
            echo "\nErrors:\n";
            foreach (array_slice($results['errors'], 0, 10) as $error) {
                echo "  - $error\n";
            }
            if (count($results['errors']) > 10) {
                echo "  ... and " . (count($results['errors']) - 10) . " more errors\n";
            }
        }
        
        // Show statistics
        echo "\nStatistics:\n";
        $stats = $warmer->getStatistics();
        echo "  Total Reputations: {$stats['total_reputations']}\n";
        echo "  Established: {$stats['established_count']}\n";
        echo "  Establishing: {$stats['establishing_count']}\n";
        echo "  Bootstrapping: {$stats['bootstrapping_count']}\n";
        echo "  Avg Reputation: " . number_format($stats['avg_reputation'], 3) . "\n";
        echo "  Batch Size: {$stats['batch_size']}\n";
        
        echo "\n✓ Cache warming completed\n\n";
        
    } catch (Exception $e) {
        echo "\n✗ Error: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

echo "========================================\n";
echo "Batch Processing Complete\n";
echo "========================================\n";

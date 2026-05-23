<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * Adaptive Weighting Configuration
 * 
 * Configuration for the LLM-based adaptive weighting system.
 * Controls whether adaptive weighting is enabled and how it behaves.
 * 
 * @package ClicShopping\Apps\Configuration\ChatGpt
 * @version 1.0.0
 * @since 2026-02-06
 */

// Determine LLM provider dynamically based on configured model
$llmProvider = 'openai'; // Default
if (defined('CLICSHOPPING_APP_CHATGPT_CH_MODEL')) {
    $model = CLICSHOPPING_APP_CHATGPT_CH_MODEL;
    
    // Determine provider from model name
    if (strpos($model, 'anth-') === 0 || strpos($model, 'claude') !== false) {
        $llmProvider = 'anthropic';
    } elseif (strpos($model, 'mistral') !== false) {
        $llmProvider = 'mistral';
    } elseif (strpos($model, 'ollama:') === 0 || strpos($model, 'mistral:') === 0) {
        $llmProvider = 'ollama';
    } elseif (strpos($model, 'openai/') === 0 || strpos($model, 'microsoft/') === 0 || strpos($model, 'qwen/') === 0) {
        $llmProvider = 'lmstudio';
    } else {
        // Default to OpenAI for gpt-* models
        $llmProvider = 'openai';
    }
}

return [
    // Enable/disable adaptive weighting system
    // When false, uses static reputation-based weighting
    'ADAPTIVE_WEIGHTING_ENABLED' => true,
    
    // LLM provider for weight calculation
    // Dynamically determined from CLICSHOPPING_APP_CHATGPT_CH_MODEL
    // Options: 'openai', 'ollama', 'anthropic', 'mistral', 'lmstudio'
    'LLM_PROVIDER' => $llmProvider,
    
    // Enable fallback to static weighting on LLM failure
    'FALLBACK_ENABLED' => true,
    
    // Alert threshold for fallback rate (0.0-1.0)
    // Generates alert if fallback rate exceeds this percentage
    'FALLBACK_ALERT_THRESHOLD' => 0.05, // 5%
    
    // Weight audit retention in days
    // How long to keep weight calculation audit logs
    'WEIGHT_AUDIT_RETENTION_DAYS' => 90,
    
    // Enable anomaly detection for weight patterns
    'ANOMALY_DETECTION_ENABLED' => true,
    
    // Enable LLM-based critic selection
    // When false, uses existing selection algorithm
    'CRITIC_SELECTION_ENABLED' => false,
    
    // Maximum retries for LLM calls
    'MAX_RETRIES' => 2,
    
    // Timeout for LLM calls in seconds
    'TIMEOUT_SECONDS' => 30,
    
    // Migration mode - calculate both static and adaptive in parallel
    'MIGRATION_MODE' => false,
    
    // Rollout percentage (0-100) for gradual deployment
    // Only this percentage of evaluations will use adaptive weighting
    'ADAPTIVE_WEIGHT_ROLLOUT_PERCENTAGE' => 0,
];

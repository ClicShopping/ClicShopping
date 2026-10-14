<?php
declare(strict_types=1);

namespace ClicShopping\AI\Config;

/**
 * Load balancing configuration
 * 
 * Centralized configuration for load balancing parameters
 * including strategies, limits, and thresholds.
 */
class LoadBalancingConfig
{
    private array $config;
    private static ?LoadBalancingConfig $instance = null;
    
    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->config = $this->loadDefaultConfig();
    }
    
    /**
     * Get singleton instance
     * 
     * @return LoadBalancingConfig Instance
     */
    public static function getInstance(): LoadBalancingConfig
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Set configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }
    
    /**
     * Get all configuration
     * 
     * @return array All configuration
     */
    public function getAll(): array
    {
        return $this->config;
    }
    
    /**
     * Update multiple configuration values
     * 
     * @param array $config Configuration updates
     * @return void
     */
    public function update(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
    
    /**
     * Reset to default configuration
     * 
     * @return void
     */
    public function reset(): void
    {
        $this->config = $this->loadDefaultConfig();
    }
    
    /**
     * Load default configuration
     * 
     * @return array Default configuration
     */
    private function loadDefaultConfig(): array
    {
        return [
            // Load balancing strategy
            'strategy' => 'weighted_round_robin', // Options: weighted_round_robin, least_connections
            
            // Actor limits
            'max_concurrent_actions_per_actor' => 5,
            'actor_queue_size' => 100,
            'actor_queue_timeout' => 300, // seconds
            
            // Critic limits
            'max_concurrent_evaluations_per_critic' => 10,
            'critic_queue_size' => 100,
            'critic_queue_timeout' => 300, // seconds
            
            // Load thresholds
            'critical_load_threshold' => 0.9, // 90%
            'warning_load_threshold' => 0.75, // 75%
            'overload_threshold' => 0.95, // 95%
            
            // Load shedding
            'load_shedding_enabled' => true,
            'shed_low_priority_at_warning' => true,
            'shed_medium_priority_at_critical' => true,
            
            // Queue management
            'enable_priority_queuing' => true,
            'queue_cleanup_interval' => 60, // seconds
            'max_queue_age' => 600, // seconds
            
            // Performance tuning
            'load_calculation_cache_ttl' => 5, // seconds
            'metrics_aggregation_interval' => 30, // seconds
            
            // Retry and timeout
            'max_retry_attempts' => 3,
            'retry_backoff_multiplier' => 2,
            'initial_retry_delay' => 1, // seconds
            
            // Monitoring
            'enable_load_monitoring' => true,
            'alert_on_overload' => true,
            'alert_threshold' => 0.85,
            
            // Advanced features
            'enable_predictive_scaling' => false,
            'enable_adaptive_thresholds' => false,
            'enable_circuit_breaker' => true,
            'circuit_breaker_threshold' => 0.5, // 50% failure rate
            'circuit_breaker_timeout' => 60 // seconds
        ];
    }
    
    /**
     * Validate configuration
     * 
     * @return array Validation errors (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];
        
        // Validate thresholds
        if ($this->config['critical_load_threshold'] <= $this->config['warning_load_threshold']) {
            $errors[] = 'Critical load threshold must be greater than warning threshold';
        }
        
        if ($this->config['critical_load_threshold'] > 1.0 || $this->config['critical_load_threshold'] < 0.0) {
            $errors[] = 'Critical load threshold must be between 0.0 and 1.0';
        }
        
        if ($this->config['warning_load_threshold'] > 1.0 || $this->config['warning_load_threshold'] < 0.0) {
            $errors[] = 'Warning load threshold must be between 0.0 and 1.0';
        }
        
        // Validate limits
        if ($this->config['max_concurrent_actions_per_actor'] < 1) {
            $errors[] = 'Max concurrent actions per actor must be at least 1';
        }
        
        if ($this->config['max_concurrent_evaluations_per_critic'] < 1) {
            $errors[] = 'Max concurrent evaluations per critic must be at least 1';
        }
        
        // Validate queue sizes
        if ($this->config['actor_queue_size'] < 0) {
            $errors[] = 'Actor queue size must be non-negative';
        }
        
        if ($this->config['critic_queue_size'] < 0) {
            $errors[] = 'Critic queue size must be non-negative';
        }
        
        return $errors;
    }
    
    /**
     * Export configuration to array for storage
     * 
     * @return array Configuration array
     */
    public function export(): array
    {
        return $this->config;
    }
    
    /**
     * Import configuration from array
     * 
     * @param array $config Configuration array
     * @return void
     */
    public function import(array $config): void
    {
        $this->config = array_merge($this->loadDefaultConfig(), $config);
    }
}

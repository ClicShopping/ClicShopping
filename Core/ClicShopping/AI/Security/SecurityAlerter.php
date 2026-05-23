<?php
/**
 * Security Alerter Class
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\AI\Security;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Mail;

/**
 * Class SecurityAlerter
 * Provides email alerting for security events
 * Monitors threat rates and system failures, sends configurable alerts
 * 
 * Requirements: 8.4
 */
class SecurityAlerter
{
    private $db;
    private string $prefix;
    private bool $alertsEnabled;
    private string $alertEmail;
    private int $alertThreshold;
    private int $highThreatThreshold;
    private bool $failureAlertsEnabled;
    private int $alertCooldown;
    private bool $digestMode;
    private string $cacheDir;
    
    /**
     * Constructor
     * Initializes alerting configuration from system constants
     */
    public function __construct()
    {
        if (Registry::exists('Db')) {
            $this->db = Registry::get('Db');
        }
        
        $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
        
        // Load configuration from constants
        // Handle both boolean and string 'True'/'False' formats (DB compatibility)
        $alertsEnabledConfig = defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERTS_ENABLED') 
            ? CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERTS_ENABLED 
            : false;
        $this->alertsEnabled = ($alertsEnabledConfig === true || $alertsEnabledConfig == 'True' || $alertsEnabledConfig == 'true' || $alertsEnabledConfig === '1');
            
        $this->alertEmail = defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_EMAIL') 
            ? CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_EMAIL 
            : '';
            
        $this->alertThreshold = CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_THRESHOLD;
            
        $this->highThreatThreshold = CLICSHOPPING_APP_CHATGPT_RA_SECURITY_HIGH_THREAT_THRESHOLD;
            
        $this->failureAlertsEnabled = CLICSHOPPING_APP_CHATGPT_RA_SECURITY_FAILURE_ALERTS;
            
        $this->alertCooldown = CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_COOLDOWN;
            
        $this->digestMode = CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_DIGEST_MODE;
        
        // Cache directory for tracking last alert times
        $this->cacheDir = CLICSHOPPING::BASE_DIR . 'Work/Cache/Security/';
        
        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Check if alerts are properly configured
     * 
     * @return bool True if alerts can be sent
     */
    public function isConfigured(): bool
    {
        return $this->alertsEnabled && !empty($this->alertEmail) && filter_var($this->alertEmail, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Check current threat rate and send alert if threshold exceeded
     * Called periodically or after each security event
     * 
     * @return bool True if alert was sent
     * 
     * Requirements: 8.4
     */
    public function checkThreatRate(): bool
    {
        // Skip if alerts not configured
        if (!$this->isConfigured()) {
            return false;
        }
        
        // Check cooldown period
        if (!$this->canSendAlert('threat_rate')) {
            return false;
        }
        
        // Get threat count for last hour
        $threatCount = $this->getThreatCountLastHour();
        
        // Determine if alert should be sent
        $shouldAlert = false;
        $severity = 'medium';
        
        if ($threatCount >= $this->highThreatThreshold) {
            $shouldAlert = true;
            $severity = 'critical';
        } elseif ($threatCount >= $this->alertThreshold) {
            $shouldAlert = true;
            $severity = 'high';
        }
        
        if ($shouldAlert) {
            return $this->sendThreatRateAlert($threatCount, $severity);
        }
        
        return false;
    }
    
    /**
     * Send alert for high threat rate
     * 
     * @param int $threatCount Number of threats detected
     * @param string $severity Alert severity (high, critical)
     * @return bool True if alert sent successfully
     */
    private function sendThreatRateAlert(int $threatCount, string $severity): bool
    {
        $subject = "[Security Alert] High Threat Rate Detected - {$severity}";
        
        // Get threat breakdown
        $breakdown = $this->getThreatBreakdown();
        
        // Build email body
        $body = $this->buildThreatRateEmailBody($threatCount, $severity, $breakdown);
        
        // Send email
        $sent = $this->sendEmail($subject, $body);
        
        if ($sent) {
            // Update last alert time
            $this->updateLastAlertTime('threat_rate');
        }
        
        return $sent;
    }
    
    /**
     * Send alert for system failure
     * 
     * @param string $failureType Type of failure (llm_unavailable, database_error, etc.)
     * @param string $errorMessage Error message
     * @param array $context Additional context
     * @return bool True if alert sent successfully
     * 
     * Requirements: 8.4
     */
    public function sendSystemFailureAlert(string $failureType, string $errorMessage, array $context = []): bool
    {
        // Skip if failure alerts not enabled
        if (!$this->failureAlertsEnabled || !$this->isConfigured()) {
            return false;
        }
        
        // Check cooldown for this specific failure type
        if (!$this->canSendAlert("failure_{$failureType}")) {
            return false;
        }
        
        $subject = "[Security Alert] System Failure - {$failureType}";
        
        // Build email body
        $body = $this->buildSystemFailureEmailBody($failureType, $errorMessage, $context);
        
        // Send email
        $sent = $this->sendEmail($subject, $body);
        
        if ($sent) {
            // Update last alert time
            $this->updateLastAlertTime("failure_{$failureType}");
        }
        
        return $sent;
    }
    
    /**
     * Send hourly digest of security events
     * Should be called by a cron job or scheduled task
     * 
     * @return bool True if digest sent successfully
     * 
     * Requirements: 8.4
     */
    public function sendHourlyDigest(): bool
    {
        // Skip if not in digest mode or not configured
        if (!$this->digestMode || !$this->isConfigured()) {
            return false;
        }
        
        // Get statistics for last hour
        $stats = $this->getHourlyStatistics();
        
        // Only send if there were events
        if ($stats['total_events'] == 0) {
            return false;
        }
        
        $subject = "[Security Digest] Hourly Security Summary";
        
        // Build email body
        $body = $this->buildDigestEmailBody($stats);
        
        // Send email
        return $this->sendEmail($subject, $body);
    }
    
    /**
     * Get threat count for last hour
     * 
     * @return int Number of threats detected
     */
    private function getThreatCountLastHour(): int
    {
        if (!$this->db) {
            return 0;
        }
        
        try {
            $table = ':table_rag_security_events';
            $startTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
            
            $query = "SELECT COUNT(*) as count 
                     FROM {$table} 
                     WHERE created_at >= :start_time 
                     AND threat_type IS NOT NULL 
                     AND blocked = 1";
            
            $result = $this->db->prepare($query);
            $result->bindValue(':start_time', $startTime);
            $result->execute();
            
            return (int)($result->fetch()['count'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get threat breakdown by type
     * 
     * @return array Threat counts by type
     */
    private function getThreatBreakdown(): array
    {
        if (!$this->db) {
            return [];
        }
        
        try {
            $table = ':table_rag_security_events';
            $startTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
            
            $query = "SELECT 
                        threat_type,
                        COUNT(*) as count,
                        AVG(threat_score) as avg_score
                     FROM {$table} 
                     WHERE created_at >= :start_time 
                     AND threat_type IS NOT NULL 
                     GROUP BY threat_type 
                     ORDER BY count DESC";
            
            $result = $this->db->prepare($query);
            $result->bindValue(':start_time', $startTime);
            $result->execute();
            
            return $result->fetchAll() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Get hourly statistics for digest
     * 
     * @return array Statistics data
     */
    private function getHourlyStatistics(): array
    {
        if (!$this->db) {
            return ['total_events' => 0];
        }
        
        try {
            $table = ':table_rag_security_events';
            $startTime = date('Y-m-d H:i:s', strtotime('-1 hour'));
            
            $query = "SELECT 
                        COUNT(*) as total_events,
                        SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_count,
                        SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
                        SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_count,
                        SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium_count,
                        SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low_count
                     FROM {$table} 
                     WHERE created_at >= :start_time";
            
            $result = $this->db->prepare($query);
            $result->bindValue(':start_time', $startTime);
            $result->execute();
            
            $stats = $result->fetch() ?: ['total_events' => 0];
            
            // Add threat breakdown
            $stats['threat_breakdown'] = $this->getThreatBreakdown();
            
            return $stats;
        } catch (\Exception $e) {
            return ['total_events' => 0];
        }
    }
    
    /**
     * Check if alert can be sent (cooldown period)
     * 
     * @param string $alertType Type of alert
     * @return bool True if alert can be sent
     */
    private function canSendAlert(string $alertType): bool
    {
        $cacheFile = $this->cacheDir . "last_alert_{$alertType}.txt";
        
        if (!file_exists($cacheFile)) {
            return true;
        }
        
        $lastAlertTime = (int)file_get_contents($cacheFile);
        $cooldownSeconds = $this->alertCooldown * 60;
        
        return (time() - $lastAlertTime) >= $cooldownSeconds;
    }
    
    /**
     * Update last alert time
     * 
     * @param string $alertType Type of alert
     * @return void
     */
    private function updateLastAlertTime(string $alertType): void
    {
        $cacheFile = $this->cacheDir . "last_alert_{$alertType}.txt";
        file_put_contents($cacheFile, time());
    }
    
    /**
     * Build email body for threat rate alert
     * 
     * @param int $threatCount Number of threats
     * @param string $severity Alert severity
     * @param array $breakdown Threat breakdown
     * @return string Email body (HTML)
     */
    private function buildThreatRateEmailBody(int $threatCount, string $severity, array $breakdown): string
    {
        $html = "<html><body style='font-family: Arial, sans-serif;'>";
        $html .= "<h2 style='color: " . ($severity === 'critical' ? '#dc3545' : '#ffc107') . ";'>Security Alert: High Threat Rate</h2>";
        $html .= "<p><strong>Severity:</strong> " . strtoupper($severity) . "</p>";
        $html .= "<p><strong>Threats Detected (Last Hour):</strong> {$threatCount}</p>";
        $html .= "<p><strong>Alert Threshold:</strong> {$this->alertThreshold}</p>";
        $html .= "<p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
        
        if (!empty($breakdown)) {
            $html .= "<h3>Threat Breakdown:</h3>";
            $html .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
            $html .= "<tr><th>Threat Type</th><th>Count</th><th>Avg Score</th></tr>";
            
            foreach ($breakdown as $threat) {
                $html .= "<tr>";
                $html .= "<td>" . htmlspecialchars($threat['threat_type']) . "</td>";
                $html .= "<td>" . $threat['count'] . "</td>";
                $html .= "<td>" . number_format($threat['avg_score'], 2) . "</td>";
                $html .= "</tr>";
            }
            
            $html .= "</table>";
        }
        
        $html .= "<hr>";
        $html .= "<p><strong>Action Required:</strong></p>";
        $html .= "<ul>";
        $html .= "<li>Review security logs for attack patterns</li>";
        $html .= "<li>Check if legitimate users are being blocked</li>";
        $html .= "<li>Consider adjusting threat thresholds if needed</li>";
        $html .= "<li>Monitor for continued attack activity</li>";
        $html .= "</ul>";
        
        $html .= "<p style='color: #666; font-size: 12px;'>This is an automated security alert from ClicShopping AI Security System.</p>";
        $html .= "</body></html>";
        
        return $html;
    }
    
    /**
     * Build email body for system failure alert
     * 
     * @param string $failureType Type of failure
     * @param string $errorMessage Error message
     * @param array $context Additional context
     * @return string Email body (HTML)
     */
    private function buildSystemFailureEmailBody(string $failureType, string $errorMessage, array $context): string
    {
        $html = "<html><body style='font-family: Arial, sans-serif;'>";
        $html .= "<h2 style='color: #dc3545;'>Security System Failure</h2>";
        $html .= "<p><strong>Failure Type:</strong> " . htmlspecialchars($failureType) . "</p>";
        $html .= "<p><strong>Error Message:</strong> " . htmlspecialchars($errorMessage) . "</p>";
        $html .= "<p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
        
        if (!empty($context)) {
            $html .= "<h3>Additional Context:</h3>";
            $html .= "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
            $html .= htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT));
            $html .= "</pre>";
        }
        
        $html .= "<hr>";
        $html .= "<p><strong>Impact:</strong></p>";
        $html .= "<ul>";
        
        switch ($failureType) {
            case 'llm_unavailable':
                $html .= "<li>LLM-based security analysis is unavailable</li>";
                $html .= "<li>System may fall back to pattern-based detection</li>";
                $html .= "<li>Detection accuracy may be reduced</li>";
                break;
            case 'database_error':
                $html .= "<li>Security event logging may be affected</li>";
                $html .= "<li>Statistics and reporting may be incomplete</li>";
                break;
            case 'pattern_load_error':
                $html .= "<li>Pattern-based detection may be unavailable</li>";
                $html .= "<li>System relies on LLM-based detection only</li>";
                break;
            default:
                $html .= "<li>Security system functionality may be degraded</li>";
        }
        
        $html .= "</ul>";
        
        $html .= "<p><strong>Recommended Actions:</strong></p>";
        $html .= "<ul>";
        $html .= "<li>Check system logs for detailed error information</li>";
        $html .= "<li>Verify service availability (LLM, database, etc.)</li>";
        $html .= "<li>Test security system functionality</li>";
        $html .= "<li>Contact system administrator if issue persists</li>";
        $html .= "</ul>";
        
        $html .= "<p style='color: #666; font-size: 12px;'>This is an automated security alert from ClicShopping AI Security System.</p>";
        $html .= "</body></html>";
        
        return $html;
    }
    
    /**
     * Build email body for hourly digest
     * 
     * @param array $stats Hourly statistics
     * @return string Email body (HTML)
     */
    private function buildDigestEmailBody(array $stats): string
    {
        $html = "<html><body style='font-family: Arial, sans-serif;'>";
        $html .= "<h2 style='color: #007bff;'>Security Hourly Digest</h2>";
        $html .= "<p><strong>Period:</strong> " . date('Y-m-d H:i:s', strtotime('-1 hour')) . " to " . date('Y-m-d H:i:s') . "</p>";
        
        $html .= "<h3>Summary:</h3>";
        $html .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
        $html .= "<tr><th>Metric</th><th>Count</th></tr>";
        $html .= "<tr><td>Total Events</td><td>" . ($stats['total_events'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Blocked Queries</td><td>" . ($stats['blocked_count'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Critical Severity</td><td style='color: #dc3545;'>" . ($stats['critical_count'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>High Severity</td><td style='color: #ffc107;'>" . ($stats['high_count'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Medium Severity</td><td>" . ($stats['medium_count'] ?? 0) . "</td></tr>";
        $html .= "<tr><td>Low Severity</td><td>" . ($stats['low_count'] ?? 0) . "</td></tr>";
        $html .= "</table>";
        
        if (!empty($stats['threat_breakdown'])) {
            $html .= "<h3>Threat Breakdown:</h3>";
            $html .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
            $html .= "<tr><th>Threat Type</th><th>Count</th><th>Avg Score</th></tr>";
            
            foreach ($stats['threat_breakdown'] as $threat) {
                $html .= "<tr>";
                $html .= "<td>" . htmlspecialchars($threat['threat_type']) . "</td>";
                $html .= "<td>" . $threat['count'] . "</td>";
                $html .= "<td>" . number_format($threat['avg_score'], 2) . "</td>";
                $html .= "</tr>";
            }
            
            $html .= "</table>";
        }
        
        $html .= "<p style='color: #666; font-size: 12px; margin-top: 20px;'>This is an automated hourly digest from ClicShopping AI Security System.</p>";
        $html .= "</body></html>";
        
        return $html;
    }
    
    /**
     * Send email using ClicShopping Mail class
     * 
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @return bool True if sent successfully
     */
    private function sendEmail(string $subject, string $body): bool
    {
        try {
            $mail = new Mail();
            
            // Set sender (use system email if configured)
            $fromEmail = defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : 'noreply@clicshopping.org';
            $fromName = defined('STORE_NAME') ? STORE_NAME . ' Security' : 'ClicShopping Security';
            
            $mail->setFrom($fromEmail, $fromName);
            $mail->setTo($this->alertEmail);
            $mail->setSubject($subject);
            $mail->setHtml($body);
            
            return $mail->send();
        } catch (\Exception $e) {
            // Log error but don't throw - alerting should not break the system
            error_log("Security alert email failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test alert configuration
     * Sends a test email to verify configuration
     * 
     * @return array Result with success status and message
     */
    public function sendTestAlert(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Alerts not properly configured. Check email address and enable alerts.'
            ];
        }
        
        $subject = "[Security Alert] Test Alert - Configuration Verified";
        
        $body = "<html><body style='font-family: Arial, sans-serif;'>";
        $body .= "<h2 style='color: #28a745;'>Security Alert Test</h2>";
        $body .= "<p>This is a test alert to verify your security alerting configuration.</p>";
        $body .= "<p><strong>Configuration:</strong></p>";
        $body .= "<ul>";
        $body .= "<li>Alert Email: " . htmlspecialchars($this->alertEmail) . "</li>";
        $body .= "<li>Alert Threshold: {$this->alertThreshold} threats/hour</li>";
        $body .= "<li>High Threat Threshold: {$this->highThreatThreshold} threats/hour</li>";
        $body .= "<li>Cooldown Period: {$this->alertCooldown} minutes</li>";
        $body .= "<li>Digest Mode: " . ($this->digestMode ? 'Enabled' : 'Disabled') . "</li>";
        $body .= "<li>Failure Alerts: " . ($this->failureAlertsEnabled ? 'Enabled' : 'Disabled') . "</li>";
        $body .= "</ul>";
        $body .= "<p>If you received this email, your security alerting system is working correctly.</p>";
        $body .= "<p style='color: #666; font-size: 12px;'>Test sent at: " . date('Y-m-d H:i:s') . "</p>";
        $body .= "</body></html>";
        
        $sent = $this->sendEmail($subject, $body);
        
        return [
            'success' => $sent,
            'message' => $sent 
                ? 'Test alert sent successfully to ' . $this->alertEmail 
                : 'Failed to send test alert. Check email configuration.'
        ];
    }
}

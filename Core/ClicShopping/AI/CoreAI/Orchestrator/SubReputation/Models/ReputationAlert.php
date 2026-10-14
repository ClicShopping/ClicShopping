<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models;

use DateTimeImmutable;

/**
 * ReputationAlert - Data model for reputation alerts
 * 
 * Represents an alert generated for reputation issues or anomalies.
 * 
 * Requirements: 8.1, 8.2, 8.3, 8.4
 */
class ReputationAlert
{
    public ?int $alertId = null;
    public string $criticId;
    public string $alertType;           // 'low_reputation', 'rapid_change', 'gaming_detected', 'anomaly'
    public string $severity;            // 'low', 'medium', 'high', 'critical'
    public string $message;
    public ?array $context = null;
    public bool $acknowledged = false;
    public ?string $acknowledgedBy = null;
    public ?DateTimeImmutable $acknowledgedAt = null;
    public DateTimeImmutable $createdAt;
    
    /**
     * Create a new ReputationAlert
     * 
     * @param string $criticId Critic identifier
     * @param string $alertType Alert type
     * @param string $severity Alert severity
     * @param string $message Human-readable alert message
     * @param array|null $context Additional context data
     * @return self New reputation alert
     */
    public static function create(
        string $criticId,
        string $alertType,
        string $severity,
        string $message,
        ?array $context = null
    ): self {
        $alert = new self();
        $alert->criticId = $criticId;
        $alert->alertType = $alertType;
        $alert->severity = $severity;
        $alert->message = $message;
        $alert->context = $context;
        $alert->createdAt = new DateTimeImmutable();
        
        return $alert;
    }
    
    /**
     * Check if alert is valid
     * 
     * @return bool True if valid
     */
    public function isValid(): bool
    {
        $validTypes = ['low_reputation', 'rapid_change', 'gaming_detected', 'anomaly'];
        $validSeverities = ['low', 'medium', 'high', 'critical'];
        
        return !empty($this->criticId)
            && in_array($this->alertType, $validTypes, true)
            && in_array($this->severity, $validSeverities, true)
            && !empty($this->message);
    }
    
    /**
     * Get alert as array for serialization
     * 
     * @return array Alert data
     */
    public function toArray(): array
    {
        return [
            'alert_id' => $this->alertId,
            'critic_id' => $this->criticId,
            'alert_type' => $this->alertType,
            'severity' => $this->severity,
            'message' => $this->message,
            'context' => $this->context,
            'acknowledged' => $this->acknowledged,
            'acknowledged_by' => $this->acknowledgedBy,
            'acknowledged_at' => $this->acknowledgedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s')
        ];
    }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

/**
 * Context class
 * 
 * Represents the execution context for actions and evaluations.
 * Contains system state, user information, and environmental data
 * needed for proper action execution and evaluation.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class Context
{
    private string $contextId;
    private string $userId;
    private int $languageId;
    private array $systemState;
    private array $userPreferences;
    private array $environmentalData;
    private \DateTimeImmutable $timestamp;
    
    public function __construct(
        string $userId,
        int $languageId,
        array $systemState = [],
        array $userPreferences = [],
        array $environmentalData = []
    ) {
        $this->contextId = $this->generateId();
        $this->userId = $userId;
        $this->languageId = $languageId;
        $this->systemState = $systemState;
        $this->userPreferences = $userPreferences;
        $this->environmentalData = $environmentalData;
        $this->timestamp = new \DateTimeImmutable();
    }
    
    public function getContextId(): string { return $this->contextId; }
    public function getUserId(): string { return $this->userId; }
    public function getLanguageId(): int { return $this->languageId; }
    public function getSystemState(): array { return $this->systemState; }
    public function getUserPreferences(): array { return $this->userPreferences; }
    public function getEnvironmentalData(): array { return $this->environmentalData; }
    public function getTimestamp(): \DateTimeImmutable { return $this->timestamp; }
    
    public function setSystemState(array $systemState): void
    {
        $this->systemState = $systemState;
    }
    
    public function addEnvironmentalData(string $key, mixed $value): void
    {
        $this->environmentalData[$key] = $value;
    }
    
    public function getEnvironmentalValue(string $key): mixed
    {
        return $this->environmentalData[$key] ?? null;
    }
    
    private function generateId(): string
    {
        return 'context_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
    
    public function toArray(): array
    {
        return [
            'context_id' => $this->contextId,
            'user_id' => $this->userId,
            'language_id' => $this->languageId,
            'system_state' => $this->systemState,
            'user_preferences' => $this->userPreferences,
            'environmental_data' => $this->environmentalData,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }
}
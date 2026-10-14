<?php
/**
 * TaskValidator - Validators for deterministic plans
 * Handles external data validation and access policies
 * Ensures deterministic behavior even if external sources fail
 */

namespace ClicShopping\AI\CoreAI\Planning;




class TaskValidator
{
    /**
     * Checks if valid external competitor data is available
     * 
     * @param array|null $competitorData Competitor data
     * @return array [bool $ok, array $reasoning]
     */
    public static function validateCompetitorDataAvailability(?array $competitorData): array
    {
        $reasoning = [];
        
        if (is_null($competitorData)) {
            $reasoning[] = 'no_competitor_payload';
            return [false, $reasoning];
        }
        
        if (!is_array($competitorData) || empty($competitorData)) {
            $reasoning[] = 'empty_competitor_dataset';
            return [false, $reasoning];
        }
        
        // Check expected minimal structure
        $sample = reset($competitorData);
        if (!is_array($sample) || !array_key_exists('name', $sample) || !array_key_exists('price', $sample)) {
            $reasoning[] = 'invalid_competitor_record_schema';
            return [false, $reasoning];
        }
        
        // Suspicious values
        foreach ($competitorData as $rec) {
            if (!is_numeric($rec['price']) && !is_null($rec['price'])) {
                $reasoning[] = 'non_numeric_price_found';
                return [false, $reasoning];
            }
        }
        
        // If all passes
        return [true, ['validated_competitor_dataset']];
    }
    
    /**
     * Policy: allow/reject external source calls
     * 
     * @param array $policy Policy configuration
     * @return bool True if external access allowed
     */
    public static function externalAccessAllowed(array $policy): bool
    {
        // Minimal policy: explicit flag presence
        if (isset($policy['allow_external']) && $policy['allow_external'] === true) {
            return true;
        }
        
        // Check if SerpApi is configured
        if (isset($policy['serpapi_configured']) && $policy['serpapi_configured'] === true) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Validates internal competitor cache availability
     * 
     * @param array|null $internalCache Internal cache
     * @return array [bool $ok, array $data]
     */
    public static function validateInternalCompetitorCache(?array $internalCache): array
    {
        if (is_null($internalCache) || empty($internalCache)) {
            return [false, []];
        }
        
        // Filter valid data
        $validData = [];
        foreach ($internalCache as $item) {
            if (is_array($item) && isset($item['name']) && isset($item['price'])) {
                $validData[] = $item;
            }
        }
        
        return [count($validData) > 0, $validData];
    }
    
    /**
     * Determines fallback strategy for step_2
     * 
     * @param array $context Execution context
     * @return array [string $strategy, array $data]
     */
    public static function determineFallbackStrategy(array $context): array
    {
        $policy = $context['policy'] ?? ['allow_external' => false];
        
        // If external blocked by policy
        if (!self::externalAccessAllowed($policy)) {
            [$hasCache, $cacheData] = self::validateInternalCompetitorCache(
                $context['internal_competitor_cache'] ?? null
            );
            
            if ($hasCache) {
                return ['internal_cache', $cacheData];
            } else {
                return ['no_data', []];
            }
        }
        
        // If external allowed but invalid data
        $competitorPayload = $context['competitor_payload'] ?? null;
        [$isValid, $reasoning] = self::validateCompetitorDataAvailability($competitorPayload);
        
        if (!$isValid) {
            [$hasCache, $cacheData] = self::validateInternalCompetitorCache(
                $context['internal_competitor_cache'] ?? null
            );
            
            if ($hasCache) {
                return ['fallback_to_cache', $cacheData];
            } else {
                return ['external_failed_no_cache', []];
            }
        }
        
        return ['external_valid', $competitorPayload];
    }
}
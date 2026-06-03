<?php
/**
 * TaskPlanValidator - Enhanced validator with auto-correction
 * 
 * Mixed Strategy (Option 3):
 * - Attempts automatic correction of inconsistencies
 * - If impossible → refuses with detailed diagnostics
 * - Internal-only deterministic fallback if doubt
 * - Intelligent rollback with graceful skip
 */

namespace ClicShopping\AI\CoreAI\Planning;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Config\SystemConfig;

class TaskPlanValidator
{
    private bool $debug;
    private ?SecurityLogger $securityLogger;
    
    // Validation policies
    private array $validationPolicies = [
        'min_steps' => 1,
        'max_steps' => 10,
        'allow_orphan_steps' => false,
        'require_final_step' => true,
        'allow_external_data' => true,
        'auto_correct_dependencies' => true,
        'fallback_to_internal_only' => true
    ];
    
    public function __construct(bool $debug = false, ?SecurityLogger $securityLogger = null)
    {
        $this->debug = $debug;
        $this->securityLogger = $securityLogger;

        // Maximum plan steps is configurable (defaults <- base <- Custom/Conf override).
        $orchestratorConfig = SystemConfig::getSection('OrchestratorAgent', [
            'max_plan_steps' => $this->validationPolicies['max_steps'],
        ]);
	
        $this->validationPolicies['max_steps'] = (int) $orchestratorConfig['max_plan_steps'];
    }
    
    /**
     * Main validation - Mixed Option 3
     * 
     * @param array $steps List of TaskStep
     * @param array $dependencies Dependency graph
     * @param array $context Execution context
     * @return array [bool $valid, array $correctedSteps, array $diagnostics]
     */
    public function validateAndCorrectPlan(array $steps, array $dependencies, array $context = []): array
    {
        $diagnostics = [];
        $correctedSteps = $steps;
        
        if ($this->debug) {
            $this->logDebug("Starting plan validation with " . count($steps) . " steps");
        }
        
        // 1. Critical validations (immediate refusal if failed)
        $criticalValidation = $this->validateCriticalConstraints($steps, $diagnostics);
        if (!$criticalValidation['valid']) {
            return [
                'valid' => false,
                'corrected_steps' => [],
                'diagnostics' => $criticalValidation['diagnostics'],
                'correction_attempted' => false,
                'reason' => 'critical_constraints_failed'
            ];
        }
        
        // 2. Validations with auto-correction
        $correctionResults = $this->attemptAutoCorrections($correctedSteps, $dependencies, $context, $diagnostics);
        $correctedSteps = $correctionResults['steps'];
        $diagnostics = array_merge($diagnostics, $correctionResults['diagnostics']);
        
        // 3. Final validation after corrections
        $finalValidation = $this->validateFinalPlan($correctedSteps, $dependencies, $diagnostics);
        
        return [
            'valid' => $finalValidation['valid'],
            'corrected_steps' => $correctedSteps,
            'diagnostics' => $diagnostics,
            'correction_attempted' => $correctionResults['corrections_applied'],
            'corrections_applied' => $correctionResults['correction_details'],
            'reason' => $finalValidation['reason'] ?? 'validated'
        ];
    }
    
    /**
     * Critical validations (immediate refusal)
     * 
     * @param array $steps Steps to validate
     * @param array $diagnostics Diagnostics array
     * @return array Validation result
     */
    private function validateCriticalConstraints(array $steps, array &$diagnostics): array
    {
        // Constraint 1: Number of steps
        if (count($steps) < $this->validationPolicies['min_steps']) {
            $diagnostics[] = [
                'type' => 'critical_error',
                'code' => 'INSUFFICIENT_STEPS',
                'message' => 'Plan must have at least ' . $this->validationPolicies['min_steps'] . ' step(s)',
                'current_count' => count($steps)
            ];
            return ['valid' => false, 'diagnostics' => $diagnostics];
        }
        
        if (count($steps) > $this->validationPolicies['max_steps']) {
            $diagnostics[] = [
                'type' => 'critical_error',
                'code' => 'TOO_MANY_STEPS',
                'message' => 'Plan exceeds maximum of ' . $this->validationPolicies['max_steps'] . ' steps',
                'current_count' => count($steps)
            ];
            return ['valid' => false, 'diagnostics' => $diagnostics];
        }
        
        // Constraint 2: Empty or malformed steps
        foreach ($steps as $i => $step) {
            if (!$step || !method_exists($step, 'getId') || !method_exists($step, 'getType')) {
                $diagnostics[] = [
                    'type' => 'critical_error',
                    'code' => 'MALFORMED_STEP',
                    'message' => "Step at index $i is malformed or missing required methods",
                    'step_index' => $i
                ];
                return ['valid' => false, 'diagnostics' => $diagnostics];
            }
            
            if (empty($step->getId()) || empty($step->getType())) {
                $diagnostics[] = [
                    'type' => 'critical_error',
                    'code' => 'EMPTY_STEP_DATA',
                    'message' => "Step at index $i has empty ID or type",
                    'step_id' => $step->getId(),
                    'step_type' => $step->getType()
                ];
                return ['valid' => false, 'diagnostics' => $diagnostics];
            }
        }
        
        return ['valid' => true, 'diagnostics' => $diagnostics];
    }
    
    /**
     * Auto-correction attempts
     * 
     * @param array $steps Steps to correct
     * @param array $dependencies Dependency graph
     * @param array $context Execution context
     * @param array $diagnostics Diagnostics array
     * @return array Correction results
     */
    private function attemptAutoCorrections(array $steps, array $dependencies, array $context, array &$diagnostics): array
    {
        $correctedSteps = $steps;
        $correctionsApplied = false;
        $correctionDetails = [];
        
        // Correction 1: Circular dependencies
        $circularResult = $this->fixCircularDependencies($correctedSteps, $dependencies, $diagnostics);
        if ($circularResult['corrected']) {
            $correctedSteps = $circularResult['steps'];
            $correctionsApplied = true;
            $correctionDetails[] = 'circular_dependencies_fixed';
        }
        
        // Correction 2: Orphan steps
        $orphanResult = $this->fixOrphanSteps($correctedSteps, $dependencies, $diagnostics);
        if ($orphanResult['corrected']) {
            $correctedSteps = $orphanResult['steps'];
            $correctionsApplied = true;
            $correctionDetails[] = 'orphan_steps_fixed';
        }
        
        // Correction 3: Missing final step
        $finalStepResult = $this->ensureFinalStep($correctedSteps, $diagnostics);
        if ($finalStepResult['corrected']) {
            $correctedSteps = $finalStepResult['steps'];
            $correctionsApplied = true;
            $correctionDetails[] = 'final_step_ensured';
        }
        
        // Correction 4: Internal-only fallback if external data is doubtful
        $fallbackResult = $this->applyInternalFallbackIfNeeded($correctedSteps, $context, $diagnostics);
        if ($fallbackResult['corrected']) {
            $correctedSteps = $fallbackResult['steps'];
            $correctionsApplied = true;
            $correctionDetails[] = 'internal_fallback_applied';
        }
        
        return [
            'steps' => $correctedSteps,
            'corrections_applied' => $correctionsApplied,
            'correction_details' => $correctionDetails,
            'diagnostics' => $diagnostics
        ];
    }
    
    /**
     * Fix circular dependencies
     * 
     * @param array $steps Steps to fix
     * @param array $dependencies Dependency graph
     * @param array $diagnostics Diagnostics array
     * @return array Fix result
     */
    private function fixCircularDependencies(array $steps, array $dependencies, array &$diagnostics): array
    {
        // Detect cycles with DFS algorithm
        $visited = [];
        $recursionStack = [];
        $cycles = [];
        
        foreach ($steps as $step) {
            $stepId = $step->getId();
            if (!isset($visited[$stepId])) {
                $this->detectCycles($stepId, $dependencies, $visited, $recursionStack, $cycles);
            }
        }
        
        if (empty($cycles)) {
            return ['corrected' => false, 'steps' => $steps];
        }
        
        // Correction: Remove dependencies that create cycles
        $correctedSteps = [];
        foreach ($steps as $step) {
            $stepId = $step->getId();
            $metadata = $step->getMetadata();
            $dependsOn = $metadata['depends_on'] ?? [];
            
            // Filter circular dependencies
            $filteredDependsOn = array_filter($dependsOn, function($depId) use ($cycles, $stepId) {
                foreach ($cycles as $cycle) {
                    if (in_array($stepId, $cycle, true) && in_array($depId, $cycle, true)) {
                        // This dependency creates a cycle
                        return false;
                    }
                }
                return true;
            });
            
            if (count($filteredDependsOn) !== count($dependsOn)) {
                // Create new step with corrected dependencies
                $metadata['depends_on'] = array_values($filteredDependsOn);
                $correctedStep = new TaskStep(
                    $step->getId(),
                    $step->getType(),
                    $step->getDescription(),
                    $metadata
                );
                $correctedSteps[] = $correctedStep;
                
                $diagnostics[] = [
                    'type' => 'auto_correction',
                    'code' => 'CIRCULAR_DEPENDENCY_FIXED',
                    'message' => "Removed circular dependencies for step: $stepId",
                    'step_id' => $stepId,
                    'removed_dependencies' => array_diff($dependsOn, $filteredDependsOn)
                ];
            } else {
                $correctedSteps[] = $step;
            }
        }
        
        return ['corrected' => true, 'steps' => $correctedSteps];
    }
    
    /**
     * Cycle detection with DFS
     * 
     * @param string $stepId Step ID
     * @param array $dependencies Dependency graph
     * @param array $visited Visited nodes
     * @param array $recursionStack Recursion stack
     * @param array $cycles Detected cycles
     * @return void
     */
    private function detectCycles(string $stepId, array $dependencies, array &$visited, array &$recursionStack, array &$cycles): void
    {
        $visited[$stepId] = true;
        $recursionStack[$stepId] = true;
        
        $dependsOn = $dependencies[$stepId]['depends_on'] ?? [];
        foreach ($dependsOn as $depId) {
            if (!isset($visited[$depId])) {
                $this->detectCycles($depId, $dependencies, $visited, $recursionStack, $cycles);
            } elseif (isset($recursionStack[$depId]) && $recursionStack[$depId]) {
                // Cycle detected
                $cycles[] = [$stepId, $depId];
            }
        }
        
        $recursionStack[$stepId] = false;
    }
    
    /**
     * Fix orphan steps
     * 
     * @param array $steps Steps to fix
     * @param array $dependencies Dependency graph
     * @param array $diagnostics Diagnostics array
     * @return array Fix result
     */
    private function fixOrphanSteps(array $steps, array $dependencies, array &$diagnostics): array
    {
        if ($this->validationPolicies['allow_orphan_steps']) {
            return ['corrected' => false, 'steps' => $steps];
        }
        
        $correctedSteps = [];
        $hasCorrections = false;
        
        foreach ($steps as $i => $step) {
            $stepId = $step->getId();
            $dependsOn = $dependencies[$stepId]['depends_on'] ?? [];
            $requiredBy = $dependencies[$stepId]['required_by'] ?? [];
            
            // A step is orphan if it has no dependencies AND is not required by anyone
            // EXCEPT if it's the first or last step
            $isOrphan = empty($dependsOn) && empty($requiredBy) && $i > 0 && $i < (count($steps) - 1);
            
            if ($isOrphan) {
                // Correction: Link to previous step
                $previousStep = $steps[$i - 1];
                $metadata = $step->getMetadata();
                $metadata['depends_on'] = [$previousStep->getId()];
                
                $correctedStep = new TaskStep(
                    $step->getId(),
                    $step->getType(),
                    $step->getDescription(),
                    $metadata
                );
                
                $correctedSteps[] = $correctedStep;
                $hasCorrections = true;
                
                $diagnostics[] = [
                    'type' => 'auto_correction',
                    'code' => 'ORPHAN_STEP_LINKED',
                    'message' => "Linked orphan step to previous step",
                    'step_id' => $stepId,
                    'linked_to' => $previousStep->getId()
                ];
            } else {
                $correctedSteps[] = $step;
            }
        }
        
        return ['corrected' => $hasCorrections, 'steps' => $correctedSteps];
    }
    
    /**
     * Ensure there is a final step
     * 
     * @param array $steps Steps to check
     * @param array $diagnostics Diagnostics array
     * @return array Fix result
     */
    private function ensureFinalStep(array $steps, array &$diagnostics): array
    {
        if (!$this->validationPolicies['require_final_step']) {
            return ['corrected' => false, 'steps' => $steps];
        }
        
        // Check if last step is marked as final
        $lastStep = end($steps);
        $metadata = $lastStep->getMetadata();
        
        if (!($metadata['is_final'] ?? false)) {
            // Correction: Mark last step as final
            $metadata['is_final'] = true;
            
            $correctedLastStep = new TaskStep(
                $lastStep->getId(),
                $lastStep->getType(),
                $lastStep->getDescription(),
                $metadata
            );
            
            $correctedSteps = $steps;
            $correctedSteps[count($correctedSteps) - 1] = $correctedLastStep;
            
            $diagnostics[] = [
                'type' => 'auto_correction',
                'code' => 'FINAL_STEP_MARKED',
                'message' => "Marked last step as final",
                'step_id' => $lastStep->getId()
            ];
            
            return ['corrected' => true, 'steps' => $correctedSteps];
        }
        
        return ['corrected' => false, 'steps' => $steps];
    }
    
    /**
     * Internal-only fallback if external data is doubtful
     * 
     * @param array $steps Steps to check
     * @param array $context Execution context
     * @param array $diagnostics Diagnostics array
     * @return array Fix result
     */
    private function applyInternalFallbackIfNeeded(array $steps, array $context, array &$diagnostics): array
    {
        if (!$this->validationPolicies['fallback_to_internal_only']) {
            return ['corrected' => false, 'steps' => $steps];
        }
        
        // Check if external data is reliable
        $externalDataReliable = $this->assessExternalDataReliability($context);
        
        if ($externalDataReliable) {
            return ['corrected' => false, 'steps' => $steps];
        }
        
        // Correction: Modify external steps to use only internal data
        $correctedSteps = [];
        $hasCorrections = false;
        
        foreach ($steps as $step) {
            $stepType = $step->getType();
            $metadata = $step->getMetadata();
            
            if ($stepType === 'collect_competitor_market_data') {
                // Force internal cache only
                $metadata['force_internal_only'] = true;
                $metadata['external_access_blocked'] = 'unreliable_external_data';
                
                $correctedStep = new TaskStep(
                    $step->getId(),
                    $step->getType(),
                    $step->getDescription(),
                    $metadata
                );
                
                $correctedSteps[] = $correctedStep;
                $hasCorrections = true;
                
                $diagnostics[] = [
                    'type' => 'auto_correction',
                    'code' => 'INTERNAL_FALLBACK_APPLIED',
                    'message' => "Forced internal-only mode due to unreliable external data",
                    'step_id' => $step->getId(),
                    'reason' => 'external_data_unreliable'
                ];
            } else {
                $correctedSteps[] = $step;
            }
        }
        
        return ['corrected' => $hasCorrections, 'steps' => $correctedSteps];
    }
    
    /**
     * Assess external data reliability
     * 
     * @param array $context Execution context
     * @return bool True if reliable
     */
    private function assessExternalDataReliability(array $context): bool
    {
        $policy = $context['policy'] ?? [];
        
        // Reliability criteria
        $hasApiKey = !empty($policy['serpapi_key'] ?? '');
        $externalAllowed = $policy['allow_external'] ?? false;
        $hasValidCache = !empty($context['internal_competitor_cache'] ?? []);
        
        // External data reliable if API configured AND allowed
        return $hasApiKey && $externalAllowed;
    }
    
    /**
     * Final validation after corrections
     * 
     * @param array $steps Corrected steps
     * @param array $dependencies Dependency graph
     * @param array $diagnostics Diagnostics array
     * @return array Validation result
     */
    private function validateFinalPlan(array $steps, array $dependencies, array &$diagnostics): array
    {
        // Final checks
        $issues = [];
        
        // 1. Check no cycles remain
        if ($this->hasCycles($steps, $dependencies)) {
            $issues[] = 'circular_dependencies_remain';
        }
        
        // 2. Check step type coherence
        if (!$this->validateStepTypeCoherence($steps)) {
            $issues[] = 'step_type_incoherence';
        }
        
        // 3. Check all dependencies exist
        if (!$this->validateDependenciesExist($steps, $dependencies)) {
            $issues[] = 'missing_dependencies';
        }
        
        if (!empty($issues)) {
            $diagnostics[] = [
                'type' => 'validation_error',
                'code' => 'FINAL_VALIDATION_FAILED',
                'message' => 'Plan validation failed after corrections',
                'issues' => $issues
            ];
            
            return ['valid' => false, 'reason' => 'final_validation_failed'];
        }
        
        $diagnostics[] = [
            'type' => 'validation_success',
            'code' => 'PLAN_VALIDATED',
            'message' => 'Plan successfully validated and corrected',
            'steps_count' => count($steps)
        ];
        
        return ['valid' => true, 'reason' => 'validated'];
    }
    
    /**
     * Check if cycles remain
     * 
     * @param array $steps Steps to check
     * @param array $dependencies Dependency graph
     * @return bool True if cycles exist
     */
    private function hasCycles(array $steps, array $dependencies): bool
    {
        $visited = [];
        $recursionStack = [];
        
        foreach ($steps as $step) {
            $stepId = $step->getId();
            if (!isset($visited[$stepId])) {
                if ($this->hasCycleDFS($stepId, $dependencies, $visited, $recursionStack)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function hasCycleDFS(string $stepId, array $dependencies, array &$visited, array &$recursionStack): bool
    {
        $visited[$stepId] = true;
        $recursionStack[$stepId] = true;
        
        $dependsOn = $dependencies[$stepId]['depends_on'] ?? [];
        foreach ($dependsOn as $depId) {
            if (!isset($visited[$depId])) {
                if ($this->hasCycleDFS($depId, $dependencies, $visited, $recursionStack)) {
                    return true;
                }
            } elseif (isset($recursionStack[$depId]) && $recursionStack[$depId]) {
                return true;
            }
        }
        
        $recursionStack[$stepId] = false;
        return false;
    }
    
    /**
     * Validate step type coherence
     * 
     * @param array $steps Steps to validate
     * @return bool True if coherent
     */
    private function validateStepTypeCoherence(array $steps): bool
    {
        $allowedTypes = [
            'collect_our_product_data',
            'collect_competitor_market_data',
            'competitive_analysis_synthesis',
            'load_product_catalog_data',
            'pattern_extraction',
            'pattern_frequency_ranking',
            'pattern_synthesis',
            'price_data_collection',
            'price_analysis',
            'price_insights_synthesis',
            'analytics_query',
            'semantic_search',
            'fallback'
        ];
        
        foreach ($steps as $step) {
            if (!in_array($step->getType(), $allowedTypes, true)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate that all dependencies exist
     * 
     * @param array $steps Steps to validate
     * @param array $dependencies Dependency graph
     * @return bool True if all exist
     */
    private function validateDependenciesExist(array $steps, array $dependencies): bool
    {
        $stepIds = array_map(fn($step) => $step->getId(), $steps);
        
        foreach ($dependencies as $stepId => $deps) {
            foreach ($deps['depends_on'] as $depId) {
                if (!in_array($depId, $stepIds, true)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Update validation policies
     * 
     * @param array $newPolicies New policies to merge
     * @return void
     */
    public function updatePolicies(array $newPolicies): void
    {
        $this->validationPolicies = array_merge($this->validationPolicies, $newPolicies);
        
        if ($this->debug) {
            $this->logDebug("Validation policies updated: " . json_encode($newPolicies));
        }
    }
    
    /**
     * Get current policies
     * 
     * @return array Current validation policies
     */
    public function getPolicies(): array
    {
        return $this->validationPolicies;
    }
    
    private function logDebug(string $message): void
    {
        if ($this->securityLogger) {
            $this->securityLogger->logSecurityEvent($message, 'info');
        }
        
        if ($this->debug) {
            error_log("[TaskPlanValidator] $message");
        }
    }
}
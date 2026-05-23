<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Config\DomainConfig;

/**
 * LLMPromptBuilder - Builds structured prompts for LLM-based weight analysis
 * 
 * Creates comprehensive prompts that guide the LLM to analyze critic profiles,
 * evaluation context, and historical data to determine adaptive weights.
 * Uses Pure LLM approach - no fixed formulas, all decisions made by LLM.
 * 
 * Prompts are loaded from language files (ClicShoppingAdmin/Core/Languages/.../ecommerce/rag_adaptive_weighting.txt)
 * Input sanitization uses HTML::sanitize() for security.
 * 
 * Requirements: 1.1, 1.2, 1.3, 10.1, 10.2
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine
 * @version 1.0.0
 * @since 2026-02-06
 */
class LLMPromptBuilder
{
    private $language;
    
    /**
     * Constructor - Initialize language system
     */
    public function __construct()
    {
        $this->language = Registry::get('Language');
        
        DomainConfig::loadLanguageFile('rag_adaptive_weighting');
    }
    
    /**
     * Build weight analysis prompt for LLM
     * 
     * Creates a structured prompt that includes:
     * - Evaluation context (output_type, priority, special requirements)
     * - Critic profiles (reputation, domain, expertise, confidence, recent performance)
     * - Instructions for Pure LLM analysis (no formulas)
     * - Expected JSON response format
     * 
     * Requirements: 1.1, 1.2, 1.3, 10.1, 10.2
     * 
     * @param array $criticData Array of critic data from CriticDataCollector
     * @param array $evaluationContext Evaluation context information
     * @return string Structured prompt for LLM
     */
    public function buildWeightAnalysisPrompt(array $criticData, array $evaluationContext): string
    {
        // Sanitize inputs to prevent prompt injection
        $sanitizedContext = $this->sanitizeContext($evaluationContext);
        $sanitizedCritics = $this->sanitizeCriticData($criticData);
        
        // Build the prompt sections
        $systemInstructions = $this->buildSystemInstructions();
        $contextSection = $this->buildContextSection($sanitizedContext);
        $criticsSection = $this->buildCriticsSection($sanitizedCritics);
        $analysisInstructions = $this->buildAnalysisInstructions();
        $responseFormat = $this->buildResponseFormat();
        
        // Combine all sections
        $prompt = implode("\n\n", [
            $systemInstructions,
            $contextSection,
            $criticsSection,
            $analysisInstructions,
            $responseFormat
        ]);
        
        return $prompt;
    }
    
    /**
     * Build system instructions section
     * 
     * Loads instructions from language file.
     * Emphasizes Pure LLM mode - no formulas, natural language reasoning.
     * 
     * Requirements: 1.1, 10.1
     * 
     * @return string System instructions
     */
    private function buildSystemInstructions(): string
    {
        return $this->language->getDef('text_system_instructions');
    }
    
    /**
     * Build evaluation context section
     * 
     * Describes the evaluation context using language file templates.
     * 
     * Requirements: 1.3, 10.2
     * 
     * @param array $context Sanitized evaluation context
     * @return string Context section
     */
    private function buildContextSection(array $context): string
    {
        $outputType = $context['output_type'] ?? 'unknown';
        $priority = $context['priority'] ?? 'medium';
        $requiredDomains = $context['required_domains'] ?? [];
        $specialRequirements = $context['special_requirements'] ?? [];
        
        $section = $this->language->getDef('text_context_header') . "\n\n";
        $section .= sprintf($this->language->getDef('text_context_output_type'), $outputType) . "\n";
        $section .= sprintf($this->language->getDef('text_context_priority'), $priority) . "\n";
        
        if (!empty($requiredDomains)) {
            $domainsList = implode(', ', $requiredDomains);
            $section .= sprintf($this->language->getDef('text_context_required_domains'), $domainsList) . "\n";
        }
        
        if (!empty($specialRequirements)) {
            $section .= $this->language->getDef('text_context_special_requirements') . "\n";
            foreach ($specialRequirements as $requirement) {
                $section .= sprintf($this->language->getDef('text_context_requirement_item'), $requirement) . "\n";
            }
        }
        
        // Add context description if available
        if (isset($context['description'])) {
            $section .= "\n" . sprintf($this->language->getDef('text_context_description'), $context['description']) . "\n";
        }
        
        return $section;
    }
    
    /**
     * Build critics section
     * 
     * Provides detailed information about each critic using language file templates.
     * 
     * Requirements: 1.2, 1.3
     * 
     * @param array $critics Sanitized critic data
     * @return string Critics section
     */
    private function buildCriticsSection(array $critics): string
    {
        $section = $this->language->getDef('text_critics_header') . "\n\n";
        
        foreach ($critics as $criticId => $data) {
            $criticName = $data['critic_name'] ?? $criticId;
            $section .= sprintf($this->language->getDef('text_critic_section_header'), $criticName, $criticId) . "\n\n";
            
            // Reputation information
            $reputation = $data['reputation'] ?? [];
            $section .= $this->language->getDef('text_reputation_header') . "\n";
            $section .= sprintf($this->language->getDef('text_reputation_score'), $reputation['score'] ?? 0.75) . "\n";
            $section .= sprintf($this->language->getDef('text_reputation_status'), $reputation['status'] ?? 'unknown') . "\n";
            $section .= sprintf($this->language->getDef('text_reputation_total_evaluations'), $reputation['total_evaluations'] ?? 0) . "\n";
            
            if (isset($reputation['consensus_alignment'])) {
                $section .= sprintf($this->language->getDef('text_reputation_consensus_alignment'), $reputation['consensus_alignment']) . "\n";
            }
            if (isset($reputation['feedback_quality'])) {
                $section .= sprintf($this->language->getDef('text_reputation_feedback_quality'), $reputation['feedback_quality']) . "\n";
            }
            if (isset($reputation['consistency_score'])) {
                $section .= sprintf($this->language->getDef('text_reputation_consistency'), $reputation['consistency_score']) . "\n";
            }
            if (isset($reputation['expertise_accuracy'])) {
                $section .= sprintf($this->language->getDef('text_reputation_expertise_accuracy'), $reputation['expertise_accuracy']) . "\n";
            }
            
            // Domain expertise
            $domains = $data['domain'] ?? ['general'];
            $expertiseLevel = $data['expertise_level'] ?? 0.5;
            
            $section .= "\n" . $this->language->getDef('text_domain_header') . "\n";
            $section .= sprintf($this->language->getDef('text_domain_list'), implode(', ', $domains)) . "\n";
            $section .= sprintf($this->language->getDef('text_domain_expertise_level'), $expertiseLevel) . "\n";
            
            // Confidence information
            $confidence = $data['confidence'] ?? [];
            $section .= "\n" . $this->language->getDef('text_confidence_header') . "\n";
            $section .= sprintf($this->language->getDef('text_confidence_current'), $confidence['current_confidence'] ?? 0.7) . "\n";
            $section .= sprintf($this->language->getDef('text_confidence_average'), $confidence['average_confidence'] ?? 0.7) . "\n";
            
            if (isset($confidence['confidence_stability'])) {
                $section .= sprintf($this->language->getDef('text_confidence_stability'), $confidence['confidence_stability']) . "\n";
            }
            if (isset($confidence['over_confidence_detected']) && $confidence['over_confidence_detected']) {
                $section .= $this->language->getDef('text_confidence_over_warning') . "\n";
            }
            if (isset($confidence['under_confidence_detected']) && $confidence['under_confidence_detected']) {
                $section .= $this->language->getDef('text_confidence_under_warning') . "\n";
            }
            
            // Recent performance
            $recentEvals = $data['recent_evaluations'] ?? [];
            $count30Days = $recentEvals['count_30_days'] ?? 0;
            $lastEvalDate = $data['last_evaluation_date'] ?? null;
            
            $section .= "\n" . $this->language->getDef('text_activity_header') . "\n";
            $section .= sprintf($this->language->getDef('text_activity_count'), $count30Days) . "\n";
            if ($lastEvalDate !== null) {
                $section .= sprintf($this->language->getDef('text_activity_last_date'), $lastEvalDate) . "\n";
            } else {
                $section .= $this->language->getDef('text_activity_no_recent') . "\n";
            }
            
            $section .= "\n---\n\n";
        }
        
        return $section;
    }
    
    /**
     * Build analysis instructions section
     * 
     * Loads instructions from language file.
     * 
     * Requirements: 1.1, 1.2, 10.1
     * 
     * @return string Analysis instructions
     */
    private function buildAnalysisInstructions(): string
    {
        return $this->language->getDef('text_analysis_instructions');
    }
    
    /**
     * Build response format section
     * 
     * Loads format specification from language file.
     * 
     * Requirements: 1.1, 10.2
     * 
     * @return string Response format specification
     */
    private function buildResponseFormat(): string
    {
        return $this->language->getDef('text_response_format');
    }
    
    /**
     * Sanitize evaluation context to prevent prompt injection
     * 
     * Uses HTML::sanitize() for security.
     * Ensures context data is safe to include in LLM prompt.
     * 
     * Requirements: 10.2
     * 
     * @param array $context Raw evaluation context
     * @return array Sanitized context
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];
        
        // Sanitize output_type
        if (isset($context['output_type'])) {
            $sanitized['output_type'] = HTML::sanitize(substr($context['output_type'], 0, 100));
        }
        
        // Sanitize priority
        if (isset($context['priority'])) {
            $priority = strtolower(HTML::sanitize($context['priority']));
            // Only allow valid priority values
            if (in_array($priority, ['low', 'medium', 'high', 'critical'], true)) {
                $sanitized['priority'] = $priority;
            } else {
                $sanitized['priority'] = 'medium';
            }
        }
        
        // Sanitize required_domains
        if (isset($context['required_domains']) && is_array($context['required_domains'])) {
            $sanitized['required_domains'] = array_map(
                fn($domain) => HTML::sanitize(substr($domain, 0, 50)),
                $context['required_domains']
            );
        }
        
        // Sanitize special_requirements
        if (isset($context['special_requirements']) && is_array($context['special_requirements'])) {
            $sanitized['special_requirements'] = array_map(
                fn($req) => HTML::sanitize(substr($req, 0, 200)),
                array_slice($context['special_requirements'], 0, 10) // Limit to 10 requirements
            );
        }
        
        // Sanitize description
        if (isset($context['description'])) {
            $sanitized['description'] = HTML::sanitize(substr($context['description'], 0, 500));
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize critic data to prevent prompt injection
     * 
     * Uses HTML::sanitize() for all string fields.
     * Validates and sanitizes all critic data fields.
     * Ensures numeric values are within expected ranges.
     * 
     * Requirements: 10.2
     * 
     * @param array $criticData Raw critic data
     * @return array Sanitized critic data
     */
    private function sanitizeCriticData(array $criticData): array
    {
        $sanitized = [];
        
        foreach ($criticData as $criticId => $data) {
            // Sanitize critic_id
            $safeCriticId = HTML::sanitize(substr($criticId, 0, 100));
            
            $sanitized[$safeCriticId] = [
                'critic_id' => $safeCriticId,
                'critic_name' => HTML::sanitize(substr($data['critic_name'] ?? $safeCriticId, 0, 100)),
                'reputation' => $this->sanitizeReputation($data['reputation'] ?? []),
                'domain' => $this->sanitizeDomains($data['domain'] ?? ['general']),
                'expertise_level' => $this->sanitizeFloat($data['expertise_level'] ?? 0.5, 0.0, 1.0),
                'confidence' => $this->sanitizeConfidence($data['confidence'] ?? []),
                'recent_evaluations' => $this->sanitizeRecentEvaluations($data['recent_evaluations'] ?? []),
                'last_evaluation_date' => HTML::sanitize(substr($data['last_evaluation_date'] ?? '', 0, 50)),
                'total_evaluations' => $this->sanitizeInt($data['total_evaluations'] ?? 0, 0, 1000000)
            ];
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize reputation data
     * 
     * @param array $reputation Raw reputation data
     * @return array Sanitized reputation data
     */
    private function sanitizeReputation(array $reputation): array
    {
        return [
            'score' => $this->sanitizeFloat($reputation['score'] ?? 0.75, 0.0, 1.0),
            'status' => HTML::sanitize(substr($reputation['status'] ?? 'unknown', 0, 50)),
            'consensus_alignment' => $this->sanitizeFloat($reputation['consensus_alignment'] ?? 0.75, 0.0, 1.0),
            'feedback_quality' => $this->sanitizeFloat($reputation['feedback_quality'] ?? 0.75, 0.0, 1.0),
            'consistency_score' => $this->sanitizeFloat($reputation['consistency_score'] ?? 0.75, 0.0, 1.0),
            'expertise_accuracy' => $this->sanitizeFloat($reputation['expertise_accuracy'] ?? 0.75, 0.0, 1.0),
            'total_evaluations' => $this->sanitizeInt($reputation['total_evaluations'] ?? 0, 0, 1000000)
        ];
    }
    
    /**
     * Sanitize domain list
     * 
     * @param array $domains Raw domain list
     * @return array Sanitized domain list
     */
    private function sanitizeDomains(array $domains): array
    {
        return array_map(
            fn($domain) => HTML::sanitize(substr($domain, 0, 50)),
            array_slice($domains, 0, 10) // Limit to 10 domains
        );
    }
    
    /**
     * Sanitize confidence data
     * 
     * @param array $confidence Raw confidence data
     * @return array Sanitized confidence data
     */
    private function sanitizeConfidence(array $confidence): array
    {
        return [
            'current_confidence' => $this->sanitizeFloat($confidence['current_confidence'] ?? 0.7, 0.0, 1.0),
            'average_confidence' => $this->sanitizeFloat($confidence['average_confidence'] ?? 0.7, 0.0, 1.0),
            'confidence_stability' => $this->sanitizeFloat($confidence['confidence_stability'] ?? 0.8, 0.0, 1.0),
            'over_confidence_detected' => (bool)($confidence['over_confidence_detected'] ?? false),
            'under_confidence_detected' => (bool)($confidence['under_confidence_detected'] ?? false)
        ];
    }
    
    /**
     * Sanitize recent evaluations data
     * 
     * @param array $recentEvals Raw recent evaluations data
     * @return array Sanitized recent evaluations data
     */
    private function sanitizeRecentEvaluations(array $recentEvals): array
    {
        return [
            'count_30_days' => $this->sanitizeInt($recentEvals['count_30_days'] ?? 0, 0, 10000),
            'latest_evaluations' => [] // Omit detailed evaluation data from prompt for brevity
        ];
    }
    
    /**
     * Sanitize float value
     * 
     * Ensures value is numeric and within specified range.
     * 
     * @param mixed $value Raw value
     * @param float $min Minimum allowed value
     * @param float $max Maximum allowed value
     * @return float Sanitized float
     */
    private function sanitizeFloat($value, float $min, float $max): float
    {
        $float = (float)$value;
        
        if ($float < $min) {
            return $min;
        }
        
        if ($float > $max) {
            return $max;
        }
        
        return $float;
    }
    
    /**
     * Sanitize integer value
     * 
     * Ensures value is numeric and within specified range.
     * 
     * @param mixed $value Raw value
     * @param int $min Minimum allowed value
     * @param int $max Maximum allowed value
     * @return int Sanitized integer
     */
    private function sanitizeInt($value, int $min, int $max): int
    {
        $int = (int)$value;
        
        if ($int < $min) {
            return $min;
        }
        
        if ($int > $max) {
            return $max;
        }
        
        return $int;
    }
    
    /**
     * Build critic selection prompt for LLM
     * 
     * Creates a structured prompt for LLM-based critic selection that includes:
     * - Evaluation context (output_type, priority, required domains, special requirements)
     * - Available critic profiles (reputation, domain expertise, confidence, load, recent performance)
     * - Selection criteria (expertise relevance, diversity, quality, availability)
     * - Multi-domain selection strategy
     * - Expected JSON response format
     * 
     * The LLM determines:
     * - Which critics to include based on domain expertise match
     * - Optimal number of critics (not fixed at 3)
     * - Balance between domain specialists and generalists
     * - Diversity of perspectives
     * 
     * Requirements: 16.1, 16.2, 16.3, 16.4, 15.1, 15.2, 15.3, 15.4
     * 
     * @param array $criticData Array of critic data from CriticDataCollector
     * @param array $evaluationContext Evaluation context information
     * @return string Structured prompt for LLM critic selection
     */
    public function buildCriticSelectionPrompt(array $criticData, array $evaluationContext): string
    {
        // Sanitize inputs to prevent prompt injection
        $sanitizedContext = $this->sanitizeContext($evaluationContext);
        $sanitizedCritics = $this->sanitizeCriticData($criticData);
        
        // Build the prompt sections
        $systemInstructions = $this->buildSelectionSystemInstructions();
        $contextSection = $this->buildSelectionContextSection($sanitizedContext);
        $criticsSection = $this->buildSelectionCriticsSection($sanitizedCritics);
        $selectionCriteria = $this->buildSelectionCriteria();
        $responseFormat = $this->buildSelectionResponseFormat();
        
        // Combine all sections
        $prompt = implode("\n\n", [
            $systemInstructions,
            $contextSection,
            $criticsSection,
            $selectionCriteria,
            $responseFormat
        ]);
        
        return $prompt;
    }
    
    /**
     * Build system instructions for critic selection
     * 
     * Emphasizes multi-domain selection strategy and Pure LLM approach.
     * 
     * Requirements: 16.1, 16.2
     * 
     * @return string System instructions
     */
    private function buildSelectionSystemInstructions(): string
    {
        return <<<INSTRUCTIONS
ROLE: You are an expert in selecting optimal critics for evaluations based on domain expertise, reputation, and diversity.

TASK: Analyze the available critics and evaluation context to select the most appropriate critics for this evaluation.

MULTI-DOMAIN SELECTION STRATEGY:
- Prioritize critics with expertise in required domains
- Balance domain specialists (deep expertise in one domain) vs generalists (broad expertise across domains)
- Ensure coverage of all required domains if possible
- Consider domain expertise level (expert > competent > novice)
- Balance expertise relevance with diversity of perspectives
- Ensure minimum 2 critics for valid consensus
INSTRUCTIONS;
    }
    
    /**
     * Build evaluation context section for critic selection
     * 
     * Requirements: 16.1, 16.3
     * 
     * @param array $context Sanitized evaluation context
     * @return string Context section
     */
    private function buildSelectionContextSection(array $context): string
    {
        $outputType = $context['output_type'] ?? 'unknown';
        $priority = $context['priority'] ?? 'medium';
        $requiredDomains = $context['required_domains'] ?? [];
        $specialRequirements = $context['special_requirements'] ?? [];
        
        $section = "EVALUATION CONTEXT:\n\n";
        $section .= "- Output Type: '{$outputType}'\n";
        $section .= "- Priority Level: '{$priority}'\n";
        
        if (!empty($requiredDomains)) {
            $domainsList = implode(', ', $requiredDomains);
            $section .= "- Required Domains: [{$domainsList}]\n";
        }
        
        if (!empty($specialRequirements)) {
            $section .= "- Special Requirements:\n";
            foreach ($specialRequirements as $requirement) {
                $section .= "  * {$requirement}\n";
            }
        }
        
        if (isset($context['description'])) {
            $section .= "\nContext Description: {$context['description']}\n";
        }
        
        return $section;
    }
    
    /**
     * Build available critics section for selection
     * 
     * Requirements: 16.1, 16.2
     * 
     * @param array $critics Sanitized critic data
     * @return string Critics section
     */
    private function buildSelectionCriticsSection(array $critics): string
    {
        $section = "AVAILABLE CRITICS:\n\n";
        
        foreach ($critics as $criticId => $data) {
            $criticName = $data['critic_name'] ?? $criticId;
            $section .= "Critic: {$criticName} (ID: {$criticId})\n";
            
            // Domain expertise
            $domains = $data['domain'] ?? ['general'];
            $expertiseLevel = $data['expertise_level'] ?? 0.5;
            $expertiseLabel = $this->getExpertiseLevelLabel($expertiseLevel);
            
            $section .= "- Domain Expertise: " . implode(', ', $domains) . " ({$expertiseLabel})\n";
            $section .= "- Expertise Level: {$expertiseLevel}\n";
            
            // Reputation
            $reputation = $data['reputation'] ?? [];
            $reputationScore = $reputation['score'] ?? 0.75;
            $section .= "- Reputation Score: {$reputationScore}\n";
            
            // Confidence
            $confidence = $data['confidence'] ?? [];
            $currentConfidence = $confidence['current_confidence'] ?? 0.7;
            $section .= "- Confidence: {$currentConfidence}\n";
            
            // Recent activity
            $recentEvals = $data['recent_evaluations'] ?? [];
            $count30Days = $recentEvals['count_30_days'] ?? 0;
            $lastEvalDate = $data['last_evaluation_date'] ?? 'Never';
            
            $section .= "- Recent Activity: {$count30Days} evaluations in last 30 days\n";
            $section .= "- Last Evaluation: {$lastEvalDate}\n";
            
            $section .= "\n";
        }
        
        return $section;
    }
    
    /**
     * Build selection criteria section
     * 
     * Requirements: 16.2, 16.3, 16.4
     * 
     * @return string Selection criteria
     */
    private function buildSelectionCriteria(): string
    {
        return <<<CRITERIA
SELECTION CRITERIA:

1. Domain Expertise Match:
   - How well does the critic's domain expertise align with required domains?
   - Prioritize critics with expert-level knowledge in required domains
   - Consider both depth (expertise level) and breadth (multiple domains)

2. Reputation and Performance:
   - Higher reputation scores indicate better historical performance
   - Consider recent activity and consistency

3. Confidence Levels:
   - Appropriate confidence indicates self-awareness
   - Avoid over-confident or under-confident critics if better options exist

4. Diversity of Perspectives:
   - Select critics with different domain specializations when possible
   - Balance specialists (deep in one domain) vs generalists (broad across domains)
   - Ensure multiple viewpoints for robust consensus

5. Optimal Number:
   - Determine the optimal number of critics for this evaluation
   - Minimum 2 critics required for valid consensus
   - More critics for critical evaluations, fewer for routine ones
   - Consider available expertise and diversity needs

6. Domain Coverage:
   - Ensure all required domains are covered if possible
   - If not all domains can be covered, prioritize most critical domains
CRITERIA;
    }
    
    /**
     * Build response format for critic selection
     * 
     * Requirements: 16.3, 16.4
     * 
     * @return string Response format specification
     */
    private function buildSelectionResponseFormat(): string
    {
        return <<<FORMAT
OUTPUT FORMAT:

You MUST respond with valid JSON only. Do not include any text before or after the JSON object.

{
  "selected_critics": ["critic_id1", "critic_id2", ...],
  "selection_rationale": "Overall reasoning for this selection",
  "critic_explanations": {
    "critic_id1": "Why this critic was selected (mention domain expertise, reputation, etc.)",
    "critic_id2": "Why this critic was selected",
    ...
  },
  "rejected_critics": {
    "critic_id_x": "Why this critic was not selected",
    ...
  },
  "optimal_count": <number of critics selected>,
  "diversity_score": <0.0-1.0, how diverse is the selected group>,
  "domain_coverage": {
    "domain1": ["critic_id1", "critic_id2"],
    "domain2": ["critic_id2"],
    ...
  }
}

IMPORTANT RULES:
- Select at least 2 critics (minimum for valid consensus)
- Explain your reasoning for each selection and rejection
- Consider domain expertise match as primary factor
- Balance quality (reputation) with diversity
- Determine optimal number based on evaluation criticality and available expertise
FORMAT;
    }
    
    /**
     * Get expertise level label
     * 
     * Converts numeric expertise level to human-readable label.
     * 
     * @param float $level Expertise level (0.0-1.0)
     * @return string Expertise label
     */
    private function getExpertiseLevelLabel(float $level): string
    {
        if ($level >= 0.8) {
            return 'expert';
        } elseif ($level >= 0.6) {
            return 'competent';
        } else {
            return 'novice';
        }
    }
    
    /**
     * Build bounds determination prompt for LLM
     * 
     * Creates a structured prompt for LLM-based weight bounds determination that includes:
     * - Evaluation context (output_type, priority, required domains, special requirements)
     * - Critic information (number of critics, expertise distribution, reputation range)
     * - Bounds determination criteria (number of critics, criticality, diversity needs)
     * - Typical bounds reference (0.1 - 0.5)
     * - Expected JSON response format
     * 
     * The LLM determines:
     * - Appropriate minimum weight bound
     * - Appropriate maximum weight bound
     * - Rationale for the chosen bounds
     * - Explanation of factors considered
     * 
     * Requirements: 14.1, 14.2, 14.3, 14.4
     * 
     * @param array $criticData Array of critic data from CriticDataCollector
     * @param array $evaluationContext Evaluation context information
     * @return string Structured prompt for LLM bounds determination
     */
    public function buildBoundsDeterminationPrompt(array $criticData, array $evaluationContext): string
    {
        // Sanitize inputs to prevent prompt injection
        $sanitizedContext = $this->sanitizeContext($evaluationContext);
        $sanitizedCritics = $this->sanitizeCriticData($criticData);
        
        // Build the prompt sections
        $systemInstructions = $this->buildBoundsSystemInstructions();
        $contextSection = $this->buildBoundsContextSection($sanitizedContext);
        $criticsSection = $this->buildBoundsCriticsSection($sanitizedCritics);
        $boundsGuidance = $this->buildBoundsGuidance();
        $responseFormat = $this->buildBoundsResponseFormat();
        
        // Combine all sections
        $prompt = implode("\n\n", [
            $systemInstructions,
            $contextSection,
            $criticsSection,
            $boundsGuidance,
            $responseFormat
        ]);
        
        return $prompt;
    }
    
    /**
     * Build system instructions for bounds determination
     * 
     * Emphasizes context-aware bounds determination and Pure LLM approach.
     * 
     * Requirements: 14.1, 14.2
     * 
     * @return string System instructions
     */
    private function buildBoundsSystemInstructions(): string
    {
        return <<<INSTRUCTIONS
ROLE: You are an expert in determining appropriate weight bounds for critic consensus building.

TASK: Analyze the evaluation context and critic pool to determine appropriate minimum and maximum weight bounds.

OBJECTIVE: Determine bounds that:
- Prevent single critic dominance while allowing expertise to shine
- Ensure all critics have meaningful influence (no critic is effectively silenced)
- Balance quality (allowing experts higher weight) with diversity (ensuring all voices heard)
- Adapt to the specific evaluation context and criticality
INSTRUCTIONS;
    }
    
    /**
     * Build evaluation context section for bounds determination
     * 
     * Requirements: 14.1, 14.3
     * 
     * @param array $context Sanitized evaluation context
     * @return string Context section
     */
    private function buildBoundsContextSection(array $context): string
    {
        $outputType = $context['output_type'] ?? 'unknown';
        $priority = $context['priority'] ?? 'medium';
        $requiredDomains = $context['required_domains'] ?? [];
        $specialRequirements = $context['special_requirements'] ?? [];
        
        $section = "EVALUATION CONTEXT:\n\n";
        $section .= "- Output Type: '{$outputType}'\n";
        $section .= "- Priority Level: '{$priority}'\n";
        
        if (!empty($requiredDomains)) {
            $domainsList = implode(', ', $requiredDomains);
            $section .= "- Required Domains: [{$domainsList}]\n";
        }
        
        if (!empty($specialRequirements)) {
            $section .= "- Special Requirements:\n";
            foreach ($specialRequirements as $requirement) {
                $section .= "  * {$requirement}\n";
            }
        }
        
        if (isset($context['description'])) {
            $section .= "\nContext Description: {$context['description']}\n";
        }
        
        return $section;
    }
    
    /**
     * Build critics summary section for bounds determination
     * 
     * Provides aggregate information about the critic pool rather than individual details.
     * 
     * Requirements: 14.1, 14.3
     * 
     * @param array $critics Sanitized critic data
     * @return string Critics summary section
     */
    private function buildBoundsCriticsSection(array $critics): string
    {
        $numCritics = count($critics);
        
        // Calculate reputation statistics
        $reputations = array_map(fn($c) => $c['reputation']['score'] ?? 0.75, $critics);
        $avgReputation = count($reputations) > 0 ? array_sum($reputations) / count($reputations) : 0.75;
        $minReputation = count($reputations) > 0 ? min($reputations) : 0.75;
        $maxReputation = count($reputations) > 0 ? max($reputations) : 0.75;
        
        // Calculate expertise statistics
        $expertiseLevels = array_map(fn($c) => $c['expertise_level'] ?? 0.5, $critics);
        $avgExpertise = count($expertiseLevels) > 0 ? array_sum($expertiseLevels) / count($expertiseLevels) : 0.5;
        $minExpertise = count($expertiseLevels) > 0 ? min($expertiseLevels) : 0.5;
        $maxExpertise = count($expertiseLevels) > 0 ? max($expertiseLevels) : 0.5;
        
        // Count expertise levels
        $experts = count(array_filter($expertiseLevels, fn($e) => $e >= 0.8));
        $competent = count(array_filter($expertiseLevels, fn($e) => $e >= 0.6 && $e < 0.8));
        $novices = count(array_filter($expertiseLevels, fn($e) => $e < 0.6));
        
        // Collect unique domains
        $allDomains = [];
        foreach ($critics as $critic) {
            $domains = $critic['domain'] ?? ['general'];
            $allDomains = array_merge($allDomains, $domains);
        }
        $uniqueDomains = array_unique($allDomains);
        
        $section = "CRITIC POOL SUMMARY:\n\n";
        $section .= "- Number of Critics: {$numCritics}\n";
        $section .= "- Reputation Range: {$minReputation} - {$maxReputation} (avg: " . round($avgReputation, 3) . ")\n";
        $section .= "- Expertise Level Range: {$minExpertise} - {$maxExpertise} (avg: " . round($avgExpertise, 3) . ")\n";
        $section .= "- Expertise Distribution: {$experts} experts, {$competent} competent, {$novices} novices\n";
        $section .= "- Domain Coverage: " . count($uniqueDomains) . " unique domains (" . implode(', ', array_slice($uniqueDomains, 0, 5));
        if (count($uniqueDomains) > 5) {
            $section .= ", +" . (count($uniqueDomains) - 5) . " more";
        }
        $section .= ")\n";
        
        return $section;
    }
    
    /**
     * Build bounds determination guidance section
     * 
     * Provides guidance on factors to consider when determining bounds.
     * 
     * Requirements: 14.1, 14.2, 14.3
     * 
     * @return string Bounds guidance
     */
    private function buildBoundsGuidance(): string
    {
        return <<<GUIDANCE
BOUNDS DETERMINATION GUIDANCE:

TYPICAL BOUNDS: 0.1 (min) to 0.5 (max)
- These bounds work well for most scenarios with 3-5 critics
- Ensures no critic is silenced (min 10% influence)
- Prevents single critic dominance (max 50% influence)

FACTORS TO CONSIDER:

1. Number of Critics:
   - 2 critics: Wider bounds (e.g., 0.05-0.7) allow meaningful differentiation
   - 3-5 critics: Typical bounds (0.1-0.5) provide good balance
   - 6+ critics: Tighter bounds (e.g., 0.15-0.4) prevent dominance

2. Evaluation Criticality:
   - Critical evaluations: Allow wider max bound (e.g., 0.6) to let experts dominate
   - High priority: Typical bounds with slight adjustment
   - Medium/Low priority: Tighter bounds to ensure diversity

3. Diversity Needs:
   - High diversity needed: Tighter bounds (e.g., 0.15-0.4) ensure all voices heard
   - Expertise-focused: Wider bounds (e.g., 0.1-0.6) allow experts to lead
   - Balanced: Typical bounds

4. Expertise Distribution:
   - Wide expertise gap: Wider bounds allow experts to have more influence
   - Similar expertise: Tighter bounds since all critics are comparable
   - Few experts: Wider max bound to leverage expert knowledge

5. Special Requirements:
   - Security-critical: May need wider bounds to trust expert judgment
   - Performance-critical: Similar to security
   - Exploratory/Research: Tighter bounds to encourage diverse perspectives

BOUNDS CONSTRAINTS:
- Minimum bound must be >= 0.0 (cannot be negative)
- Maximum bound must be <= 1.0 (cannot exceed 100%)
- Minimum must be < Maximum (must allow variation)
- Range (max - min) should be at least 0.05 (meaningful variation)

WHEN TO EXCEED TYPICAL BOUNDS:
- Explain clearly why typical bounds are insufficient
- Provide strong justification based on context
- Consider the trade-offs (quality vs diversity)
GUIDANCE;
    }
    
    /**
     * Build response format for bounds determination
     * 
     * Requirements: 14.2, 14.4
     * 
     * @return string Response format specification
     */
    private function buildBoundsResponseFormat(): string
    {
        return <<<FORMAT
OUTPUT FORMAT:

You MUST respond with valid JSON only. Do not include any text before or after the JSON object.

{
  "min_bound": <float between 0.0 and 1.0>,
  "max_bound": <float between 0.0 and 1.0>,
  "rationale": "Overall reasoning for these bounds (2-3 sentences)",
  "explanation": "Detailed explanation of why these bounds are appropriate for this specific evaluation context",
  "factors_considered": {
    "num_critics": "How the number of critics influenced your bounds decision",
    "criticality": "How evaluation criticality influenced your bounds decision",
    "diversity_needs": "How diversity requirements influenced your bounds decision",
    "context_requirements": "How special context requirements influenced your bounds decision"
  }
}

IMPORTANT RULES:
- min_bound must be >= 0.0
- max_bound must be <= 1.0
- min_bound must be < max_bound
- Range (max_bound - min_bound) must be >= 0.05
- If you suggest bounds outside typical range (0.1-0.5), provide strong justification
- Explain your reasoning clearly for each factor
FORMAT;
    }
    
    /**
     * Build anomaly detection prompt for LLM
     * 
     * Creates a structured prompt for LLM-based anomaly detection that includes:
     * - Weight history data (evaluations, critics, weights, contexts)
     * - Analysis focus areas (high weights, maximum weights, unusual distributions, gaming patterns)
     * - Expected anomaly types and severity levels
     * - Expected JSON response format
     * 
     * The LLM analyzes patterns to detect:
     * - Critics with unusually high weights across multiple evaluations
     * - Critics consistently receiving maximum weights
     * - Unusual weight distributions (e.g., one critic dominates)
     * - Sudden weight changes without corresponding reputation changes
     * - Patterns suggesting collusion or gaming
     * 
     * Requirements: 20.1, 20.3, 29.1, 29.2, 29.3, 29.4
     * 
     * @param array $weightHistory Weight history data from audit logger
     * @param int $days Number of days of history analyzed
     * @return string Structured prompt for LLM anomaly detection
     */
    public function buildAnomalyDetectionPrompt(array $weightHistory, int $days): string
    {
        // Build the prompt sections
        $systemInstructions = $this->buildAnomalySystemInstructions();
        $historySection = $this->buildWeightHistorySection($weightHistory, $days);
        $analysisFocus = $this->buildAnomalyAnalysisFocus();
        $responseFormat = $this->buildAnomalyResponseFormat();
        
        // Combine all sections
        $prompt = implode("\n\n", [
            $systemInstructions,
            $historySection,
            $analysisFocus,
            $responseFormat
        ]);
        
        return $prompt;
    }
    
    /**
     * Build system instructions for anomaly detection
     * 
     * Emphasizes pattern detection and gaming prevention.
     * 
     * Requirements: 20.1, 20.3
     * 
     * @return string System instructions
     */
    private function buildAnomalySystemInstructions(): string
    {
        return <<<INSTRUCTIONS
ROLE: You are an expert in detecting anomalies and potential gaming in weight distributions.

TASK: Analyze the following weight history to identify suspicious patterns that may indicate:
- Unusual weight distributions
- Potential gaming or manipulation attempts
- Critics receiving inappropriately high or low weights
- Sudden unexplained changes in weight patterns
- Collusion or coordination between critics

OBJECTIVE: Identify patterns that warrant investigation or corrective action.
INSTRUCTIONS;
    }
    
    /**
     * Build weight history section for anomaly detection
     * 
     * Formats weight history data for LLM analysis.
     * Includes evaluation context, critic weights, and timestamps.
     * 
     * Requirements: 20.1, 29.1
     * 
     * @param array $weightHistory Weight history data
     * @param int $days Number of days analyzed
     * @return string Weight history section
     */
    private function buildWeightHistorySection(array $weightHistory, int $days): string
    {
        $section = "WEIGHT HISTORY (Last {$days} days):\n\n";
        
        if (empty($weightHistory)) {
            $section .= "No weight history available.\n";
            return $section;
        }
        
        // Group by evaluation
        $evaluations = [];
        foreach ($weightHistory as $entry) {
            $evalId = $entry['evaluation_id'] ?? 'unknown';
            if (!isset($evaluations[$evalId])) {
                $evaluations[$evalId] = [
                    'evaluation_id' => $evalId,
                    'timestamp' => $entry['timestamp'] ?? date('Y-m-d H:i:s'),
                    'context' => $entry['context'] ?? [],
                    'weights' => []
                ];
            }
            
            $criticId = $entry['critic_id'] ?? 'unknown';
            $weight = $entry['weight'] ?? 0.0;
            $evaluations[$evalId]['weights'][$criticId] = $weight;
        }
        
        // Format each evaluation
        $count = 0;
        foreach ($evaluations as $evalId => $eval) {
            $count++;
            if ($count > 50) {
                $section .= "\n[... " . (count($evaluations) - 50) . " more evaluations omitted for brevity ...]\n";
                break;
            }
            
            $section .= "Evaluation: {$evalId}\n";
            $section .= "Timestamp: {$eval['timestamp']}\n";
            
            // Context summary
            $context = $eval['context'];
            if (!empty($context)) {
                $outputType = $context['output_type'] ?? 'unknown';
                $priority = $context['priority'] ?? 'unknown';
                $section .= "Context: {$outputType} (priority: {$priority})\n";
            }
            
            // Weights
            $section .= "Weights:\n";
            arsort($eval['weights']); // Sort by weight descending
            foreach ($eval['weights'] as $criticId => $weight) {
                $section .= "  - {$criticId}: " . round($weight, 4) . "\n";
            }
            
            $section .= "\n";
        }
        
        // Add summary statistics
        $section .= $this->buildWeightHistorySummary($weightHistory);
        
        return $section;
    }
    
    /**
     * Build weight history summary statistics
     * 
     * Provides aggregate statistics to help LLM identify patterns.
     * 
     * Requirements: 20.1, 29.1
     * 
     * @param array $weightHistory Weight history data
     * @return string Summary statistics
     */
    private function buildWeightHistorySummary(array $weightHistory): string
    {
        // Calculate per-critic statistics
        $criticStats = [];
        foreach ($weightHistory as $entry) {
            $criticId = $entry['critic_id'] ?? 'unknown';
            $weight = $entry['weight'] ?? 0.0;
            
            if (!isset($criticStats[$criticId])) {
                $criticStats[$criticId] = [
                    'weights' => [],
                    'count' => 0,
                    'sum' => 0.0,
                    'max' => 0.0,
                    'min' => 1.0
                ];
            }
            
            $criticStats[$criticId]['weights'][] = $weight;
            $criticStats[$criticId]['count']++;
            $criticStats[$criticId]['sum'] += $weight;
            $criticStats[$criticId]['max'] = max($criticStats[$criticId]['max'], $weight);
            $criticStats[$criticId]['min'] = min($criticStats[$criticId]['min'], $weight);
        }
        
        // Calculate averages and sort by average weight
        foreach ($criticStats as $criticId => &$stats) {
            $stats['avg'] = $stats['count'] > 0 ? $stats['sum'] / $stats['count'] : 0.0;
        }
        uasort($criticStats, fn($a, $b) => $b['avg'] <=> $a['avg']);
        
        $section = "SUMMARY STATISTICS:\n\n";
        $section .= "Total Evaluations: " . count(array_unique(array_column($weightHistory, 'evaluation_id'))) . "\n";
        $section .= "Total Critics: " . count($criticStats) . "\n\n";
        
        $section .= "Per-Critic Statistics (sorted by average weight):\n";
        $count = 0;
        foreach ($criticStats as $criticId => $stats) {
            $count++;
            if ($count > 20) {
                $section .= "[... " . (count($criticStats) - 20) . " more critics omitted ...]\n";
                break;
            }
            
            $section .= sprintf(
                "  - %s: avg=%.4f, max=%.4f, min=%.4f, count=%d\n",
                $criticId,
                $stats['avg'],
                $stats['max'],
                $stats['min'],
                $stats['count']
            );
        }
        
        return $section;
    }
    
    /**
     * Build anomaly analysis focus section
     * 
     * Guides the LLM on what patterns to look for.
     * 
     * Requirements: 20.1, 20.3, 29.1, 29.2, 29.3, 29.4
     * 
     * @return string Analysis focus guidance
     */
    private function buildAnomalyAnalysisFocus(): string
    {
        return <<<FOCUS
ANALYSIS FOCUS:

Look for the following suspicious patterns:

1. CONSISTENTLY HIGH WEIGHTS:
   - Critics with average weight > 0.5 across multiple evaluations
   - Critics who frequently receive the highest weight in their evaluation group
   - Pattern: May indicate over-reliance on a single critic or gaming

2. MAXIMUM WEIGHT DOMINANCE:
   - Critics consistently receiving maximum weights (close to 1.0 or upper bound)
   - Critics who dominate consensus in most evaluations
   - Pattern: May indicate inappropriate dominance or manipulation

3. UNUSUAL WEIGHT DISTRIBUTIONS:
   - Evaluations where one critic has >70% of total weight
   - Evaluations with extreme weight imbalances
   - Pattern: May indicate context mismatch or gaming

4. SUDDEN WEIGHT CHANGES:
   - Critics whose weights change dramatically without corresponding reputation changes
   - Sudden spikes or drops in weight that don't align with performance
   - Pattern: May indicate system manipulation or data issues

5. COLLUSION PATTERNS:
   - Multiple critics with suspiciously similar weight patterns
   - Critics whose weights always move together
   - Pattern: May indicate coordination or gaming

6. CONTEXT MISMATCH:
   - Critics receiving high weights in contexts outside their expertise
   - Weights that don't align with domain expertise
   - Pattern: May indicate system misconfiguration

7. GAMING INDICATORS:
   - Patterns that suggest intentional manipulation
   - Unusual timing or coordination of weight changes
   - Weights that seem artificially inflated or deflated

SEVERITY LEVELS:
- HIGH: Clear evidence of gaming, manipulation, or system failure requiring immediate action
- MEDIUM: Suspicious patterns that warrant investigation and monitoring
- LOW: Minor anomalies or edge cases that should be noted but may be explainable

For each anomaly detected, provide:
- Type of anomaly
- Affected critic(s)
- Severity level
- Description of what was detected
- Supporting evidence from the data
- Recommended action
FOCUS;
    }
    
    /**
     * Build response format for anomaly detection
     * 
     * Requirements: 20.1, 20.3, 29.1
     * 
     * @return string Response format specification
     */
    private function buildAnomalyResponseFormat(): string
    {
        return <<<FORMAT
OUTPUT FORMAT:

You MUST respond with valid JSON only. Do not include any text before or after the JSON object.

{
  "anomalies": [
    {
      "type": "anomaly_type (e.g., 'consistently_high_weights', 'maximum_weight_dominance', 'unusual_distribution', 'sudden_change', 'collusion_pattern', 'context_mismatch', 'gaming_indicator')",
      "critic_id": "critic_id (or null if anomaly is evaluation-wide)",
      "severity": "low|medium|high",
      "description": "Clear description of what was detected",
      "evidence": [
        "Specific evidence point 1",
        "Specific evidence point 2",
        "..."
      ],
      "recommendation": "Suggested action (e.g., 'Investigate critic reputation', 'Review evaluation contexts', 'Monitor for continued pattern')"
    }
  ],
  "overall_assessment": "Summary of findings - are there concerning patterns? Is the system functioning normally?"
}

IMPORTANT RULES:
- Include ALL detected anomalies, even low-severity ones
- Provide specific evidence from the data (cite evaluation IDs, weights, dates)
- Be objective - distinguish between definite issues and potential concerns
- If no anomalies detected, return empty anomalies array with positive overall assessment
- Severity should reflect confidence and impact: high = definite issue requiring action, medium = suspicious pattern needing investigation, low = minor concern or edge case
FORMAT;
    }
}


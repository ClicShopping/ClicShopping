<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

/**
 * EvaluationContextBuilder
 *
 * Evaluation-context concern extracted verbatim from {@see ActorCriticCoordinator}.
 * Builds the (domain-agnostic) context passed to the critics / LLM weighting engine
 * from an ActionResult and its Action: required capability domains and special
 * requirements. Pure derivation — no state, no DB; behaviour unchanged.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 */
class EvaluationContextBuilder
{
    private bool $debug;

    /**
     * @param bool $debug Debug logging toggle (inherited from the coordinator)
     */
    public function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Build evaluation context from action result
     *
     * Extracts required domains and other context information from the action result
     * to provide to the LLM weighting engine.
     *
     * Requirements: 1.1, 8.1
     *
     * @param ActionResult $actionResult The action result
     * @param Action $action The original action
     * @return array Evaluation context
     */
    public function build(ActionResult $actionResult, Action $action): array
    {
        // Extract required domains from action context
        $requiredDomains = $this->extractRequiredDomains($action);

        // Build evaluation context
        $context = [
            'evaluation_id' => uniqid('eval_', true),
            'output_type' => $actionResult->getOutputType(),
            'priority' => $action->getPriority(),
            'action_type' => $action->getType(),
            'required_domains' => $requiredDomains,
            'execution_metrics' => $actionResult->getExecutionMetrics(),
            'special_requirements' => $this->extractSpecialRequirements($action)
        ];

        if ($this->debug) {
            error_log(sprintf(
                "ActorCriticCoordinator: Built evaluation context - Output: %s, Priority: %s, Domains: %s",
                $context['output_type'],
                $context['priority'],
                implode(', ', $context['required_domains'])
            ));
        }

        return $context;
    }

    /**
     * Extract required domains from action
     *
     * Determines which generic capability domains are required for evaluating
     * this action based on action type and context.
     *
     * @param Action $action The action
     * @return array Array of required domain names
     */
    private function extractRequiredDomains(Action $action): array
    {
        $actionType = $action->getType();
        $context = $action->getContext();
        $environmentalData = $context->getEnvironmentalData();

        // Check if domains are explicitly specified in environmental data
        if (isset($environmentalData['required_domains']) && is_array($environmentalData['required_domains'])) {
            return $environmentalData['required_domains'];
        }

        // Infer domains from action type (generic capability domains)
        $domainMap = [
            'search' => ['semantic', 'quality'],
            'query' => ['semantic', 'analytics'],
            'analysis' => ['analytics', 'reasoning'],
            'recommendation' => ['semantic', 'reasoning'],
            'validation' => ['quality', 'security'],
            'optimization' => ['performance', 'quality'],
            'security_audit' => ['security', 'quality'],
            'data_processing' => ['analytics', 'performance']
        ];

        // Return mapped domains or default to semantic
        return $domainMap[$actionType] ?? ['semantic'];
    }

    /**
     * Extract special requirements from action
     *
     * Extracts any special requirements or constraints from the action context.
     *
     * @param Action $action The action
     * @return array Special requirements
     */
    private function extractSpecialRequirements(Action $action): array
    {
        $context = $action->getContext();
        $environmentalData = $context->getEnvironmentalData();

        $requirements = [];

        // Check for special requirements in environmental data
        if (isset($environmentalData['special_requirements'])) {
            $requirements = $environmentalData['special_requirements'];
        }

        // Add priority-based requirements
        if ($action->getPriority() === 'critical') {
            $requirements[] = 'high_accuracy_required';
        }

        return $requirements;
    }
}

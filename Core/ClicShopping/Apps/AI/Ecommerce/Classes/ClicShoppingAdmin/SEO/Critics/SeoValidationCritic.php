<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Critics;

use ClicShopping\AI\InterfacesAI\CriticAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Evaluation;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Prediction;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\EvaluationCriteria;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents\SeoCodeValidationAgent;

/**
 * Class SeoValidationCritic
 *
 * An evaluation agent that utilizes an underlying SeoCodeValidationAgent to 
 * analyze content changes against detailed SEO criteria, technical boundaries, 
 * and operational constraints.
 *
 * Implements a fault-tolerant strategy to prevent execution gaps within the 
 * orchestration layout by returning safe low scores during runtime exceptions.
 */
class SeoValidationCritic implements CriticAgentInterface
{
  /** @var string Unique identifier for this critic instance. */
  private string $criticId;

  /** @var bool Flag to toggle verbose debugging features. */
  private bool $debug;

  /** @var SeoCodeValidationAgent The underlying validation micro-agent. */
  private SeoCodeValidationAgent $validator;

  /** @var SecurityLogger Instance utilized to record system tracking and safety exceptions. */
  private SecurityLogger $securityLogger;

  /** @var array Audit trail storing structured historical output data of performed evaluations. */
  private array $evaluationHistory = [];

  /**
   * SeoValidationCritic constructor.
   *
   * @param bool $debug Enables debugging mode if set to true.
   * @param CriticRegistry|null $registry Optional registry instance to auto-enroll this critic.
   * @param SeoCodeValidationAgent|null $validator Optional pre-configured validator instance.
   */
  public function __construct(
    bool $debug = false,
    ?CriticRegistry $registry = null,
    ?SeoCodeValidationAgent $validator = null
  )
  {
    $this->criticId       = 'seo_validation_critic_' . uniqid();
    $this->debug          = $debug;
    $this->validator      = $validator ?? new SeoCodeValidationAgent();
    $this->securityLogger = new SecurityLogger();

    if ($registry !== null) {
      $registry->registerCritic($this);
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // CriticAgentInterface Implementation
  // ─────────────────────────────────────────────────────────────────────────

  /**
   * Performs a pre-flight risk evaluation to guess the likelihood of a successful run 
   * based purely on the inputs available in the initial Action payload.
   *
   * @param Action $action The action payload containing target parameters.
   * @return Prediction The calculated readiness assurance profile and associated risks.
   */
  public function predictOutcome(Action $action): Prediction
  {
    $params = $action->getParameters();
    $hasSerp = isset($params['serp_report']);
    $hasContent = isset($params['current_content']);

    $confidence = ($hasSerp && $hasContent) ? 0.7 : 0.4;
    $risks = [];

    if (!$hasSerp) {
      $risks[] = ['type' => 'missing_serp', 'description' => 'SERP data missing', 'probability' => 0.6];
    }

    if (!$hasContent) {
      $risks[] = ['type' => 'missing_content', 'description' => 'Current content missing', 'probability' => 0.6];
    }

    return new Prediction(
      $action->getActionId(),
      $this->criticId,
      ['expected_quality' => $confidence],
      $confidence,
      $risks,
      ['success' => $confidence],
      ['missing_data' => 'Provide serp_report and current_content']
    );
  }

  /**
   * Retrieves the standard weighted baseline matrix used to grade SEO validations.
   *
   * @return array<string, EvaluationCriteria> Keyed array defining the targeted SEO constraints.
   */
  public function getEvaluationCriteria(): array
  {
    return [
      'seo_proposal' => new EvaluationCriteria(
        'seo_proposal',
        0.85,
        'seo',
        ['accuracy' => 0.4, 'completeness' => 0.25, 'efficiency' => 0.2, 'clarity' => 0.15],
        ['min_quality_score' => 0.7, 'require_meta' => true],
        ['accuracy' => 0.6, 'completeness' => 0.6, 'efficiency' => 0.5, 'clarity' => 0.6]
      )
    ];
  }

  /**
   * Transforms raw Evaluation output into an actionable, categorized feedback object.
   *
   * @param ActionResult $result The production result containing metrics to interpret.
   * @return Feedback Detailed breakdown of critical vulnerabilities and strong aspects.
   */
  public function provideFeedback(ActionResult $result): Feedback
  {
    $evaluation = $this->evaluateAction($result);

    $scores = [
      'accuracy' => $evaluation->getAccuracyScore(),
      'completeness' => $evaluation->getCompletenessScore(),
      'efficiency' => $evaluation->getEfficiencyScore(),
      'clarity' => $evaluation->getClarityScore(),
    ];

    $lowest = array_keys($scores, min($scores))[0];

    $categorized = [
      'correctness' => $lowest === 'accuracy' ? [$evaluation->getFeedback()] : [],
      'completeness' => $lowest === 'completeness' ? [$evaluation->getFeedback()] : [],
      'efficiency' => $lowest === 'efficiency' ? [$evaluation->getFeedback()] : [],
      'best_practice' => $lowest === 'clarity' ? [$evaluation->getFeedback()] : [],
    ];

    return new Feedback(
      $result->getProducerAgentId(),
      $result->getResultId(),
      $evaluation->getOverallScore(),
      $categorized,
      $evaluation->getStrengths(),
      $evaluation->getImprovements()
    );
  }

  /**
   * Evaluates the absolute readiness of the action's final payload by sub-allocating 
   * validation targets to the internal validation agent.
   *
   * @note Fault-tolerant: This critic must NEVER throw an unhandled exception. 
   * If any inner execution step fails, it recovers gracefully with a minimal baseline 
   * score to guarantee that the agent pipeline can maintain its required consensus rules 
   * (§V "marge critic SEO = zéro") and safe structural fallbacks.
   *
   * @param ActionResult $result The executed result content to scan.
   * @return Evaluation Complete scoring matrices along with strengths/improvements notes.
   */
  public function evaluateAction(ActionResult $result): Evaluation
  {
    $outputType = $result->getOutputType();
    $output = $result->getOutput();
    $context = $result->getExecutionContext();
    $entityType = $context->getSystemState()['entity_type'] ?? 'category';

    $this->securityLogger->logSecurityEvent(
      'SeoValidationCritic evaluating output',
      'info',
      ['critic_id' => $this->criticId, 'output_type' => $outputType]
    );

    try {
      $changes = $this->extractChanges($output);

      $validationOutput = [
        'approved' => false,
        'quality_score' => 0,
        'issues' => ['Empty output'],
        'suggestions' => ['Generate meta title and meta description'],
        'is_spam' => false,
        'lengths' => ['passed' => false],
      ];

      if (!empty($changes)) {
        $validationAction = new Action(
          'seo_code_validation',
          [
            'entity_type' => $entityType,
            'changes' => $changes,
          ],
          $context,
          'medium',
          30
        );

        $validationOutput = $this->validator->executeAction($validationAction)->getOutput();
      }

      $scores = $this->calculateScores($changes, $validationOutput);
      $feedback = $this->buildFeedbackSummary($validationOutput, $scores);
      $strengths = $this->buildStrengths($validationOutput, $scores);
      $improvements = $this->buildImprovements($validationOutput, $scores);
    } catch (\Throwable $e) {
      $this->securityLogger->logSecurityEvent(
        'SeoValidationCritic evaluation error (fault-tolerant low score)',
        'warning',
        ['critic_id' => $this->criticId, 'error' => $e->getMessage()]
      );
      $scores = ['accuracy' => 0.2, 'completeness' => 0.2, 'efficiency' => 0.2, 'clarity' => 0.2];
      $feedback = 'SEO code validation could not complete on this proposal.';
      $strengths = [];
      $improvements = ['Regenerate the SEO content: the proposal could not be validated.'];
    }

    $evaluation = new Evaluation(
      $this->criticId,
      $result->getResultId(),
      $scores,
      $feedback,
      $strengths,
      $improvements
    );

    $this->evaluationHistory[] = [
      'evaluation_id' => $evaluation->getEvaluationId(),
      'output_type' => $outputType,
      'overall_score' => $evaluation->getOverallScore(),
      'evaluated_at' => date('Y-m-d H:i:s'),
    ];

    return $evaluation;
  }

  /**
   * Returns the identification string assigned to this critic.
   *
   * @return string Unique critic UUID.
   */
  public function getCriticId(): string
  {
    return $this->criticId;
  }

  /**
   * Safely unpacks the mixed payload result into a normalized schema structure 
   * required by the sub-validation processes.
   *
   * @param mixed $output The raw unparsed content object.
   * @return array Extracted associative map of structural modifications.
   */
  private function extractChanges(mixed $output): array
  {
    if (!is_array($output)) {
      return [];
    }

    return [
      'meta_title'                => $output['meta_title']                ?? '',
      'meta_description'          => $output['meta_description']          ?? '',
      'meta_keywords'             => $output['meta_keywords']             ?? '',
      'description'               => $output['description']               ?? '',
      'category_body_description' => $output['category_body_description'] ?? '',  // T4.2
      'h2'                        => $output['h2']                        ?? [],
      'faq'                       => $output['faq']                       ?? [],
      'schema_org_json'           => $output['schema_org_json']           ?? '',  // T3.1
    ];
  }

  /**
   * Compiles validation metric signals into a final criteria performance breakdown.
   *
   * @param array $changes Extracted dataset modifications map.
   * @param array $validation Structural response array from the validation runner.
   * @return array<string, float> Keyed scoring metrics mapped between 0.0 and 1.0.
   */
  private function calculateScores(array $changes, array $validation): array
  {
    $qualityScore = (float)($validation['quality_score'] ?? 0);
    $accuracy = max(0.0, min(1.0, $qualityScore / 100));

    $requiredFields = ['meta_title', 'meta_description', 'meta_keywords'];
    $present = 0;
    foreach ($requiredFields as $field) {
      if (!empty($changes[$field])) {
        $present++;
      }
    }
    $completeness = $present / count($requiredFields);

    $clarity = !($validation['is_spam'] ?? false) ? 1.0 : 0.3;
    $issuesCount = count($validation['issues'] ?? []);
    if ($issuesCount > 0) {
      $clarity = max(0.2, $clarity - (0.05 * $issuesCount));
    }

    $lengthsPassed = (bool)($validation['lengths']['passed'] ?? false);
    $efficiency = $lengthsPassed ? 1.0 : 0.6;

    return [
      'accuracy' => $accuracy,
      'completeness' => $completeness,
      'efficiency' => $efficiency,
      'clarity' => $clarity,
    ];
  }

  /**
   * Aggregates runtime flags, exceptions, and scores into a human-readable summary block.
   *
   * @param array $validation Structural response array from the validation runner.
   * @param array $scores Keyed assessment vectors mapping metrics.
   * @return string Normalized text log overview.
   */
  private function buildFeedbackSummary(array $validation, array $scores): string
  {
    $parts = [];
    $issues = $validation['issues'] ?? [];
    $suggestions = $validation['suggestions'] ?? [];

    if (!empty($issues)) {
      $parts[] = 'Issues: ' . implode('; ', $issues);
    }

    if (!empty($suggestions)) {
      $parts[] = 'Suggestions: ' . implode('; ', $suggestions);
    }

    if (empty($parts)) {
      $parts[] = 'SEO proposal meets validation requirements.';
    }

    $parts[] = 'Scores: ' . sprintf(
      'accuracy %.2f, completeness %.2f, clarity %.2f',
      $scores['accuracy'],
      $scores['completeness'],
      $scores['clarity']
    );

    return implode(' | ', $parts);
  }

  /**
   * Evaluates validation flags and quality profiles to report strong content factors.
   *
   * @param array $validation Structural response array from the validation runner.
   * @param array $scores Keyed assessment vectors mapping metrics.
   * @return array<int, string> List of verified structural strengths.
   */
  private function buildStrengths(array $validation, array $scores): array
  {
    $strengths = [];

    if (($validation['approved'] ?? false) === true) {
      $strengths[] = 'Validation approved.';
    }

    if (($validation['quality_score'] ?? 0) >= 80) {
      $strengths[] = 'High quality score.';
    }

    if (($validation['lengths']['passed'] ?? false) === true) {
      $strengths[] = 'Meta length constraints respected.';
    }

    if ($scores['clarity'] >= 0.9) {
      $strengths[] = 'No spam indicators detected.';
    }

    return $strengths;
  }

  /**
   * Analyzes issues, omissions, and tips to construct a recovery guideline payload.
   *
   * @param array $validation Structural response array from the validation runner.
   * @param array $scores Keyed assessment vectors mapping metrics.
   * @return array<int, string> Extracted error messages targeting corrections.
   */
  private function buildImprovements(array $validation, array $scores): array
  {
    $improvements = [];

    foreach (($validation['suggestions'] ?? []) as $suggestion) {
      $improvements[] = $suggestion;
    }

    if ($scores['completeness'] < 1.0) {
      $improvements[] = 'Complete all required meta fields.';
    }

    return array_values(array_unique(array_filter($improvements)));
  }
}

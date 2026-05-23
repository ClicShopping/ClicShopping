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
 * SeoValidationCritic
 *
 * Critic that evaluates SEO proposals using SeoCodeValidationAgent.
 */
class SeoValidationCritic implements CriticAgentInterface
{
  private string $criticId;
  private bool $debug;
  private SeoCodeValidationAgent $validator;
  private SecurityLogger $securityLogger;
  private array $evaluationHistory = [];

  public function __construct(
    bool $debug = false,
    ?CriticRegistry $registry = null,
    ?SeoCodeValidationAgent $validator = null
  )
  {
    $this->criticId = 'seo_validation_critic_' . uniqid();
    $this->debug = $debug;
    $this->validator = $validator ?? new SeoCodeValidationAgent();
    $this->securityLogger = new SecurityLogger();

    if ($registry !== null) {
      $registry->registerCritic($this);
    }
  }

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

  public function getCriticId(): string
  {
    return $this->criticId;
  }
}

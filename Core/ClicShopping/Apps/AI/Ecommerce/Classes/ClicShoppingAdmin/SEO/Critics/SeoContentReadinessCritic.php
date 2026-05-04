<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
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

/**
 * SeoContentReadinessCritic
 *
 * Lightweight second critic — no LLM calls.
 *
 * Responsibility: verify that the SEO proposal is structurally complete
 * before the pipeline applies changes to the CMS.
 *
 * Checks evaluated (rule-based only):
 *   - Required fields present and non-empty (meta_title, meta_description, meta_keywords, description)
 *   - Description has a minimum word count (≥ 50 words)
 *   - H2 headings array is populated (≥ 1 entry with a non-empty 'text')
 *   - FAQ array is populated (≥ 1 entry with non-empty 'q' and 'a')
 *   - schema_org_json present and syntactically valid JSON
 *   - No obvious placeholder strings in critical fields (e.g. "[weight not specified]", "TODO", "PLACEHOLDER")
 *
 * This critic is intentionally complementary to SeoValidationCritic, which focuses
 * on LLM-driven quality scoring, spam detection, and length constraints.
 * Together they satisfy the ActorCriticCoordinator's requirement of ≥ 2 critics
 * covering the "seo_proposal" output type.
 */
class SeoContentReadinessCritic implements CriticAgentInterface
{
  private string $criticId;
  private bool $debug;
  private SecurityLogger $securityLogger;
  private array $evaluationHistory = [];

  /**
   * Placeholder patterns that should never appear in published content.
   */
  private const PLACEHOLDER_PATTERNS = [
    '/\[weight not specified\]/i',
    '/\[.*?not specified.*?\]/i',
    '/\bTODO\b/',
    '/\bPLACEHOLDER\b/i',
    '/\{\{.*?\}\}/',   // un-substituted template variables
    '/\{[a-z_]+\}/',   // single-brace template variables
  ];

  public function __construct(
    bool $debug = false,
    ?CriticRegistry $registry = null
  )
  {
    $this->criticId    = 'seo_content_readiness_critic_' . uniqid();
    $this->debug       = $debug;
    $this->securityLogger = new SecurityLogger();

    if ($registry !== null) {
      $registry->registerCritic($this);
    }
  }

  // ──────────────────────────────────────────────────────────────────────────
  // CriticAgentInterface
  // ──────────────────────────────────────────────────────────────────────────

  public function predictOutcome(Action $action): Prediction
  {
    $params = $action->getParameters();

    // Basic pre-flight: do we have the data we need to evaluate?
    $hasOutput = isset($params['meta_title']) || isset($params['meta_description']);
    $confidence = $hasOutput ? 0.8 : 0.3;

    $risks = [];
    if (!$hasOutput) {
      $risks[] = [
        'type'        => 'missing_proposal_fields',
        'description' => 'No meta fields found in action parameters.',
        'probability' => 0.9,
      ];
    }

    return new Prediction(
      $action->getActionId(),
      $this->criticId,
      ['expected_readiness' => $confidence],
      $confidence,
      $risks,
      ['success' => $confidence],
      ['tip' => 'Ensure all SEO fields are populated before submission.']
    );
  }

  public function getEvaluationCriteria(): array
  {
    return [
      'seo_proposal' => new EvaluationCriteria(
        'seo_proposal',
        0.80,
        'seo',
        ['accuracy' => 0.3, 'completeness' => 0.4, 'efficiency' => 0.1, 'clarity' => 0.2],
        ['require_description' => true, 'require_h2' => true],
        ['accuracy' => 0.5, 'completeness' => 0.5, 'efficiency' => 0.4, 'clarity' => 0.5]
      ),
    ];
  }

  public function provideFeedback(ActionResult $result): Feedback
  {
    $evaluation = $this->evaluateAction($result);

    $scores   = [
      'accuracy'     => $evaluation->getAccuracyScore(),
      'completeness' => $evaluation->getCompletenessScore(),
      'efficiency'   => $evaluation->getEfficiencyScore(),
      'clarity'      => $evaluation->getClarityScore(),
    ];
    $lowest = array_keys($scores, min($scores))[0];

    $categorized = [
      'correctness'  => $lowest === 'accuracy'     ? [$evaluation->getFeedback()] : [],
      'completeness' => $lowest === 'completeness' ? [$evaluation->getFeedback()] : [],
      'efficiency'   => $lowest === 'efficiency'   ? [$evaluation->getFeedback()] : [],
      'best_practice'=> $lowest === 'clarity'      ? [$evaluation->getFeedback()] : [],
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
    $output  = $result->getOutput();
    $outputType = $result->getOutputType();

    $this->securityLogger->logSecurityEvent(
      'SeoContentReadinessCritic evaluating output',
      'info',
      ['critic_id' => $this->criticId, 'output_type' => $outputType]
    );

    $checks   = $this->runChecks($output);
    $scores   = $this->buildScores($checks);
    $feedback = $this->buildFeedback($checks);
    $strengths    = $this->buildStrengths($checks);
    $improvements = $this->buildImprovements($checks);

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
      'output_type'   => $outputType,
      'overall_score' => $evaluation->getOverallScore(),
      'evaluated_at'  => date('Y-m-d H:i:s'),
    ];

    return $evaluation;
  }

  public function getCriticId(): string
  {
    return $this->criticId;
  }

  // ──────────────────────────────────────────────────────────────────────────
  // Internal helpers
  // ──────────────────────────────────────────────────────────────────────────

  /**
   * Run all rule-based checks on the proposal output.
   * Returns a keyed array of check results: ['passed' => bool, 'message' => string].
   */
  private function runChecks(mixed $output): array
  {
    if (!is_array($output)) {
      return [
        'valid_array' => ['passed' => false, 'message' => 'Output is not an array.'],
      ];
    }

    $checks = [];

    // ── Required field presence ───────────────────────────────────────────
    foreach (['meta_title', 'meta_description', 'meta_keywords', 'description'] as $field) {
      $value = trim((string)($output[$field] ?? ''));
      $checks["field_{$field}"] = [
        'passed'  => $value !== '',
        'message' => $value !== ''
          ? "Field '{$field}' is present."
          : "Required field '{$field}' is empty or missing.",
      ];
    }

    // ── Description minimum length ────────────────────────────────────────
    $descWords = str_word_count(strip_tags((string)($output['description'] ?? '')));
    $checks['description_length'] = [
      'passed'  => $descWords >= 50,
      'message' => $descWords >= 50
        ? "Description has {$descWords} words (≥ 50)."
        : "Description too short: {$descWords} words (minimum 50).",
    ];

    // ── H2 headings ───────────────────────────────────────────────────────
    $h2 = $output['h2'] ?? [];
    $h2Valid = is_array($h2) && count($h2) > 0;
    if ($h2Valid) {
      // Make sure at least one entry has a non-empty 'text' key
      $h2Valid = array_reduce($h2, function (bool $carry, mixed $item): bool {
        return $carry || (is_array($item) && trim((string)($item['text'] ?? '')) !== '');
      }, false);
    }
    $checks['h2_populated'] = [
      'passed'  => $h2Valid,
      'message' => $h2Valid
        ? 'H2 headings array is populated with valid entries.'
        : 'H2 headings array is empty or contains no valid text entries.',
    ];

    // ── FAQ ───────────────────────────────────────────────────────────────
    $faq = $output['faq'] ?? [];
    $faqValid = is_array($faq) && count($faq) > 0;
    if ($faqValid) {
      $faqValid = array_reduce($faq, function (bool $carry, mixed $item): bool {
        return $carry || (
          is_array($item)
          && trim((string)($item['q'] ?? '')) !== ''
          && trim((string)($item['a'] ?? '')) !== ''
        );
      }, false);
    }
    $checks['faq_populated'] = [
      'passed'  => $faqValid,
      'message' => $faqValid
        ? 'FAQ array is populated with valid entries.'
        : 'FAQ array is empty or entries lack question/answer.',
    ];

    // ── schema_org_json ───────────────────────────────────────────────────
    $schemaJson = trim((string)($output['schema_org_json'] ?? ''));
    $schemaPresent = $schemaJson !== '';
    $schemaValid   = false;
    if ($schemaPresent) {
      json_decode($schemaJson);
      $schemaValid = json_last_error() === JSON_ERROR_NONE;
    }
    $checks['schema_present'] = [
      'passed'  => $schemaPresent,
      'message' => $schemaPresent
        ? 'schema_org_json field is present.'
        : 'schema_org_json is missing.',
    ];
    $checks['schema_valid_json'] = [
      'passed'  => !$schemaPresent || $schemaValid, // non-blocking if absent (other critic handles it)
      'message' => $schemaValid || !$schemaPresent
        ? 'schema_org_json is valid JSON (or absent).'
        : 'schema_org_json contains invalid JSON: ' . json_last_error_msg(),
    ];

    // ── Placeholder detection ─────────────────────────────────────────────
    $placeholdersFound = [];
    $criticalFields = [
      'meta_title'       => (string)($output['meta_title']       ?? ''),
      'meta_description' => (string)($output['meta_description'] ?? ''),
      'description'      => strip_tags((string)($output['description'] ?? '')),
    ];
    foreach ($criticalFields as $field => $content) {
      foreach (self::PLACEHOLDER_PATTERNS as $pattern) {
        if (preg_match($pattern, $content)) {
          $placeholdersFound[] = "Placeholder detected in '{$field}'.";
          break; // one report per field is enough
        }
      }
    }
    $checks['no_placeholders'] = [
      'passed'  => empty($placeholdersFound),
      'message' => empty($placeholdersFound)
        ? 'No placeholder strings detected in critical fields.'
        : implode(' ', $placeholdersFound),
    ];

    return $checks;
  }

  /**
   * Build score array from check results.
   * Weights:
   *   accuracy     = required fields all present + no placeholders
   *   completeness = h2 + faq + schema present
   *   efficiency   = schema valid JSON (syntax)
   *   clarity      = description length adequate
   */
  private function buildScores(array $checks): array
  {
    $fieldsOk = (int)($checks['field_meta_title']['passed']       ?? false)
              + (int)($checks['field_meta_description']['passed'] ?? false)
              + (int)($checks['field_meta_keywords']['passed']    ?? false)
              + (int)($checks['field_description']['passed']      ?? false);
    $noPlaceholders = (bool)($checks['no_placeholders']['passed'] ?? true);

    $accuracy = ($fieldsOk / 4) * ($noPlaceholders ? 1.0 : 0.5);

    $completeness = (
        (int)(bool)($checks['h2_populated']['passed']   ?? false)
      + (int)(bool)($checks['faq_populated']['passed']  ?? false)
      + (int)(bool)($checks['schema_present']['passed'] ?? false)
    ) / 3;

    $efficiency = (bool)($checks['schema_valid_json']['passed'] ?? true) ? 1.0 : 0.4;

    $clarity = (bool)($checks['description_length']['passed'] ?? false) ? 1.0 : 0.5;

    return [
      'accuracy'     => round($accuracy, 3),
      'completeness' => round($completeness, 3),
      'efficiency'   => round($efficiency, 3),
      'clarity'      => round($clarity, 3),
    ];
  }

  private function buildFeedback(array $checks): string
  {
    $failed = array_filter($checks, fn(array $c) => !($c['passed'] ?? true));

    if (empty($failed)) {
      return 'Content readiness check passed — all structural requirements met.';
    }

    $messages = array_map(fn(array $c) => $c['message'], $failed);
    return 'Readiness issues: ' . implode(' | ', $messages);
  }

  private function buildStrengths(array $checks): array
  {
    $strengths = [];
    if (array_filter($checks, fn(array $c) => $c['passed'] ?? false) === $checks) {
      $strengths[] = 'All content readiness checks passed.';
    }
    if ($checks['h2_populated']['passed']  ?? false) $strengths[] = 'H2 headings are populated.';
    if ($checks['faq_populated']['passed'] ?? false) $strengths[] = 'FAQ section is populated.';
    if ($checks['schema_present']['passed'] ?? false) $strengths[] = 'schema_org_json is present.';
    if ($checks['no_placeholders']['passed'] ?? true) $strengths[] = 'No placeholder strings detected.';
    return array_values(array_unique($strengths));
  }

  private function buildImprovements(array $checks): array
  {
    $improvements = [];
    foreach ($checks as $check) {
      if (!($check['passed'] ?? true)) {
        $improvements[] = $check['message'];
      }
    }
    return array_values(array_unique(array_filter($improvements)));
  }
}

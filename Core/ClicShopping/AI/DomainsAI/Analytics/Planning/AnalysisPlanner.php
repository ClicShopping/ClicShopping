<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\DomainsAI\Analytics\Planning;

use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\DomainsAI\Analytics\Validator\MetricExpressionFilter;
use ClicShopping\AI\Infrastructure\Prompt\PromptPlaceholders;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

/**
 * AnalysisPlanner
 *
 * Produces the analysis plan the generator works from. The model names metrics and the
 * window it read in the question; everything the generator must not be free to reinterpret -
 * the grain, the type, the comparison window - is filled in here.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Planning
 */
class AnalysisPlanner
{
  private const DAILY_SHAPE_MAX_DAYS = 31;
  private const COMPARE_CONVENTIONS = [
    PeriodResolver::COMPARE_PREVIOUS_YEAR => 'text_analysis_plan_compare_calendar_year',
    PeriodResolver::COMPARE_PREVIOUS_YEAR_COMPARABLE_DAYS => 'text_analysis_plan_compare_comparable_days',
  ];

  private AnalysisPlanValidator $validator;
  private int $languageId;
  /** @var array<string, array<int, string>> Companion columns per metric, as the catalogue declares them */
  private array $companions = [];
  /** @var array<string, string> Declared formula per metric, as the catalogue and its terms give it */
  private array $formulas = [];
  /** @var array<int, string> Metrics that are a ratio of sums */
  private array $ratios = [];
  private bool $definitionsLoaded = false;

  /**
   * @param array<string, array{grain: string, type: string, definition: string, companions?: array<int, string>}> $catalog Domain metric catalogue
   * @param int $languageId Language ID, needed to resolve the prompt's dynamic placeholders
   * @param array<int, string> $orderSideDimensions Dimensions the domain declares at order grain
   * @param array<string, string> $terms Named row-level expressions the catalogue formulas read
   */
  public function __construct(array $catalog, int $languageId, array $orderSideDimensions = [], array $terms = [])
  {
    $this->validator = new AnalysisPlanValidator($catalog, $orderSideDimensions);
    $this->languageId = $languageId;

    foreach ($catalog as $name => $metric) {
      if (!empty($metric['companions'])) {
        $this->companions[$name] = $metric['companions'];
      }

      $formula = MetricExpressionFilter::describe($name, $catalog, $terms);

      if ($formula !== '') {
        $this->formulas[$name] = $formula;
      }

      if (($metric['aggregation'] ?? '') === MetricAggregation::RATIO_OF_SUMS) {
        $this->ratios[] = $name;
      }
    }
  }

  /**
   * Ask the model for a plan, then validate it.
   *
   * @param string $englishQuestion Question already normalised to English
   * @param string $widerRequest The whole request this question is one part of, already English.
   *                             Empty when the question stands alone.
   * @return array{plan: array|null, unsatisfiable: array, errors: array<int, string>, no_metric_proposed: bool, act: bool, raw: string}
   */
  public function plan(string $englishQuestion, string $widerRequest = ''): array
  {
    $skeleton = $this->resolvePromptSkeleton();

    if (PromptPlaceholders::hasUnresolved($skeleton)) {
      return [
        'plan' => null,
        'unsatisfiable' => [],
        'errors' => ['the analysis plan prompt still carries an unresolved placeholder'],
        'no_metric_proposed' => false,
        'act' => false,
        'raw' => '',
      ];
    }

    $prompt = $this->assemblePrompt($skeleton, $englishQuestion, $widerRequest);

    $raw = (string)Gpt::getGptResponse($prompt, $this->responseMaxTokens($englishQuestion), 0.0);

    return $this->parsePlan($raw) + ['raw' => $raw];
  }

  /**
   * Fetch the prompt skeleton and resolve its registry/platform tokens (e.g. {{metric_catalog}}).
   * getDef() runs with NO vars here: it cannot pre-eat a token it does not know as a bare
   * word before the resolution chokepoint below ever gets to see it.
   *
   * @return string Skeleton with registry tokens resolved; {{question}}/{{examples}} still open
   */
  protected function resolvePromptSkeleton(): string
  {
    $this->loadDefinitions();

    $prompt = $this->getDef('text_analysis_plan_prompt');

    return PromptPlaceholders::resolve($prompt, (string)CLICSHOPPING::getConfig('db_table_prefix'), $this->languageId);
  }

  /**
   * Inject the question and the examples last, via a targeted replace - never through
   * getDef(), which would re-run its destructive interpolation over anything left.
   *
   * @param string $skeleton Resolved skeleton, from resolvePromptSkeleton()
   * @param string $englishQuestion Question already normalised to English
   * @param string $widerRequest The whole request this question is a part of; empty when it stands alone
   * @return string Prompt ready to be sent to the model
   */
  protected function assemblePrompt(string $skeleton, string $englishQuestion, string $widerRequest = ''): string
  {
    return str_replace(
      ['{{today}}', '{{wider_request}}', '{{question}}', '{{examples}}'],
      [date('Y-m-d'), trim($widerRequest), $englishQuestion, $this->examples()],
      $skeleton
    );
  }

  /**
   * Parse and validate a raw model answer. Separated from plan() so the whole contract is
   * testable without an LLM call.
   *
   * @param string $raw Raw model answer
   * @return array{plan: array|null, unsatisfiable: array, errors: array<int, string>, no_metric_proposed: bool, act: bool}
   */
  public function parsePlan(string $raw): array
  {
    $decoded = json_decode(HTMLOverrideCommon::extractJsonFromMarkdown($raw), true);

    if (!is_array($decoded)) {
      return [
        'plan' => null,
        'unsatisfiable' => [],
        'errors' => ['the plan answer is not JSON: ' . json_last_error_msg()],
        'no_metric_proposed' => false,
        'act' => false,
      ];
    }

    // Read beside the plan, not inside it: a question asking what to do may aggregate no metric.
    return $this->validator->validate($decoded) + ['act' => ($decoded['act'] ?? false) === true];
  }

  /**
   * Render the plan as the directive block injected into the generation prompt.
   *
   * @param array|null $plan Validated plan, or null for a refusal
   * @return string Directive block, empty when there is no plan
   */
  public function describeForPrompt(?array $plan): string
  {
    if ($plan === null || $plan === []) {
      return '';
    }

    $this->loadDefinitions();

    $metrics = [];
    $hasCompanions = false;

    foreach ($plan['metrics'] as $metric) {
      // Listed by the binding plan, or the generator drops them as an added metric.
      $companions = $this->companions[$metric['name']] ?? [];
      $hasCompanions = $hasCompanions || $companions !== [];

      $metrics[] = $this->getDef('text_analysis_plan_metric_line', [
        'name' => $metric['name'],
        'grain' => $metric['grain'],
        'type' => $metric['type'],
        'variation' => $this->getDef(
          MetricType::variesInPoints($metric['type'])
            ? 'text_analysis_plan_variation_points'
            : 'text_analysis_plan_variation_percent'
        ),
      ]) . ($companions === [] ? '' : ' | ' . $this->getDef('text_analysis_plan_metric_companions', [
        'companions' => implode(', ', $companions),
      ])) . (isset($this->formulas[$metric['name']]) ? ' | ' . $this->getDef('text_analysis_plan_metric_formula', [
        'formula' => $this->formulas[$metric['name']],
      ]) : '') . (in_array($metric['name'], $this->ratios, true) ? ' | ' . $this->getDef('text_analysis_plan_metric_ratio') : '');
    }

    $windows = isset($plan['periods']['current']['from'], $plan['periods']['current']['to'])
      ? $this->getDef('text_analysis_plan_window_current', [
          'from' => $plan['periods']['current']['from'],
          'to' => $plan['periods']['current']['to'],
        ])
      : (($plan['periods']['all_time'] ?? false) === true
        ? $this->getDef('text_analysis_plan_window_all_time', ['to' => (string)($plan['periods']['current']['to'] ?? '')])
        : '');

    if (isset($plan['periods']['previous'])) {
      $windows .= "\n" . $this->getDef('text_analysis_plan_window_previous', [
        'from' => $plan['periods']['previous']['from'],
        'to' => $plan['periods']['previous']['to'],
      ]);

      // Calendar YoY and comparable-days YoY are two metrics, never one: the block says which.
      $convention = self::COMPARE_CONVENTIONS[$plan['periods']['compare'] ?? ''] ?? null;

      if ($convention !== null) {
        $windows .= "\n" . $this->getDef($convention);
      }
    }

    if (($plan['periods']['time_grain'] ?? 'window') === 'day') {
      $days = self::elapsedDays($plan['periods']['current']);
      $shape = $days <= self::DAILY_SHAPE_MAX_DAYS ? 'daily' : 'monthly';

      $windows .= "\n" . $this->getDef('text_analysis_plan_time_grain', ['days' => (string)$days])
        . "\n" . $this->getDef('text_analysis_plan_time_grain_shape_' . $shape, ['days' => (string)$days])
        . ($hasCompanions ? "\n" . $this->getDef('text_analysis_plan_time_grain_companions') : '')
        . (!empty($plan['dimensions'])
          ? "\n" . $this->getDef('text_analysis_plan_time_grain_split', ['dimensions' => implode(', ', $plan['dimensions'])])
          : '');
    }

    $windows = ltrim($windows, "\n");

    $block = $this->getDef('text_analysis_plan_header') . "\n"
      . implode("\n", $metrics)
      . ($windows === '' ? '' : "\n" . $windows);

    $rankingLines = [];

    foreach ($plan['rankings'] ?? [] as $ranking) {
      $rankingLines[] = $this->getDef('text_analysis_plan_ranking_line', [
        'metric' => $ranking['metric'],
        'direction' => $ranking['direction'],
        'n' => (string)$ranking['n'],
        'partition' => $ranking['partition_by'] ?? $this->getDef('text_analysis_plan_ranking_no_partition'),
      ]);
    }

    if ($rankingLines !== []) {
      $block .= "\n" . implode("\n", $rankingLines);
    }

    if (!empty($plan['dimensions'])) {
      $block .= "\n" . $this->getDef('text_analysis_plan_dimensions_line', [
        'dimensions' => implode(', ', $plan['dimensions']),
      ]);
    }

    // A filter the plan elected is a restriction the generator must honour; unrendered, the plan
    // carried it nowhere. It says WHICH criterion applies, never how the stored value is spelled.
    $filters = [];

    foreach ($plan['filters'] ?? [] as $column => $value) {
      if (is_scalar($value) && (string)$value !== '') {
        $filters[] = $column . ' = ' . $value;
      }
    }

    if ($filters !== []) {
      $block .= "\n" . $this->getDef('text_analysis_plan_filters_line', [
        'filters' => implode(', ', $filters),
      ]);
    }

    // Metrics dropped for a grain conflict were requested by the question but cannot be computed
    // at this breakdown: name them so the generator OMITS them instead of fanning them out.
    $excluded = [];

    foreach ($plan['unsatisfiable'] ?? [] as $item) {
      if (($item['kind'] ?? '') === 'grain_conflict' && ($item['label'] ?? '') !== '') {
        $excluded[] = $item['label'];
      }
    }

    if ($excluded !== []) {
      $block .= "\n" . $this->getDef('text_analysis_plan_excluded_line', [
        'metrics' => implode(', ', $excluded),
      ]);
    }

    return $block;
  }

  /**
   * Days the window spans, both bounds included - day 1 is its own first day.
   *
   * @param array $current Current window `{from, to}`, both Y-m-d
   * @return int Elapsed days, at least 1
   */
  private static function elapsedDays(array $current): int
  {
    try {
      $from = new \DateTimeImmutable((string)($current['from'] ?? ''));
      $to = new \DateTimeImmutable((string)($current['to'] ?? ''));
    } catch (\Throwable) {
      return 1;
    }

    return max(1, (int)$from->diff($to)->days + 1);
  }

  /**
   * Domain examples for the plan prompt; an absent domain block collapses to nothing.
   *
   * @return string Example block
   */
  private function examples(): string
  {
    $examples = $this->getDef('text_analysis_plan_examples');

    return $examples === 'text_analysis_plan_examples' ? '' : $examples;
  }

  /**
   * The plan echoes nothing of the question, but a rich question yields more metrics.
   *
   * @param string $question Question being planned
   * @return int Output-token budget
   */
  private function responseMaxTokens(string $question): int
  {
    return min(2000, max(500, 300 + 2 * (int)ceil(mb_strlen($question) / 4)));
  }

  /**
   * Loads both buckets the prompt spans: the agnostic skeleton and the domain examples.
   *
   * @return void
   */
  private function loadDefinitions(): void
  {
    if ($this->definitionsLoaded) {
      return;
    }

    $this->definitionsLoaded = true;
    DomainConfig::loadAgnosticLanguageFile('rag_analysis_plan');
    DomainConfig::loadLanguageFile('rag_analysis_plan');
  }

  /**
   * @param string $key Definition key
   * @param array $vars Interpolated variables
   * @return string Definition text
   */
  private function getDef(string $key, array $vars = []): string
  {
    return (string)Registry::get('Language')->getDef($key, $vars);
  }
}

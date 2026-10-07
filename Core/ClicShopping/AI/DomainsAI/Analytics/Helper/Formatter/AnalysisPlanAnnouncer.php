<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Helper\Formatter;

use ClicShopping\AI\CoreAI\Planning\CoherenceGuard;
use ClicShopping\OM\CLICSHOPPING;

/**
 * AnalysisPlanAnnouncer — restitution of what the analysis plan and the coherence guard decided:
 * reserve, withheld rows, period, scope and metric basis, stated in the interpretation itself.
 * Stateless: the per-request state stays on AnalyticsAgent and is passed per call.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Helper\Formatter
 */
class AnalysisPlanAnnouncer
{
  public function __construct(private bool $debug = false)
  {
  }

  /**
   * State the plan's verdicts on the response, in a fixed order: reserve and withheld rows at the
   * HEAD, period, scope and basis at the FOOT. Call AFTER the result cache was written.
   *
   * @param array $response Response being assembled, mutated in place
   * @param array|null $plan Validated analysis plan, null when none
   * @param array $unsatisfiable Plan elements that could not be honoured
   * @param array $withheldRows Labels of the rows withheld for a missing cost basis
   * @param int|null $withheldShare Revenue share they carried, null when unknown
   * @param array $noSaleRows Labels of the rows whose margin was withheld for having no net sale
   * @return void
   */
  public function announce(array &$response, ?array $plan, array $unsatisfiable, array $withheldRows, ?int $withheldShare, array $noSaleRows = []): void
  {
    $this->announceAnalysisPlanReserve($response, $unsatisfiable);
    $this->announceWithheldRows($response, $withheldRows, $withheldShare);
    $this->announceNoSaleRows($response, $noSaleRows);
    $this->announceSensitiveOutput($response);
    $this->announceAnalysisPeriod($response, $plan);
    $this->announceAnalysisScope($response, $plan);
    $this->announceMetricBasis($response, $plan);
  }

  /**
   * Announce, AT THE HEAD of the answer, what the plan could not honour.
   *
   * The reserve rides the `interpretation` string itself rather than a metadata key: that
   * string is what the restitution gates carry through to the user verbatim, and a key beside
   * it would be dropped by the first gate that rebuilds the response.
   *
   * It is added AFTER the result cache was written, so the cached entry keeps the plain
   * interpretation and the reserve is rebuilt from the plan on every turn, hit or miss.
   *
   * @param array $response Response being assembled, mutated in place
   * @param array $unsatisfiable Plan elements that could not be honoured
   * @return void
   */
  private function announceAnalysisPlanReserve(array &$response, array $unsatisfiable): void
  {
    if ($unsatisfiable === []) {
      return;
    }

    // Name the measure as the question named it; an entry with no label has nothing sayable.
    $labels = array_values(array_unique(array_filter(
      array_column($unsatisfiable, 'label'),
      static fn($label): bool => is_string($label) && $label !== ''
    )));

    $response['analysis_plan_unsatisfiable'] = $unsatisfiable;

    if ($labels === []) {
      return;
    }

    $reserve = CLICSHOPPING::getDef('text_analysis_plan_reserve', ['elements' => implode(', ', $labels)]);

    if ($reserve === '' || $reserve === 'text_analysis_plan_reserve') {
      return;
    }

    $response['analysis_plan_reserve'] = $reserve;
    $response['interpretation'] = trim($reserve . "\n\n" . (string)($response['interpretation'] ?? ''));

    $this->debugLog("PLAN RESERVE announced: " . $reserve, "PLAN");
  }

  /**
   * Say, AT THE HEAD of the answer, that it lists personal contact data: how many rows were kept
   * when the list was capped, and that the access is logged. Identity alone is traced, not said.
   *
   * @param array $response Response being assembled, mutated in place
   * @return void
   */
  private function announceSensitiveOutput(array &$response): void
  {
    $notice = self::sensitiveOutputNotice($response['sensitive_output'] ?? null);

    if ($notice === '') {
      return;
    }

    $response['interpretation'] = trim($notice . "\n\n" . (string)($response['interpretation'] ?? ''));

    $this->debugLog('SENSITIVE OUTPUT announced: ' . $notice, 'PLAN');
  }

  /**
   * The sentence owed at the head of an answer listing personal contact data; '' when none is owed.
   * Every path serving a `sensitive_output` verdict outside announce() states it through here.
   *
   * @param mixed $output The `sensitive_output` verdict of AnalyticsSqlExecutor::guardSensitiveOutput()
   * @return string
   */
  public static function sensitiveOutputNotice(mixed $output): string
  {
    if (!is_array($output) || ($output['level'] ?? '') !== 'contact') {
      return '';
    }

    $key = !empty($output['capped']) ? 'text_sensitive_output_capped' : 'text_sensitive_output_logged';
    $notice = CLICSHOPPING::getDef($key, [
      'served' => (string)($output['served'] ?? ''),
      'total' => (string)($output['total'] ?? ''),
    ]);

    return $notice === $key ? '' : $notice;
  }

  /**
   * Blank the margin of the result lines that have no cost basis; their other figures stay.
   *
   * The rejection unit is the margin cell, NAMED by announceWithheldRows(). Every line at the
   * bound is left alone: CoherenceGuard then withholds the pane, the honest verdict when nothing
   * is computable.
   *
   * @param array $results Result set of the executed query
   * @return array{results: array, withheld: array, share: ?int, no_sale: array} The same set, the
   *         margins without a cost basis or without a net sale set to null; the labels to announce
   */
  public function withholdRowsWithoutCostBasis(array $results): array
  {
    $rows = $results['results'] ?? null;

    if (!is_array($rows) || $rows === []) {
      return ['results' => $results, 'withheld' => [], 'share' => null, 'no_sale' => []];
    }

    $noSale = CoherenceGuard::withholdNoNetSaleRows($rows);

    if ($noSale['withheld'] !== []) {
      $rows = $noSale['rows'];
      $results['results'] = array_values($rows);
      $this->debugLog('COHERENCE: ' . count($noSale['withheld']) . ' row(s) withheld for no net sale ('
        . implode(', ', $noSale['withheld']) . ')', 'PLAN');
    }

    $verdict = CoherenceGuard::withholdMissingCostBasisRows($rows);

    if ($verdict['withheld'] === []) {
      return ['results' => $results, 'withheld' => [], 'share' => null, 'no_sale' => $noSale['withheld']];
    }

    $results['results'] = array_values($verdict['rows']);
    $results['count'] = count($results['results']);

    $this->debugLog('COHERENCE: ' . count($verdict['withheld']) . ' row(s) withheld for a missing cost basis ('
      . implode(', ', $verdict['withheld']) . ')', 'PLAN');

    return ['results' => $results, 'withheld' => $verdict['withheld'], 'share' => $verdict['share'], 'no_sale' => $noSale['withheld']];
  }

  /**
   * Name, at the HEAD of the answer, the rows whose margin went for having no net sale: a reader
   * who sees a blank margin unexplained reads it as missing data, not as a sale that was undone.
   *
   * @param array $response Response being assembled, mutated in place
   * @param array $noSaleRows Labels of those rows
   * @return void
   */
  private function announceNoSaleRows(array &$response, array $noSaleRows): void
  {
    if ($noSaleRows === []) {
      return;
    }

    $labels = array_values(array_unique(array_map(
      static fn(string $label): string => $label === CoherenceGuard::UNLABELLED_ROW
        ? CLICSHOPPING::getDef('text_coherence_row_unlabelled')
        : $label,
      $noSaleRows
    )));
    $notice = CLICSHOPPING::getDef('text_coherence_rows_withheld_no_net_sale', ['labels' => implode(', ', $labels)]);

    if ($notice === '' || $notice === 'text_coherence_rows_withheld_no_net_sale') {
      return;
    }

    $response['coherence_no_sale_rows'] = $labels;
    $response['interpretation'] = trim($notice . "\n\n" . (string)($response['interpretation'] ?? ''));

    $this->debugLog('NO NET SALE ROWS announced: ' . $notice, 'PLAN');
  }

  /**
   * Name the lines that were dropped for having no cost basis.
   *
   * At the HEAD of the answer, like the plan reserve: a reader who is not told a line is missing
   * reads the breakdown as complete. Saying which line went, and why, is what makes the pruning
   * honest rather than convenient.
   *
   * @param array $response Response being assembled, mutated in place
   * @param array $withheldRows Labels of the rows withheld
   * @param int|null $withheldShare Revenue share they carried, null when unknown
   * @return void
   */
  private function announceWithheldRows(array &$response, array $withheldRows, ?int $withheldShare): void
  {
    if ($withheldRows === []) {
      return;
    }

    $labels = array_values(array_unique(array_map(
      static fn(string $label): string => $label === CoherenceGuard::UNLABELLED_ROW
        ? CLICSHOPPING::getDef('text_coherence_row_unlabelled')
        : $label,
      $withheldRows
    )));
    $key = $withheldShare !== null
      ? 'text_coherence_rows_withheld_missing_cost_share'
      : 'text_coherence_rows_withheld_missing_cost';
    $notice = CLICSHOPPING::getDef($key, [
      'labels' => implode(', ', $labels),
      'share' => $withheldShare !== null ? (string)$withheldShare : '',
    ]);

    if ($notice === '' || $notice === $key) {
      return;
    }

    $response['coherence_withheld_rows'] = $labels;
    $response['interpretation'] = trim($notice . "\n\n" . (string)($response['interpretation'] ?? ''));

    $this->debugLog('WITHHELD ROWS announced: ' . $notice, 'PLAN');
  }

  /**
   * Say WHICH window the figures cover, every time the plan carries one.
   *
   * The window is the one fact the reader cannot recover from the figures, and a default one is
   * invisible unless it is said. Stating it is also how the merchant knows a different span is
   * his to ask for - the next question naming a period simply replaces what this line reports.
   *
   * Rides `interpretation` at the FOOT, added after the cache write, like the basis below.
   *
   * @param array $response Response being assembled, mutated in place
   * @param array|null $plan Validated analysis plan, null when none
   * @return void
   */
  private function announceAnalysisPeriod(array &$response, ?array $plan): void
  {
    $periods = $plan['periods'] ?? [];
    $from = (string)($periods['current']['from'] ?? '');
    $to = (string)($periods['current']['to'] ?? '');
    $allTime = ($periods['all_time'] ?? false) === true && $to !== '';

    if (($from === '' || $to === '') && !$allTime) {
      return;
    }

    $days = (float)($periods['default_days'] ?? 0.0);
    $today = date('Y-m-d');

    // A window reaching past today holds days no data can cover: say it, never let the answer
    // present a running period as a complete one.
    $key = match (true) {
      $allTime => 'text_analysis_period_all_time',
      $days > 0.0 => 'text_analysis_period_default',
      $to > $today => 'text_analysis_period_running',
      default => 'text_analysis_period_window',
    };

    $notice = CLICSHOPPING::getDef($key, [
      'from' => $from,
      'to' => $to,
      'today' => $today,
      'days' => rtrim(rtrim(number_format($days, 1, '.', ''), '0'), '.'),
    ]);

    if ($notice === '' || $notice === $key) {
      return;
    }

    $response['analysis_period'] = ['from' => $from, 'to' => $to, 'default_days' => $days];
    $response['analysis_period_notice'] = $notice;
    $response['interpretation'] = trim((string)($response['interpretation'] ?? '') . "\n\n" . $notice);

    $this->debugLog("ANALYSIS PERIOD announced: " . $notice, "PLAN");
  }

  /**
   * Say WHICH scope the figures cover, whenever the plan breaks down on a dimension it does not
   * restrict.
   *
   * Read from the plan, exactly like the period above: a plan carrying `dimensions` and no
   * matching `filters` measures EVERY member of that dimension. The plan of "this category" and
   * of "all categories" are byte-identical, so the widening can never be deduced from the
   * question - only the retained scope can be stated, and it is the one fact the reader would
   * otherwise take for a restriction.
   *
   * Rides `interpretation` at the FOOT, added after the cache write, like the period above.
   *
   * @param array $response Response being assembled, mutated in place
   * @param array|null $plan Validated analysis plan, null when none
   * @return void
   */
  private function announceAnalysisScope(array &$response, ?array $plan): void
  {
    $dimensions = array_values(array_filter(
      array_map('strval', $plan['dimensions'] ?? []),
      static fn(string $d): bool => $d !== ''
    ));

    if ($dimensions === []) {
      return;
    }

    $filters = array_keys(array_filter($plan['filters'] ?? [], static fn($v): bool => is_scalar($v) && (string)$v !== ''));
    $unrestricted = array_values(array_diff($dimensions, $filters));

    if ($unrestricted === []) {
      return;
    }

    // A plan dimension is a technical name: show its label, the raw name only when none exists.
    $labels = array_map(static function (string $d): string {
      $key = 'text_analysis_dimension_' . $d;
      $label = CLICSHOPPING::getDef($key);

      return $label === '' || $label === $key ? $d : $label;
    }, $unrestricted);

    $notice = CLICSHOPPING::getDef('text_analysis_scope_unrestricted', [
      'dimensions' => implode(', ', $labels),
    ]);

    if ($notice === '' || $notice === 'text_analysis_scope_unrestricted') {
      return;
    }

    $response['analysis_scope'] = ['unrestricted' => $unrestricted];
    $response['analysis_scope_notice'] = $notice;
    $response['interpretation'] = trim((string)($response['interpretation'] ?? '') . "\n\n" . $notice);

    $this->debugLog('ANALYSIS SCOPE announced: ' . $notice, 'PLAN');
  }

  /**
   * Name the convention the figures are stated on, whenever the plan elected a metric whose
   * domain declares one.
   *
   * The plan knows the identity of the measure before the SQL exists; without this line the
   * only carrier of the convention down to the user is the column alias, which the interpreting
   * model re-verbalises at will. Read from the plan, never from the model's prose.
   *
   * Rides `interpretation` and is added after the cache write, for the same two reasons as the
   * reserve above. Placed at the FOOT of the answer: it qualifies figures, it does not warn.
   *
   * @param array $response Response being assembled, mutated in place
   * @param array|null $plan Validated analysis plan, null when none
   * @return void
   */
  private function announceMetricBasis(array &$response, ?array $plan): void
  {
    $keys = array_values(array_unique(array_filter(
      array_column($plan['metrics'] ?? [], 'basis'),
      static fn($key): bool => is_string($key) && $key !== ''
    )));

    $labels = [];

    foreach ($keys as $key) {
      $label = CLICSHOPPING::getDef($key);

      // A key that resolves to itself is a missing definition, not a label.
      if ($label !== '' && $label !== $key) {
        $labels[] = $label;
      }
    }

    if ($labels === []) {
      return;
    }

    $basis = CLICSHOPPING::getDef('text_analysis_plan_basis', ['basis' => implode(', ', $labels)]);

    if ($basis === '' || $basis === 'text_analysis_plan_basis') {
      return;
    }

    $response['metric_basis'] = $basis;
    $response['interpretation'] = trim((string)($response['interpretation'] ?? '') . "\n\n" . $basis);

    $this->debugLog("METRIC BASIS announced: " . $basis, "PLAN");
  }

  private function debugLog(string $message, string $context = '', array $data = []): void
  {
    if (!$this->debug) {
      return;
    }

    $logMessage = $message;

    if (!empty($context)) {
      $logMessage = "[{$context}] {$message}";
    }

    if (!empty($data)) {
      $logMessage .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    error_log($logMessage);
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Gouvernance\Security;

use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\CLICSHOPPING;

/**
 * SecurityJournal
 *
 * Reads and maintains `rag_security_events` as a piece of EVIDENCE rather than a counter:
 * every figure is returned with the population it was measured on, so a reader can oppose it.
 *
 * Findings are returned as CODES, never as prose: the wording belongs to the surface that
 * renders them, this layer stays agnostic and free of language files.
 */
class SecurityJournal
{
  /**
   * Event types that trace a component's lifecycle instead of a security decision.
   * They are the denominator of every rate on the security screen, which is why they are
   * named here once and never inlined in a condition.
   */
  public const NON_SECURITY_EVENT_TYPES = ['websearch_facade_initialized'];

  private string $prefix;

  public function __construct()
  {
    $this->prefix = (string)CLICSHOPPING::getConfig('db_table_prefix');
  }

  /**
   * Measures what the security screen is actually counting over a window.
   *
   * @param int $days Window in days
   * @return array Population, the score the screen shows, and the finding codes with their figures
   */
  public function report(int $days = 7): array
  {
    $days = max(1, $days);
    $counts = $this->countEvents($days);

    $total = $counts['total'];
    $withThreatType = $counts['with_threat_type'];

    $noisePercent = $total > 0 ? round($counts['noise'] / $total * 100, 1) : 0.0;
    $detectionRate = $total > 0 ? round($withThreatType / $total * 100, 2) : 0.0;
    $blockRate = $withThreatType > 0 ? round($counts['blocked'] / $withThreatType * 100, 2) : 0.0;

    // Recomputed here on purpose: the report must explain the number the screen displays,
    // so it applies the same weights as DashboardStatsCollector::calculateSecurityHealthScore().
    $criticalScore = max(0, 100 - (($total > 0 ? $counts['critical'] / $total * 100 : 0) * 10));
    $healthScore = round(min(100, $detectionRate) * 0.4 + min(100, $blockRate) * 0.2 + $criticalScore * 0.4, 2);

    $findings = [];

    if ($counts['noise'] > 0) {
      $findings[] = [
        'code' => 'noise',
        'severity' => 'critical',
        'figures' => [
          'noise' => $counts['noise'],
          'total' => $total,
          'percent' => $noisePercent,
          'types' => implode(', ', self::NON_SECURITY_EVENT_TYPES)
        ]
      ];
    }

    if ($withThreatType > $counts['real_threats']) {
      $findings[] = [
        'code' => 'none_threat_type',
        'severity' => 'high',
        'figures' => [
          'counted' => $withThreatType,
          'real' => $counts['real_threats'],
          'rate' => $detectionRate
        ]
      ];
    }

    // Always reported: it is the reason the screen reads "poor" while nothing is wrong.
    $findings[] = [
      'code' => 'inverted_score',
      'severity' => 'critical',
      'figures' => [
        'score' => $healthScore,
        'detection' => $detectionRate,
        'block' => $blockRate,
        'floor' => round($criticalScore * 0.4, 1)
      ]
    ];

    if ($counts['with_score'] < $total) {
      $findings[] = [
        'code' => 'score_population',
        'severity' => 'medium',
        'figures' => [
          'with_score' => $counts['with_score'],
          'total' => $total
        ]
      ];
    }

    $detections = $this->detections();

    if ($detections !== []) {
      $findings[] = [
        'code' => 'detections',
        'severity' => 'high',
        'figures' => [
          'count' => array_sum(array_column($detections, 'count')),
          'reasons' => count($detections),
          'blocked' => $counts['blocked']
        ]
      ];
    }

    return [
      'generated_at' => date('Y-m-d H:i:s'),
      'detections' => $detections,
      'period_days' => $days,
      'population' => [
        'total' => $total,
        'noise' => $counts['noise'],
        'security' => $total - $counts['noise'],
        'real_threats' => $counts['real_threats'],
        'blocked' => $counts['blocked']
      ],
      'health_score' => $healthScore,
      'findings' => $findings
    ];
  }

  /**
   * Deletes the lifecycle traces, and ONLY those: no row carrying a threat, a decision or a
   * block is touched. The deletion is itself recorded on the security channel - purging a
   * journal without leaving a trace of the purge is what an audit faults.
   *
   * @return int Rows deleted
   */
  public function purgeNonSecurityEvents(): int
  {
    $placeholders = implode(',', array_fill(0, count(self::NON_SECURITY_EVENT_TYPES), '?'));

    $deleted = DoctrineOrm::execute(
      "DELETE FROM {$this->prefix}rag_security_events WHERE event_type IN ({$placeholders})",
      self::NON_SECURITY_EVENT_TYPES
    );

    // File channel: `event_type` is an ENUM with no member for a purge, and logEvent() would
    // silently downgrade an unknown type to 'security_check_failed' - a purge reading as a
    // failed security check. See sql/2026_09_01_security_event_purge_type.sql.
    (new SecurityLogger())->logSecurityEvent(
      'Security journal purged: ' . $deleted . ' lifecycle events removed (' . implode(', ', self::NON_SECURITY_EVENT_TYPES) . ')',
      'warning',
      ['deleted' => $deleted, 'event_types' => self::NON_SECURITY_EVENT_TYPES]
    );

    return $deleted;
  }

  /**
   * What the journal actually detected, grouped by the reason it recorded.
   *
   * The reason lives in `metadata`, never in `matched_patterns`: a detection whose reason cannot
   * be read cannot be reviewed, so it is read from where the writer puts it.
   *
   * Deliberately NOT limited to the window: a detection from three months ago is still what the
   * journal holds, and hiding it behind a 7-day cut is how "no threat" gets read as "all clear".
   * @return array<int, array{event_type:string, reason:string, count:int, blocked:int, first_seen:string, last_seen:string, sample:string}>
   */
  private function detections(): array
  {
    $placeholders = implode(',', array_fill(0, count(self::NON_SECURITY_EVENT_TYPES), '?'));

    $rows = DoctrineOrm::select("
      SELECT event_type,
             COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.obfuscation_types')), threat_type, detection_method) as reason,
             COUNT(*) as count,
             SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked,
             MIN(created_at) as first_seen,
             MAX(created_at) as last_seen,
             SUBSTRING(MIN(user_query), 1, 120) as sample
      FROM {$this->prefix}rag_security_events
      WHERE event_type NOT IN ({$placeholders})
        AND event_type <> 'layer_performance'
        AND (threat_type IS NULL OR threat_type <> 'none')
      GROUP BY event_type, reason
      ORDER BY count DESC
    ", self::NON_SECURITY_EVENT_TYPES);

    return array_map(static fn(array $row): array => [
      'event_type' => (string)$row['event_type'],
      'reason' => (string)($row['reason'] ?? ''),
      'count' => (int)$row['count'],
      'blocked' => (int)$row['blocked'],
      'first_seen' => (string)$row['first_seen'],
      'last_seen' => (string)$row['last_seen'],
      'sample' => (string)($row['sample'] ?? '')
    ], $rows);
  }

  /**
   * @param int $days Window in days
   * @return array<string, int> Raw counts over the window
   */
  private function countEvents(int $days): array
  {
    $placeholders = implode(',', array_fill(0, count(self::NON_SECURITY_EVENT_TYPES), '?'));

    $rows = DoctrineOrm::select("
      SELECT COUNT(*) as total,
             SUM(CASE WHEN event_type IN ({$placeholders}) THEN 1 ELSE 0 END) as noise,
             SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked,
             SUM(CASE WHEN threat_type IS NOT NULL THEN 1 ELSE 0 END) as with_threat_type,
             SUM(CASE WHEN threat_type IS NOT NULL AND threat_type <> 'none' THEN 1 ELSE 0 END) as real_threats,
             SUM(CASE WHEN threat_score IS NOT NULL THEN 1 ELSE 0 END) as with_score,
             SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical
      FROM {$this->prefix}rag_security_events
      WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ", [...self::NON_SECURITY_EVENT_TYPES, $days]);

    $row = $rows[0] ?? [];

    return [
      'total' => (int)($row['total'] ?? 0),
      'noise' => (int)($row['noise'] ?? 0),
      'blocked' => (int)($row['blocked'] ?? 0),
      'with_threat_type' => (int)($row['with_threat_type'] ?? 0),
      'real_threats' => (int)($row['real_threats'] ?? 0),
      'with_score' => (int)($row['with_score'] ?? 0),
      'critical' => (int)($row['critical'] ?? 0)
    ];
  }
}

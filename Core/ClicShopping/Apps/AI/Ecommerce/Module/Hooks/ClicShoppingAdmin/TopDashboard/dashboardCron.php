<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\TopDashboard;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;

/**
 * dashboardCron
 *
 * Renders a single top-dashboard card consolidating the last 24h of cron runs
 * (SEO, CockpitAI, FAQ, Embeddings …).  The card self-hides when there is no
 * recent activity so the dashboard stays clean on installs that have never
 * triggered these crons.
 *
 * Card background colour reflects the worst observed status:
 *   - any failed   → danger (red)
 *   - any partial  → warning (orange)
 *   - any running  → info (blue)
 *   - all clean    → success (green)
 */
class dashboardCron implements HooksInterface
{
  public mixed $app;
  private mixed $db;

  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
    $this->db  = Registry::get('Db');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/TopDashboard/top_dashboard_cron_status');
  }

  /**
   * Renders one consolidated card listing every origin that ran in the last
   * 24h.  Returns empty string when no cron activity is found.
   */
  public function Display(): string
  {
    $rows = $this->fetchLatestRuns();

    if (empty($rows)) {
      return '';
    }

    $cardColor = $this->cardColor($rows);
    $title     = $this->app->getDef('top_dashboard_cron_title') ?: 'Cron status (24h)';
    $viewLabel = $this->app->getDef('top_dashboard_cron_view') ?: 'View logs';
    $cronUrl   = CLICSHOPPING::link(null, 'A&Tools\Cronjob&Cronjob');

    $lines = '';

    foreach ($rows as $row) {
      $origin   = (string)$row['origin'];
      $status   = (string)$row['status'];
      $finished = (string)($row['finished_at'] ?? $row['started_at']);
      $label    = $this->originLabel($origin);
      [$badge, $icon] = $this->statusVisual($status);
      $relative = $this->relativeTime($finished);

      $tooltip = sprintf('%s — %s', $this->statusLabel($status), $finished);

      $lines .= '
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="text-white small text-truncate me-2"
                title="' . HTML::outputProtected($tooltip) . '">
            <i class="bi ' . $icon . '"></i> ' . HTML::outputProtected($label) . '
          </span>
          <span class="badge ' . $badge . ' text-uppercase small">
            ' . HTML::outputProtected($this->statusLabel($status)) . '
          </span>
        </div>
        <div class="text-white-50 small mb-2">' . HTML::outputProtected($relative) . '</div>';
    }

    return '
<div class="col-md-2 col-12 m-1">
  <div class="card ' . $cardColor . ' shadow-sm border-0 rounded-3">
    <div class="card-body p-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="card-title text-white mb-0">
          <i class="bi bi-command"></i> ' . HTML::outputProtected($title) . '
        </h6>
        ' . HTML::link($cronUrl, '<i class="bi bi-info-circle text-white"></i>',
                       'class="text-white text-decoration-none"') . '
      </div>
      ' . $lines . '
      <div class="text-end mt-1">
        <small>' . HTML::link($cronUrl, $viewLabel, 'class="text-white"') . '</small>
      </div>
    </div>
  </div>
</div>';
  }

  /**
   * Last cron_log row per origin in the rolling 24h window.
   */
  private function fetchLatestRuns(): array
  {
    try {
      $Q = $this->db->prepare('
        SELECT cl.origin,
               cl.status,
               cl.started_at,
               cl.finished_at
        FROM :table_cron_log cl
        INNER JOIN (
          SELECT origin,
                 MAX(id) AS last_id
          FROM :table_cron_log
          WHERE started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          GROUP BY origin
        ) latest ON latest.last_id = cl.id
        ORDER BY cl.finished_at DESC,
                 cl.started_at DESC
      ');
      $Q->execute();

      return $Q->fetchAll() ?: [];
    } catch (\Throwable $e) {
      // The widget must never break the dashboard if the table is missing
      // (e.g. fresh install before MariaDb.php has been applied).
      return [];
    }
  }

  /**
   * Picks the card background colour based on the worst status across runs.
   */
  private function cardColor(array $rows): string
  {
    $statuses = array_column($rows, 'status');

    if (in_array('failed', $statuses, true)) {
      return 'bg-danger';
    }
    if (in_array('partial', $statuses, true)) {
      return 'bg-warning';
    }
    if (in_array('running', $statuses, true)) {
      return 'bg-info';
    }
    if (in_array('skipped', $statuses, true) && !in_array('completed', $statuses, true)) {
      return 'bg-secondary';
    }
    return 'bg-success';
  }

  /**
   * @return array{0:string,1:string} Bootstrap badge class + Bootstrap icon class
   */
  private function statusVisual(string $status): array
  {
    return match ($status) {
      'completed' => ['bg-success',   'bi-check-circle-fill'],
      'failed'    => ['bg-danger',    'bi-x-circle-fill'],
      'partial'   => ['bg-warning text-dark', 'bi-exclamation-triangle-fill'],
      'skipped'   => ['bg-secondary', 'bi-dash-circle'],
      'running'   => ['bg-info text-dark', 'bi-arrow-repeat'],
      default     => ['bg-light text-dark', 'bi-question-circle'],
    };
  }

  private function originLabel(string $origin): string
  {
    $key   = 'cron_status_origin_' . $origin;
    $label = $this->app->getDef($key);

    if (!empty($label)) {
      return $label;
    }

    return match ($origin) {
      'seo'       => 'SEO',
      'faq'       => 'FAQ',
      'cockpitai' => 'CockpitAI',
      'embedding' => 'Embeddings',
      default     => ucfirst($origin),
    };
  }

  private function statusLabel(string $status): string
  {
    $key   = 'cron_status_state_' . $status;
    $label = $this->app->getDef($key);

    if (!empty($label)) {
      return $label;
    }

    return ucfirst($status);
  }

  /**
   * Compact "5 min ago" / "2 h ago" formatter; falls back to the ISO date for
   * runs older than 24 hours (which should not happen given the SQL window).
   */
  private function relativeTime(string $datetime): string
  {
    if ($datetime === '') {
      return '';
    }

    $ts   = strtotime($datetime);
    $diff = max(0, time() - $ts);

    if ($diff < 60) {
      $unitKey = 'top_dashboard_cron_relative_seconds';
      return sprintf($this->app->getDef($unitKey) ?: '%d s ago', $diff);
    }
    if ($diff < 3600) {
      $unitKey = 'top_dashboard_cron_relative_minutes';
      return sprintf($this->app->getDef($unitKey) ?: '%d min ago', (int)($diff / 60));
    }
    if ($diff < 86400) {
      $unitKey = 'top_dashboard_cron_relative_hours';
      return sprintf($this->app->getDef($unitKey) ?: '%d h ago', (int)($diff / 3600));
    }

    return $datetime;
  }
}

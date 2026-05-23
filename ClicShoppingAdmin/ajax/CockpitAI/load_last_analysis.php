<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\HTML;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\CockpitAIOrchestrator;

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();

CLICSHOPPING::loadSite('ClicShoppingAdmin');

header('Content-Type: application/json; charset=utf-8');

AdministratorAdmin::hasUserAccess();

// Initialize orchestrator
$orchestrator = new CockpitAIOrchestrator();

try {
  // Check if module is enabled
  if ($orchestrator->checkStatus() === false) {
    echo json_encode([
      'success' => false,
      'error' => 'CockpitAI module is not enabled',
      'error_code' => 'MODULE_DISABLED'
    ]);
    exit;
  }

  // Validate product ID
  if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    echo json_encode([
      'success' => false,
      'error' => 'Product ID is required',
      'error_code' => 'INVALID_PRODUCT_ID'
    ]);
    exit;
  }

  $productId = (int)HTML::sanitize($_GET['product_id']);

  if ($productId <= 0) {
    echo json_encode([
      'success' => false,
      'error' => 'Invalid product ID',
      'error_code' => 'INVALID_PRODUCT_ID'
    ]);
    exit;
  }

  // Get language ID from GET parameter or session
  if (isset($_GET['language_id']) && is_numeric($_GET['language_id'])) {
    $languageId = (int)$_GET['language_id'];
  } elseif (Registry::exists('Language')) {
    $languageId = Registry::get('Language')->getId();
  } else {
    $languageId = 1;
  }

  // Whether the caller wants the full score time series (for sparklines)
  $wantHistory = isset($_GET['history']) && (int)$_GET['history'] === 1;

  // Get database connection
  $db = Registry::get('Db');

  // Query last embedding for this product and language
  $query = $db->prepare('SELECT metadata,
                                date_modified
                         FROM :table_products_cockpit_ai_embedding
                         WHERE entity_id = :entity_id
                         AND language_id = :language_id
                         ORDER BY date_modified DESC
                         LIMIT 1');

  $query->bindInt(':entity_id', $productId);
  $query->bindInt(':language_id', $languageId);
  $query->execute();

  $result = $query->fetch();

  if (!$result || empty($result['metadata'])) {
    // No analysis found
    echo json_encode([
      'success' => false,
      'error' => 'No analysis found for this product',
      'error_code' => 'NO_ANALYSIS_FOUND'
    ]);
    exit;
  }

  // Decode metadata JSON
  $metadata = json_decode($result['metadata'], true);

  if (!$metadata) {
    echo json_encode([
      'success' => false,
      'error' => 'Failed to parse analysis metadata',
      'error_code' => 'INVALID_METADATA'
    ]);
    exit;
  }

  $effectiveSeoStatus = $metadata['seo']['status'] ?? 'NOT_ANALYZED';
  $effectiveSeoScore  = $metadata['seo']['score']  ?? null;
  $seoDataStale       = false;
  try {
    $Qfresh = $db->prepare('
      SELECT seo_score_after, created_at
      FROM   :table_seo_serp_reports
      WHERE  entity_type = :entity_type
        AND  entity_id   = :entity_id
        AND  language_id = :language_id
        AND  seo_score_after > 0
      ORDER BY created_at DESC
      LIMIT 1
    ');
    $Qfresh->bindValue(':entity_type', 'product');
    $Qfresh->bindInt(':entity_id',     $productId);
    $Qfresh->bindInt(':language_id',   $languageId);
    $Qfresh->execute();
    if ($Qfresh->rowCount() > 0) {
      $seoCreatedAt = (string)$Qfresh->value('created_at');
      $analysisAt   = (string)($result['date_modified'] ?? '');
      $freshScore   = (float)($Qfresh->valueDecimal('seo_score_after') ?? 0);
      // If a fresher SEO row exists, override status + score and flag stale
      if ($seoCreatedAt !== '' && (
            $analysisAt === '' || strtotime($seoCreatedAt) > strtotime($analysisAt)
          )) {
        $effectiveSeoStatus = 'ANALYZED';
        $effectiveSeoScore  = $freshScore;
        $seoDataStale       = true;
      } elseif ($effectiveSeoStatus !== 'ANALYZED' && $freshScore > 0) {
        // Older row but still valid SEO — surface it rather than show NOT_ANALYZED
        $effectiveSeoStatus = 'ANALYZED';
        $effectiveSeoScore  = $freshScore;
      }
    }
  } catch (\Throwable $e) {
    // Defensive — never block the UI on the freshness probe.
  }

  // Reconstruct Analysis_Report structure from metadata
  // This matches the structure returned by analyze_product.php
  $report = [
    'header' => [
      'product_id' => $productId,
      'language_id' => $languageId,
      'analysis_date' => $result['date_modified'],
      'seo_status' => $effectiveSeoStatus,
      'seo_score'  => $effectiveSeoScore,
      'seo_data_stale' => $seoDataStale, // true when SEO is fresher than CockpitAI
      'pipeline_duration_ms' => $metadata['technical']['pipeline_duration_ms'] ?? 0,
    ],

    'score_x' => [
      'value' => $metadata['scores']['score_x'] ?? 0,
      'factors' => [], // Factors not stored in metadata, only scores
    ],

    'score_y' => [
      'value' => $metadata['scores']['score_y'] ?? 0,
      'factors' => [], // Factors not stored in metadata, only scores
    ],

    'quadrant' => [
      'code' => $metadata['scores']['quadrant'] ?? 'Q_intermediate',
      'label' => getQuadrantLabel($metadata['scores']['quadrant'] ?? 'Q_intermediate'),
      'strategy' => getQuadrantStrategy($metadata['scores']['quadrant'] ?? 'Q_intermediate'),
    ],

    'analysis' => [
      'text' => $metadata['analysis']['text'] ?? '',
      'fallback_used' => $metadata['analysis']['fallback_used'] ?? true,
    ],

    'action_plan' => [
      'actions' => (function() use ($metadata, $effectiveSeoStatus, $effectiveSeoScore) {
        $actions = $metadata['actions'] ?? [];
        if (!is_array($actions) || empty($actions) || $effectiveSeoStatus !== 'ANALYZED') {
          return $actions;
        }
        $obsoleteCodes = ['launch_seo_analysis'];
        // If the live SEO score is also above the low-score threshold,
        // the optimize_seo recommendation no longer applies either.
        if ($effectiveSeoScore !== null && (float)$effectiveSeoScore >= 50.0) {
          $obsoleteCodes[] = 'optimize_seo';
        }
        $filtered = [];
        foreach ($actions as $a) {
          $code = is_array($a) ? ($a['code'] ?? $a['action_code'] ?? '') : '';
          if (in_array($code, $obsoleteCodes, true)) {
            continue;
          }
          $filtered[] = $a;
        }
        return $filtered;
      })(),
      'total_rules_triggered' => count($metadata['actions'] ?? []),
      'conflicts_resolved' => 0,
    ],

    'history' => (function() use ($db, $productId, $languageId) {
      try {
        $Qhist = $db->prepare('SELECT content, date_modified
                               FROM :table_products_cockpit_ai_embedding
                               WHERE entity_id = :entity_id
                                 AND language_id = :language_id
                               ORDER BY date_modified DESC
                               LIMIT 3');
        $Qhist->bindInt(':entity_id', $productId);
        $Qhist->bindInt(':language_id', $languageId);
        $Qhist->execute();

        $history = [];
        while ($row = $Qhist->fetch()) {
          $history[] = ['content' => $row['content'] ?? '', 'date' => $row['date_modified'] ?? ''];
        }
        return $history;
      } catch (\Throwable) {
        return [];
      }
    })(),

    'technical' => [
      'steps_executed' => 8,
      'steps_completed' => 8, // Assume all steps completed if analysis exists
      'steps_failed' => 0,
      'embedding_id' => null,
      'pipeline_duration_ms' => $metadata['technical']['pipeline_duration_ms'] ?? 0,
    ],
  ];

  // ── Score history for sparklines (history=1 only) ───────────────────────
  $scoreHistory = null;
  if ($wantHistory) {
    $Qhist = $db->prepare('
      SELECT
        DATE(date_modified)                                            AS date,
        ROUND(JSON_EXTRACT(metadata, \'$.scores.score_x\'), 1)        AS score_x,
        ROUND(JSON_EXTRACT(metadata, \'$.scores.score_y\'), 1)        AS score_y
      FROM :table_products_cockpit_ai_embedding
      WHERE entity_id = :entity_id
        AND language_id = :language_id
        AND JSON_EXTRACT(metadata, \'$.scores.score_x\') IS NOT NULL
      ORDER BY date_modified ASC
    ');

    $Qhist->bindInt(':entity_id', $productId);
    $Qhist->bindInt(':language_id', $languageId);
    $Qhist->execute();

    $scoreHistory = [];
    while ($hrow = $Qhist->fetch()) {
      $scoreHistory[] = [
        'date'    => (string) ($hrow['date']    ?? ''),
        'score_x' => (float)  ($hrow['score_x'] ?? 0),
        'score_y' => (float)  ($hrow['score_y'] ?? 0),
      ];
    }
  }

  // Return success response with reconstructed report
  echo json_encode([
    'success' => true,
    'data' => $report,
    'history' => $wantHistory ? $scoreHistory : null,
    'source' => 'database',
    'analysis_date' => $result['date_modified']
  ]);

} catch (\Throwable $e) {
  $cockpitDebug = defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG')
    && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';

  if ($cockpitDebug) {
    error_log('CockpitAI Load Last Analysis Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
  }

  // Return error response
  echo json_encode([
    'success' => false,
    'error' => 'An error occurred while loading last analysis: ' . $e->getMessage(),
    'error_code' => 'LOAD_ERROR',
    'debug' => $cockpitDebug ? [
      'message' => $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine()
    ] : null
  ]);
}

/**
 * Get human-readable label for quadrant code
 *
 * @param string $code Quadrant code (Q1, Q2, Q3, Q4, Q_intermediate)
 * @return string Human-readable label
 */
function getQuadrantLabel(string $code): string
{
  return match ($code) {
    'Q1' => 'Scaling',
    'Q2' => 'Acquisition',
    'Q3' => 'Rework / Kill',
    'Q4' => 'Optimization',
    'Q_intermediate' => 'Monitoring',
    default => 'Unknown',
  };
}

/**
 * Get strategic recommendation for quadrant code
 *
 * @param string $code Quadrant code (Q1, Q2, Q3, Q4, Q_intermediate)
 * @return string Strategic recommendation
 */
function getQuadrantStrategy(string $code): string
{
  return match ($code) {
    'Q1' => 'Maintain and amplify.',
    'Q2' => 'Improve visibility and commercial reach.',
    'Q3' => 'Major rework required or consider removal.',
    'Q4' => 'Improve product sheet quality to unlock sales potential.',
    'Q_intermediate' => 'Monitor and maintain — no urgent action required.',
    default => '',
  };
}

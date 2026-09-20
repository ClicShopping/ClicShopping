<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * Renders the feedback journal report: figures come from AI\Gouvernance\Feedback\FeedbackJournal,
 * wording comes from the App language files. Deterministic, no LLM call.
 *
 * Both dashboards call THIS endpoint, so the manager and the data scientist read one analysis.
 */

use ClicShopping\AI\Gouvernance\Feedback\FeedbackJournal;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

define('CLICSHOPPING_BASE_DIR', dirname(__DIR__, 3) . '/Core/ClicShopping/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');
AdministratorAdmin::hasUserAccess();

header('Content-Type: application/json');

try {
  // The App is not registered in an ajax context: it carries the language definitions.
  if (!Registry::exists('ChatGpt')) {
    Registry::set('ChatGpt', new ChatGpt());
  }

  $app = Registry::get('ChatGpt');
  $app->loadDefinitions('Sites/ClicShoppingAdmin/dashboard');

  $report = (new FeedbackJournal())->refresh(isset($_GET['days']) ? (int)$_GET['days'] : 30);

  foreach ($report['findings'] as $i => $finding) {
    $figures = $finding['figures'] + ['population' => $finding['population']];

    $report['findings'][$i]['title'] = $app->getDef('feedback_report_' . $finding['code'] . '_title', $figures);
    $report['findings'][$i]['body'] = $app->getDef('feedback_report_' . $finding['code'] . '_body', $figures);
    // A finding without the gesture that closes it is only a complaint.
    $report['findings'][$i]['action'] = $app->getDef('feedback_report_' . $finding['code'] . '_action', $figures);
    $report['findings'][$i]['scope'] = $finding['agent_used'] === null
      ? ''
      : $app->getDef('feedback_report_scope', [
          'agent' => (string)$finding['agent_used'],
          'intent' => (string)($finding['intent_type'] ?? '')
        ]);
  }

  $report['no_finding'] = $app->getDef('feedback_report_no_finding');

  $report['limits'] = $app->getDef('feedback_report_limits', [
    'days' => $report['period_days'],
    'population' => $report['findings'][0]['population'] ?? 0
  ]);

  echo json_encode(['success' => true, 'data' => $report], JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
  error_log('get_feedback_report: ' . $e->getMessage());

  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'report_failed']);
}

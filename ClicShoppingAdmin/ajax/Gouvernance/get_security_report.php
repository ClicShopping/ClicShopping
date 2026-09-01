  <?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * Renders the security journal report: figures come from AI\Gouvernance\Security\SecurityJournal,
 * wording comes from the App language files. Deterministic, no LLM call.
 */

use ClicShopping\AI\Gouvernance\Security\SecurityJournal;
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
  $report = (new SecurityJournal())->report(isset($_GET['days']) ? (int)$_GET['days'] : 7);

  foreach ($report['findings'] as $i => $finding) {
    $report['findings'][$i]['title'] = $app->getDef('security_report_' . $finding['code'] . '_title', $finding['figures']);
    $report['findings'][$i]['body'] = $app->getDef('security_report_' . $finding['code'] . '_body', $finding['figures']);
    // A finding without the gesture that closes it is only a complaint.
    $report['findings'][$i]['action'] = $app->getDef('security_report_' . $finding['code'] . '_action', $finding['figures']);
  }

  $report['detections_label'] = $app->getDef('security_report_detections_label');
  $report['no_detection'] = $app->getDef('security_report_no_detection');

  $report['limits'] = $app->getDef('security_report_limits', [
    'days' => $report['period_days'],
    'security' => $report['population']['security']
  ]);

  echo json_encode(['success' => true, 'data' => $report], JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
  error_log('get_security_report: ' . $e->getMessage());

  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'report_failed']);
}

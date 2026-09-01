<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * Removes the component lifecycle traces from the security journal.
 *
 * POST only: it deletes rows from an audit trail, and a deletion must not be reachable by
 * following a link. Rows carrying a threat, a decision or a block are never touched.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'post_required']);
  exit;
}

try {
  // The App is not registered in an ajax context: it carries the language definitions.
  if (!Registry::exists('ChatGpt')) {
    Registry::set('ChatGpt', new ChatGpt());
  }

  $app = Registry::get('ChatGpt');
  $app->loadDefinitions('Sites/ClicShoppingAdmin/dashboard');
  $deleted = (new SecurityJournal())->purgeNonSecurityEvents();

  echo json_encode([
    'success' => true,
    'deleted' => $deleted,
    'message' => $app->getDef('security_clear_done', ['deleted' => $deleted])
  ], JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
  error_log('clear_security_noise: ' . $e->getMessage());

  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'clear_failed']);
}

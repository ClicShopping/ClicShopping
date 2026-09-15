<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * Purge the answer cache of ONE question.
 *
 * A user report may be about a replayed answer, not a real defect: the query cache is keyed on the
 * question, and a hit is indistinguishable from a correct answer. Clearing the whole cache to check
 * one report costs every other entry, so this targets a single question and reports what it removed.
 */

use ClicShopping\AI\Infrastructure\Cache\QueryCache;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

define('PAGE_PARSE_START_TIME', microtime());
define('CLICSHOPPING_BASE_DIR', dirname(__DIR__, 3) . '/Core/ClicShopping/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');

AdministratorAdmin::hasUserAccess();

header('Content-Type: application/json');

try {
  $input = json_decode((string)file_get_contents('php://input'), true);
  $question = trim((string)($input['question'] ?? ''));

  if ($question === '') {
    echo json_encode(['success' => false, 'error' => 'question is required'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 1. The cache's own invalidation: the only path that reaches a Memcached / Redis / file backend.
  //    Its key embeds the context mode, so it clears the generic entry, not necessarily every mode.
  $invalidated = (new QueryCache())->invalidate($question);

  // 2. The stored question is the raw one, so this catches every mode of the DB backend — including
  //    what step 1 cannot address. Truncated to 500 on write, so compare on the same length.
  $db = Registry::get('Db');

  $delete = $db->prepare('DELETE FROM :table_rag_query_cache WHERE user_query = :user_query');
  $delete->bindValue(':user_query', mb_substr($question, 0, 500));

  // execute() returns false on a SQL error without throwing: read "0 row" as failure, not as
  // "nothing was cached".
  if ($delete->execute() === false) {
    throw new \RuntimeException('DELETE failed: ' . implode(' ', $delete->errorInfo()));
  }

  $rows = $delete->rowCount();

  error_log("Purge question cache: '{$question}' — {$rows} row(s) deleted, invalidate=" . ($invalidated ? 'true' : 'false'));

  echo json_encode([
    'success' => true,
    'question' => $question,
    'rows_deleted' => $rows,
    'backend_invalidated' => $invalidated
  ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
  error_log('Purge question cache error: ' . $e->getMessage());

  echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

exit;

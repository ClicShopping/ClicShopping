<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * cron-cli.php — command-line cron entry point.
 *
 * Same dispatch as the HTTP pages (Cronjob/Sites/.../{Run,RunAll} +
 * Sites/Shop/Pages/CJ) but run from a shell so AI crons that take minutes per
 * product (SEO ≈ 3 min/product, CockpitAI LLM analysis) are NOT killed by the
 * web server's max_execution_time. Meant to be scheduled by an external cron
 * (ISPConfig, crontab, …), one call per cron id:
 *
 *   php cron-cli.php <cronId>            run that cron if enabled and due
 *   php cron-cli.php <cronId> --force    run it now, ignore the status + cycle gates
 *   php cron-cli.php --all               run every enabled + due cron
 *   php cron-cli.php --list              print the cron table, run nothing (safe)
 *
 * A disabled cron (clic_cron.status = 0 — e.g. the AI app is not installed or the
 * merchant turned the cron off in the admin) is skipped in EVERY mode, whether
 * targeted by id or swept by --all. Only --force overrides a disabled row.
 *
 * Per-cron gating is preserved by setting $_GET['cronId'] before dispatch: every
 * App's Cronjob/Process hook self-gates on Cron::getCronCode() == $_GET['cronId'],
 * so only the targeted concern runs (the broadcast still reaches all hooks, but
 * the non-matching ones no-op). Named per-cron hooks are dispatched via the row
 * `action` column, exactly like the HTTP path.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron;

define('PAGE_PARSE_START_TIME', microtime());
define('CLICSHOPPING_BASE_DIR', __DIR__ . '/Core/ClicShopping/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('Shop');

if (PHP_SAPI !== 'cli') {
  die("This script can only be run from the command line.\n");
}

// The whole point of the CLI entry: lift the time cap the HTTP entry point
// cannot, so a full catch-up batch of long AI crons completes in one run.
set_time_limit(0);

$args       = array_slice($argv, 1);
$force      = in_array('--force', $args, true);
$runAll     = in_array('--all', $args, true);
$positional = array_values(array_filter($args, static fn($a) => !str_starts_with($a, '--')));

$hooks = Registry::get('Hooks');
$time  = time();

// ── Safe default: no args (or --list) prints the table and runs nothing ──────
if (empty($args) || in_array('--list', $args, true)) {
  $rows = Cron::getCrons(null, null);
  printf("%-4s %-28s %-10s %-26s %-6s %s\n", 'id', 'code', 'cycle', 'action', 'status', 'date_modified');
  foreach ($rows as $r) {
    printf(
      "%-4s %-28s %-10s %-26s %-6s %s\n",
      $r['cron_id'], $r['code'], $r['cycle'], (string)($r['action'] ?? ''),
      $r['status'] ? 'ON' : 'off', (string)($r['date_modified'] ?? '')
    );
  }
  exit(0);
}

// ── Select the rows to consider ──────────────────────────────────────────────
if ($runAll) {
  $results = Cron::getCrons(null, null);
} else {
  $cronId = $positional[0] ?? null;

  if ($cronId === null || !ctype_digit((string)$cronId)) {
    fwrite(STDERR, "Usage: php cron-cli.php <cronId> [--force] | --all | --list\n");
    exit(1);
  }

  // Cron::getCrons() filters on $_GET['cronId']; the Process hooks self-gate on
  // it too. Set it before any dispatch so both read the intended target.
  $_GET['cronId'] = (string)(int)$cronId;
  $results = Cron::getCrons(null, (int)$cronId);
}

$dispatched = 0;

foreach ($results as $result) {
  // Respect the on/off status in EVERY mode. A disabled cron must never run —
  // typically the App owning it (e.g. the AI stack) is not installed/enabled on
  // this config, so its concern would fatal or waste resources. --force is the
  // explicit manual override for testing.
  if ((int)$result['status'] !== 1 && !$force) {
    echo "[cron-cli] skip (disabled) cron_id={$result['cron_id']} code={$result['code']} status=off (use --force to override)\n";
    continue;
  }

  $due = $force
    || (strtotime('+1 ' . $result['cycle'], strtotime((string)$result['date_modified'])) < ($time + 10));

  if (!$due) {
    echo "[cron-cli] skip (not due) cron_id={$result['cron_id']} code={$result['code']} cycle={$result['cycle']}\n";
    continue;
  }

  Cron::updateCron((int)$result['cron_id']);

  // Point the per-cron gate at the row we are running so only its concern fires.
  $_GET['cronId'] = (string)(int)$result['cron_id'];

  $hooks->call('Cronjob', 'Process');

  $action = trim((string)($result['action'] ?? ''));
  if ($action !== '' && $action !== 'Process') {
    $hooks->call('Cronjob', $action);
  }

  $dispatched++;
  echo "[cron-cli] dispatched cron_id={$result['cron_id']} code={$result['code']} action={$action}\n";
}

echo "[cron-cli] done — {$dispatched} cron(s) dispatched.\n";
exit(0);

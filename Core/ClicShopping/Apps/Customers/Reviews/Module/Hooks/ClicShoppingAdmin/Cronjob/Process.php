<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Module\Hooks\ClicShoppingAdmin\Cronjob;

use ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment\ReviewSentimentCronRunner;
use ClicShopping\OM\Interfaces\HooksInterface;

/**
 * Process — daily review-sentiment cron (ClicShoppingAdmin entry point).
 *
 * Thin hook: the whole auto-generation pipeline and auto-accept policy live in
 * {@see ReviewSentimentCronRunner}, shared with the Shop hook so the behaviour is
 * defined exactly once. Registered in the Reviews app clicshopping.json.
 */
class Process implements HooksInterface
{
  public function execute()
  {
    return (new ReviewSentimentCronRunner())->run();
  }
}

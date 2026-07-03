<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Module\Hooks\Shop\Cronjob;

use ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment\ReviewSentimentCronRunner;
use ClicShopping\OM\Interfaces\HooksInterface;

/**
 * Process — daily review-sentiment cron (Shop entry point).
 *
 * Thin hook: delegates to {@see ReviewSentimentCronRunner}, shared with the
 * ClicShoppingAdmin hook. Registered in the Reviews app clicshopping.json.
 */
class Process implements HooksInterface
{
  public function execute()
  {
    return (new ReviewSentimentCronRunner())->run();
  }
}

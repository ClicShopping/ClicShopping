<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\Shop\Cronjob;

use ClicShopping\Apps\AI\Ecommerce\Classes\Shared\SeoCronRunner;
use ClicShopping\OM\Interfaces\HooksInterface;

/**
 * SeoOptimization — daily SEO cron (Shop entry point).
 *
 * Thin hook: the whole 3-phase pipeline, auto-accept policy and administrator
 * warning note live in {@see SeoCronRunner}, shared with the ClicShoppingAdmin
 * hook so the behaviour is defined exactly once.
 */
class SeoOptimization implements HooksInterface
{
  public function execute()
  {
    return (new SeoCronRunner())->run();
  }
}

<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

/**
 * Customer account area.
 */
class Account extends \ClicShopping\OM\Domains\PagesAbstract
{
  /**
   * Action rendering the dashboard, i.e. the page's default template.
   */
  private const DEFAULT_ACTION = 'Main';

  /**
   * Sends any request that resolves to no action to the dashboard URL.
   *
   * `templates/main.php` used to carry the guard and the rendering itself, so a bare "/Account"
   * — or any unknown segment — silently produced the dashboard. Now that Actions/Main.php owns
   * that logic the template only renders, so those requests must be routed to the canonical URL
   * instead of reaching it with no content.
   *
   * @return void
   */
  protected function init()
  {
    $next = array_keys(array_slice($_GET, 1, null, true))[0] ?? null;

    // Never redirect the dashboard segment itself: pointing it at its own URL would loop.
    if ($next === self::DEFAULT_ACTION) {
      return;
    }

    if ($next !== null && $this->actionExists(HTML::sanitize(basename($next)))) {
      return;
    }

    CLICSHOPPING::redirect(null, $this->code . '&' . self::DEFAULT_ACTION);
  }
}

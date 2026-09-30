<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Reviews;

use ClicShopping\OM\Interfaces\HooksInterface;

class SetFlag implements HooksInterface
{
  /**
   * Approving a review embeds it, disapproving removes its embedding.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['id'])) {
      (new Update())->sync((int)$_GET['id']);
    }
  }
}

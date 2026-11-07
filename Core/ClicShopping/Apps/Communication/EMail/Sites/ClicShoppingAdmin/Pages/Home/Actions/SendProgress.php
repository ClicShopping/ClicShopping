<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\EMail\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

/**
 * Renders the resumable send progress page. The template processes one time-bounded
 * batch and meta-refreshes back to this action until the queue is empty.
 */
class SendProgress extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_EMail = Registry::get('EMail');

    $this->page->setFile('send_progress.php');

    $CLICSHOPPING_EMail->loadDefinitions('Sites/ClicShoppingAdmin/email');
  }
}

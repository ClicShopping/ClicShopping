<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Communication\Newsletter\Sites\ClicShoppingAdmin\Pages\Home\Actions\Newsletter;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Unlock extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Newsletter = Registry::get('Newsletter');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $newsletter_id = null;

    if (isset($_GET['nID'])) {
      $newsletter_id = HTML::sanitize($_GET['nID']);
    }

    $status = 0;

    $CLICSHOPPING_Newsletter->db->save('newsletters', ['locked' => $status], ['newsletters_id' => (int)$newsletter_id]);

    $CLICSHOPPING_Newsletter->redirect('Newsletter&page=' . $page . '&nID=' . $newsletter_id);
  }
}
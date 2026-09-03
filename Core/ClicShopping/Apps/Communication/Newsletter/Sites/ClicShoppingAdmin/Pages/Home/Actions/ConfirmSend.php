<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ConfirmSend extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Newsletter = Registry::get('Newsletter');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $this->page->setFile('confirm_send.php');

    $newsletter_id = HTML::sanitize($_GET['nID']);

    $Qcheck = $CLICSHOPPING_Newsletter->db->get('newsletters', 'locked', ['newsletters_id' => (int)$newsletter_id]);

    if ($Qcheck->fetch() !== false) {
      if ($Qcheck->valueInt('locked') < 1) {
        $error = $CLICSHOPPING_Newsletter->getDef('error_remove_unlocked_newsletter');

        $CLICSHOPPING_MessageStack->add($error, 'error');

        $CLICSHOPPING_Newsletter->redirect('Newsletter&page=' . (int)$_GET['page'] . '&nID=' . (int)$_GET['nID']);
      }
    }

    $CLICSHOPPING_Newsletter->loadDefinitions('Sites/ClicShoppingAdmin/Newsletter');
  }
}
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

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Newsletter = Registry::get('Newsletter');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $nID = null;

    if (isset($_GET['nID'])) {
      $nID = HTML::sanitize($_GET['nID']);
    }

    if (isset($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        $Qdelete = $CLICSHOPPING_Newsletter->db->prepare('delete
                                                            from :table_newsletters
                                                            where newsletters_id = :newsletters_id
                                                          ');
        $Qdelete->bindInt(':newsletters_id', $id);
        $Qdelete->execute();
      }
    }

    $CLICSHOPPING_Newsletter->redirect('Newsletter&page=' . $page . '&nID=' . $nID);
  }
}
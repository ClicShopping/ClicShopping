<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\ClicShoppingAdmin\Pages\Home\Actions\Reviews;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Reviews = Registry::get('Reviews');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_POST['selected']) && \is_array($_POST['selected']) && !empty($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        $reviews_id = HTML::sanitize($id);

        $CLICSHOPPING_Reviews->db->delete('reviews', ['reviews_id' => (int)$reviews_id]);

        $CLICSHOPPING_Reviews->db->delete('reviews_description', ['reviews_id' => (int)$reviews_id]);
      }
    }

    $CLICSHOPPING_Hooks->call('Reviews', 'DeleteAll');

    $CLICSHOPPING_Reviews->redirect('Reviews&page=' . $page);
  }
}
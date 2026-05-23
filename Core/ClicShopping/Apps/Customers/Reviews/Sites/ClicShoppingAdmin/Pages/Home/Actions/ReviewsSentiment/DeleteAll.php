<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\ClicShoppingAdmin\Pages\Home\Actions\ReviewsSentiment;

use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Reviews = Registry::get('Reviews');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        $Qid = $CLICSHOPPING_Reviews->db->get('reviews_sentiment', 'id', ['reviews_id' => (int)$id]);

        $CLICSHOPPING_Reviews->db->delete('reviews_sentiment', ['reviews_id' => (int)$id]);
        $CLICSHOPPING_Reviews->db->delete('reviews_sentiment_description', ['id' => (int)$Qid->valueInt('id')]);
      }
    }
    $CLICSHOPPING_Hooks->call('ReviewsSentiment', 'DeleteAll');

    $CLICSHOPPING_Reviews->redirect('Reviews&page=' . $page);
  }
}
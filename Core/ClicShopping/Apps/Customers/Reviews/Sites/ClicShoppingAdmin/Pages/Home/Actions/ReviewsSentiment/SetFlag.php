<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\ClicShoppingAdmin\Pages\Home\Actions\ReviewsSentiment;

use ClicShopping\Apps\Customers\Reviews\Classes\ClicShoppingAdmin\ReviewsAdmin;
use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Reviews = Registry::get('Reviews');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $flag = isset($_GET['flag']) ? (int)$_GET['flag'] : 0;

    if ($id > 0) {
      ReviewsAdmin::getReviewsSentimentApprovedStatus($id, $flag);
    }

    $CLICSHOPPING_Reviews->redirect('ReviewsSentiment&page=' . $page . '&rID=' . $id);
  }
}
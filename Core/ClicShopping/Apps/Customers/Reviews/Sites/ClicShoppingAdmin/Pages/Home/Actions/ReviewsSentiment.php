<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class ReviewsSentiment extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Reviews = Registry::get('Reviews');

    $this->page->setFile('reviews_sentiment.php');
    $this->page->data['action'] = 'ReviewsSentiment';

    $CLICSHOPPING_Reviews->loadDefinitions('Sites/ClicShoppingAdmin/reviews_sentiment');
  }
}
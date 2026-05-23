<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\Shop\Pages\ReviewsInfo\Actions\ReviewsInfo;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Reviews = Registry::get('Reviews');

    if (!isset($_GET['products_id']) && !is_numeric($CLICSHOPPING_ProductsCommon->getId())) {
      CLICSHOPPING::redirect();
    }

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $review_id = HTML::sanitize($_GET['reviews_id']);
      $products_id = $CLICSHOPPING_ProductsCommon->getId();

      $Ocheck = $CLICSHOPPING_Db->prepare('select reviews_id
                                      from :table_reviews
                                      where reviews_id = :reviews_id
                                      and products_id = :products_id
                                      and customers_id = :customer_id
                                    ');
      $Ocheck->bindInt(':reviews_id', $review_id);
      $Ocheck->bindInt(':products_id', $products_id);
      $Ocheck->bindInt(':customer_id', $CLICSHOPPING_Customer->getID());
      $Ocheck->execute();

      if ($Ocheck->rowCount() > 0) {
        $CLICSHOPPING_Reviews->deleteReviews($review_id);
      }

      CLICSHOPPING::redirect(null, 'Products&Description&products_id=' . $products_id);
    }
  }
}
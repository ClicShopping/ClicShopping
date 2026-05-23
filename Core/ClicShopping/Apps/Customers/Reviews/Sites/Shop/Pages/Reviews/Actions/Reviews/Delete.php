<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\Shop\Pages\Reviews\Actions\Reviews;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * @param $products_id
   * @return void
   */
  private static function deleteReviews($products_id): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Reviews = Registry::get('Reviews');

    $review_id = HTML::sanitize($_GET['reviews_id']);

    $Ocheck = $CLICSHOPPING_Db->prepare('select reviews_id
                                          from :table_reviews
                                          where reviews_id = :reviews_id
                                          and products_id = :products_id
                                          and customers_id = :customers_id
                                          ');
    $Ocheck->bindInt(':reviews_id', $review_id);
    $Ocheck->bindInt(':products_id', $products_id);
    $Ocheck->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $Ocheck->execute();

    if ($Ocheck->rowCount() > 0) {
      $CLICSHOPPING_Reviews->delete('reviews', ['customers_id' => (int)$CLICSHOPPING_Customer->getID()]);
      $CLICSHOPPING_Reviews->delete('reviews_description', ['reviews_id' => (int)$review_id]);
    }
  }

  public function execute()
  {
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $products_id = $CLICSHOPPING_ProductsCommon->getId();

    if (!$CLICSHOPPING_ProductsCommon->getId() !== null && !is_numeric($CLICSHOPPING_ProductsCommon->getId())) {
      CLICSHOPPING::redirect();
    }

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      self::deleteReviews($products_id);

      CLICSHOPPING::redirect(null, 'Products&Reviews&products_id=' . $products_id);
    }
  }
}
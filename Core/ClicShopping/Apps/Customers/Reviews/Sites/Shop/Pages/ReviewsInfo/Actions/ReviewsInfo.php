<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\Shop\Pages\ReviewsInfo\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ReviewsInfo extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Reviews = Registry::get('Reviews');

    $reviews_id = $_GET['reviews_id'] ?? null;

    if (!isset($_GET['products_id']) && ($reviews_id === null || $CLICSHOPPING_Reviews->getDataReviews($reviews_id) === false)) {
      CLICSHOPPING::redirect();
    }

    if (isset($_GET['reviews_id'])) {
      $review = $CLICSHOPPING_Reviews->getDataReviews($_GET['reviews_id']);
      $date_added = '';
      $customers_name = '';
      $reviews_rating = '';
      $reviews_text = '';
      $products_name = '';
      $product_price = '';
      $button_small_view_details = '';
      $products_image = '';
      $product_not_sell = '';

      if ($review !== false) {
        // Count this single-review view (the counter was never incremented before).
        $CLICSHOPPING_Reviews->incrementReadCount((int)$_GET['reviews_id']);

        $date_added = $review['date_added'];
        $customers_name = HTML::outputProtected(substr($review['customers_name'], 0, -4)) . ' ...';
        $reviews_rating = $review['reviews_rating'];
        $reviews_text = $review['reviews_text'];
        $products_name = HTML::link(CLICSHOPPING::link(null, 'Products&Description&products_id=' . $CLICSHOPPING_ProductsCommon->getID()) . '" itemprop="url" class="productTitle"', '<span itemprop="name">' . HTML::outputProtected($CLICSHOPPING_ProductsCommon->getProductsName()) . '</span>');

        $product_price = $CLICSHOPPING_ProductsCommon->getCustomersPrice();
        $button_small_view_details = HTML::button(CLICSHOPPING::getDef('button_details'), '', CLICSHOPPING::link(null, 'Products&Description&products_id=' . $CLICSHOPPING_ProductsCommon->getID()), 'info', null, 'sm');

        if (!\is_null($CLICSHOPPING_ProductsCommon->getProductsImage($CLICSHOPPING_ProductsCommon->getID()))) {
          $products_image = '<h1>' . HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $CLICSHOPPING_ProductsCommon->getProductsImage($CLICSHOPPING_ProductsCommon->getID()), $CLICSHOPPING_ProductsCommon->getProductsName($CLICSHOPPING_ProductsCommon->getID()), (int)SMALL_IMAGE_WIDTH, (int)SMALL_IMAGE_HEIGHT, 'hspace="5" vspace="5"') . '</h1>';
        }

        if ($CLICSHOPPING_ProductsCommon->getProductsArchive() === 1 && is_numeric($CLICSHOPPING_ProductsCommon->getId())) {
          $product_price = '';
          $product_not_sell = CLICSHOPPING::getDef('products_not_sell');
        }

        // templates
        $this->page->setFile('reviews_info.php');
        //Content
        $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('product_reviews_info');

        //language
        $CLICSHOPPING_Language->loadDefinitions('product_reviews_info');

        $all_get = CLICSHOPPING::getAllGET([
          'Products',
          'Reviews'
        ]);

        $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title'), CLICSHOPPING::link(null, $all_get));
      }
    }
  }
}
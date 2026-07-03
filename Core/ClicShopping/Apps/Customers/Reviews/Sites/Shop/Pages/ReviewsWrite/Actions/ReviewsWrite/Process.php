<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\Shop\Pages\ReviewsWrite\Actions\ReviewsWrite;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\RateLimiter;
use ClicShopping\OM\Registry;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Reviews = Registry::get('Reviews');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $error = false;

      // Throttle review submissions: one every 60s per session (anti-spam / flood).
      $rate_limiter = new RateLimiter(['reviews_write' => 60]);
      $rate_check = $rate_limiter->check('reviews_write');

      if ($rate_check['allowed'] === false) {
        $CLICSHOPPING_MessageStack->add($rate_check['message'], 'error', 'reviews_write');
        CLICSHOPPING::redirect(null, 'Products&ReviewsWrite&products_id=' . $CLICSHOPPING_ProductsCommon->getID());
      }

      $CLICSHOPPING_Hooks->call('ReviewsWrite', 'PreAction');

      $rating = (int)($_POST['rating'] ?? 0);
      $review = HTML::sanitize($_POST['review'] ?? '');

      if (isset($_POST['customer_agree_privacy'])) {
        $customer_agree_privacy = HTML::sanitize($_POST['customer_agree_privacy']);

        if ($customer_agree_privacy != 'on' && \defined('MODULES_PRODUCTS_REVIEWS_WRITE_CUSTOMER_AGREEMENT_STATUS') && MODULES_PRODUCTS_REVIEWS_WRITE_CUSTOMER_AGREEMENT_STATUS == 'True') {
          $error = true;
          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error'), 'error', 'reviews_write');
        }
      }

      if (\strlen($review) < (int)REVIEW_TEXT_MIN_LENGTH) {
        $error = true;
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('js_review_text', ['min_length' => (int)REVIEW_TEXT_MIN_LENGTH]), 'error');
      }

      if (($rating < 1) || ($rating > 5)) {
        $error = true;
      }

      if ($error === false) {
        $CLICSHOPPING_Reviews->saveEntry();
        $CLICSHOPPING_Reviews->sendEmail();

        // Start the rate-limit window only on a successful submission.
        $rate_limiter->record('reviews_write');

        $CLICSHOPPING_Hooks->call('ReviewsWrite', 'Process');

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('message_customer'), 'success', 'rewiews_write');

        CLICSHOPPING::redirect(null, 'Products&ReviewsWrite&Success&products_id=' . $CLICSHOPPING_ProductsCommon->getID());
      }

      if ($error === true) {
        CLICSHOPPING::redirect(null, 'Products&ReviewsWrite&products_id=' . $CLICSHOPPING_ProductsCommon->getID());
      }
    }
  }
}
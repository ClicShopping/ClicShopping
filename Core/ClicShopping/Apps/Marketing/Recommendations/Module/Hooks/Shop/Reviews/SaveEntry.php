<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Module\Hooks\Shop\Reviews;

use ClicShopping\Apps\Marketing\Recommendations\Classes\Shop\RecommendationsShop;
use ClicShopping\Apps\Marketing\Recommendations\Classes\Shop\ProductsAutomation;
use ClicShopping\Apps\Marketing\Recommendations\Classes\Shop\RatingValidator;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\HooksInterface;

class saveEntry implements HooksInterface
{
  private mixed $productsCommon;
  private mixed $recommendationsShop;
  private string $review;
  /**
   * Constructor method.
   *
   * Initializes the productsCommon property and registers the RecommendationsShop instance
   * in the registry before assigning it to the recommendationsShop property.
   *
   * @return void
   */
  public function __construct()
  {
    $this->review = HTML::sanitize($_POST['review']);

    $this->productsCommon = Registry::get('ProductsCommon');

    Registry::set('RecommendationsShop', new RecommendationsShop());
    $this->recommendationsShop = Registry::get('RecommendationsShop');
  }

  /**
   * Executes the main functionality for saving product recommendations and triggering additional automation processes
   * based on the application's configuration settings.
   *
   * @return bool|void Returns false if the recommendations functionality is not enabled, otherwise no return value.
   */
  public function execute()
  {
    if (!defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_STATUS') || CLICSHOPPING_APP_RECOMMENDATIONS_PR_STATUS == 'False') {
      return false;
    }

    $review = $this->review;

    // Validate and sanitize the rating input securely
    $validatedRating = RatingValidator::validatePostRating($_POST);

    if ($validatedRating === null) {
      // Log the security issue and use default rating
      error_log('[Recommendations Security] Invalid rating provided, using default value');
      $validatedRating = RatingValidator::getDefaultRating();
    }

    $review = HTML::sanitize($review);

    $this->recommendationsShop->saveRecommendations($this->productsCommon->getID(), $validatedRating, $review);

//productsAutomation
    if (defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_FAVORITES_STATUS') || CLICSHOPPING_APP_RECOMMENDATIONS_PR_FAVORITES_STATUS == 'True') {
      ProductsAutomation::favorites();
    }

    if (defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_FEATURED_STATUS') || CLICSHOPPING_APP_RECOMMENDATIONS_PR_FEATURED_STATUS == 'True') {
      ProductsAutomation::featured();
    }
  }
}
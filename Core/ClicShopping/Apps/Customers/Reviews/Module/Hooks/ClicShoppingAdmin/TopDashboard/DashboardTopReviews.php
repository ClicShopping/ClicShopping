<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Module\Hooks\ClicShoppingAdmin\TopDashboard;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Reviews\Reviews as ReviewsApp;

class DashboardTopReviews implements HooksInterface
{
  /**
   * @var bool|null
   */
  public mixed $app;

  /**
   * Initializes the Reviews application module by setting it in the Registry if not already present
   * and loading the necessary definitions for the Reviews module configuration.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Reviews')) {
      Registry::set('Reviews', new ReviewsApp());
    }

    $this->app = Registry::get('Reviews');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/TopDashboard/dashboard_top_reviews');
  }

  /**
   * Generates and returns an HTML block displaying the number of new reviews
   * added in the last two months with a pending status. If no such reviews exist,
   * returns an empty string.
   *
   * @return string The constructed HTML block with review details or an empty string if no reviews are pending.
   */
  public function Display(): string
  {
    $Qreviews = $this->app->db->prepare('select count(reviews_id) as num_reviews 
                                          from :table_reviews 
                                          where date_added >= (now() - INTERVAL 2 month) 
                                          and status = 0
                                          ');
    $Qreviews->execute();

    $number_of_reviews = $Qreviews->valueInt('num_reviews');

    $text = $this->app->getDef('text_number_of_reviews');
    $text_view = $this->app->getDef('text_view');

    $output = '';

    if ($number_of_reviews > 0) {
      $output = '
<div class="col-md-2 col-12 m-1">
    <div class="card bg-success">
      <div class="card-body">
        <div class="row">
          <h6 class="card-title text-white"><i class="bi bi-list"></i> ' . $text . '</h6>
        </div>
        <div class="col-md-12">
          <span class="text-white"><strong>' . $number_of_reviews . '</strong></span>
          <span><small class="text-white">' . HTML::link(CLICSHOPPING::link(null, 'A&Customers\Reviews&Reviews'), $text_view, 'class="text-white"') . '</small></span>
        </div>
      </div>
    </div>
</div>
';
    }

    return $output;
  }
}
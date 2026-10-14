<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Stripe\Module\Hooks\ClicShoppingAdmin\Orders;

use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Payment\Stripe\Stripe as StripeApp;
/**
 * This class implements the hook interface to display additional content
 * in the Order Administration area in the ClicShopping admin panel.
 * Specifically, it adds a Stripe dashboard button tab as a part of the
 * admin interface.
 */
class PageContentTab3 implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Stripe application.
   *
   * Checks if the 'Stripe' instance exists in the Registry. If it does not exist, a new StripeApp instance is created
   * and added to the Registry. The app instance is then fetched from the Registry and assigned to the $app property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Stripe')) {
      Registry::set('Stripe', new StripeApp());
    }

    $this->app = Registry::get('Stripe');
  }

  /**
   * Generates and returns the HTML and JavaScript code for embedding Stripe-related content.
   *
   * This method checks if the Stripe application is active and appends
   * a defined HTML structure and JavaScript to integrate Stripe elements
   * into the order page.
   *
   * @return string|false The generated HTML and JavaScript code as a string,
   *                      or false if the Stripe application status is not defined.
   */
  public function display()
  {

    if (!\defined('CLICSHOPPING_APP_STRIPE_ST_STATUS')) {
      return false;
    }

    $this->app->loadDefinitions('Hooks/ClicShoppingAdmin/Orders/page_content_tab_3');

    $content = '<!-- stripe start -->';
    $content .= '<div class="mt-1"></div>';
    $content .= '<div class="row" id="stripeButton">';
    $content .= '<span class="col-md-2"><a href="https://dashboard.stripe.com" target="_blank" class="btn btn-primary" role="button">' . $this->app->getDef('text_stripe_dashboard') . '</a>';
    $content .= '</div>';
    $content .= '<!-- stripe end -->';

    $output = <<<EOD
<!-- ######################## -->
<!--  Start order Stripe     -->
<!-- ######################## -->
<script>
$('#ErpOrder').prepend(
    '{$content}'
);
</script>
<!-- ######################## -->
<!--  End order Stripe      -->
<!-- ######################## -->
EOD;

    return $output;
  }
}

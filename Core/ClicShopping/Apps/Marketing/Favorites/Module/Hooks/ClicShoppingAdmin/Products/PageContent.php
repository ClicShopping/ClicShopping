<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Favorites\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\HooksInterface;

use ClicShopping\Apps\Marketing\Favorites\Favorites as FavoritesApp;

class PageContent implements HooksInterface
{
  public mixed $app;

  /**
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Favorites')) {
      Registry::set('Favorites', new FavoritesApp());
    }

    $this->app = Registry::get('Favorites');
  }

  /**
   * Generates and returns the HTML and JavaScript content to display the favorites toggle feature in the product page.
   *
   * @return string|bool The generated content as a string if the feature is enabled, otherwise `false`.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_FAVORITES_FA_STATUS') || CLICSHOPPING_APP_FAVORITES_FA_STATUS == 'False') {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/PageContent');

    $content = '<div class="row">';
    $content .= '<div class="col-md-9">';
    $content .= '<div class="form-group row">';
    $content .= '<label for="' . $this->app->getDef('text_products_favorites') . '" class="col-5 col-form-label">' . $this->app->getDef('text_products_favorites') . '</label>';
    $content .= '<div class="col-md-5">';
    $content .= '<ul class="list-group-slider list-group-flush">';
    $content .= '<li class="list-group-item-slider">';
    $content .= '<label class="switch">';
    $content .= HTML::checkboxField('products_favorites', 'yes', false, 'class="success"');
    $content .= '<span class="slider"></span>';
    $content .= '</label>';
    $content .= '</li>';
    $content .= '</ul>';
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';

    $output = <<<EOD
<!-- ######################## -->
<!--  Start FavoritesApp      -->
<!-- ######################## -->
<script>
$('#tab9Content').prepend(
    '{$content}'
);
</script>
<!-- ######################## -->
<!--  End FavoritesApp      -->
<!-- ######################## -->

EOD;
    return $output;
  }
}

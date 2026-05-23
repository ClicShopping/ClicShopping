<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Specials\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\Specials\Specials as SpecialsApp;

class PageContent implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method that initializes the application by checking for the existence of
   * a 'Specials' registry entry. If it does not exist, it sets a new instance of SpecialsApp.
   * The application instance is then retrieved and assigned.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Specials')) {
      Registry::set('Specials', new SpecialsApp());
    }

    $this->app = Registry::get('Specials');
  }

  /**
   * Displays the specials settings interface if certain conditions are met.
   *
   * @return string|false Returns the generated HTML content for the specials settings interface
   *                      if the necessary conditions are met, or false if the application status
   *                      is disabled or required parameters are not set.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_SPECIALS_SP_STATUS') || CLICSHOPPING_APP_SPECIALS_SP_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Products']) && isset($_GET['Update'])) {
      $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/PageContent');

      $content = '<div class="row">';
      $content .= '<div class="col-md-9">';
      $content .= '<div class="form-group row">';
      $content .= '<label for="' . $this->app->getDef('text_products_specials') . '" class="col-5 col-form-label">' . $this->app->getDef('text_products_specials') . '</label>';
      $content .= '<div class="col-md-5">';
      $content .= '<ul class="list-group-slider list-group-flush">';
      $content .= '<li class="list-group-item-slider">';
      $content .= '<label class="switch">';
      $content .= HTML::checkboxField('products_specials', 'yes', false, 'class="success"');
      $content .= '<span class="slider"></span>';
      $content .= '</label>';
      $content .= '</li>';
      $content .= '<span style="padding-top:0.5rem">' . HTML::inputField('percentage_products_specials', '', 'placeholder="' . $this->app->getDef('text_products_specials_percentage') . ' "class="form-control"') . '</span>';
      $content .= '</ul>';
      $content .= '</div>';
      $content .= '</div>';
      $content .= '</div>';
      $content .= '</div>';
    } else {
      $content = '';
    }

    $output = <<<EOD
<!-- ######################## -->
<!--  Start SpecialsApp      -->
<!-- ######################## -->
<script>
$('#tab9Content').prepend(
    '{$content}'
);
</script>
<!-- ######################## -->
<!--  End SpecialsApp      -->
<!-- ######################## -->
EOD;
    return $output;
  }
}

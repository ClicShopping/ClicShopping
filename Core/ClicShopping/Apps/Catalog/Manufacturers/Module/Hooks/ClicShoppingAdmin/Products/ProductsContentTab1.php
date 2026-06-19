<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Manufacturers\Classes\ClicShoppingAdmin\ManufacturerAdmin;
use ClicShopping\Apps\Catalog\Manufacturers\Manufacturers as ManufacturersApp;

use function count;
use function is_array;

class ProductsContentTab1 implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Manufacturers application module.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Manufacturers')) {
      Registry::set('Manufacturers', new ManufacturersApp());
    }

    $this->app = Registry::get('Manufacturers');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/page_content_tab_1');
  }

  /**
   * Generates and returns the HTML content and JavaScript required for a manufacturer autocomplete and modal dialog.
   * This includes the form elements for selecting a manufacturer and associated modal functionality.
   *
   * @return string The HTML and JavaScript output for the manufacturer autocomplete and modal dialog.
   */
  public function display(): string
  {
    if (!\defined('CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS') || CLICSHOPPING_APP_MANUFACTURERS_CM_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['pID'])) {
      $pId = HTML::sanitize($_GET['pID']);
    } else {
      $pId = null;
    }

    $manufacturer_array = ManufacturerAdmin::getManufacturerName($pId);

    if (is_array($manufacturer_array) && count($manufacturer_array) > 0) {
      $manufacturers_name = $manufacturer_array[0]['manufacturers_name'];
    } else {
      $manufacturers_name = '';
    }

    $content = '<!-- Link trigger modal -->';
    $content .= '<div class="col-md-5">';
    $content .= '<div class="form-group row">';
    $content .= '<label for="' . $this->app->getDef('text_products_manufacturer') . '" class="col-5 col-form-label">' . $this->app->getDef('text_products_manufacturer') . '</label>';
    $content .= '<div class="col-md-5">';
    $content .= HTML::inputField('manufacturers_name', $manufacturers_name, 'id="ajax_manufacturers_name" list="manufacturer_list" class="form-control"');
    $content .= '<datalist id="manufacturer_list"></datalist>';
    $content .= '<a href="' . $this->app->link('ManufacturersPopUp') . '"  data-bs-toggle="modal" data-refresh="true" data-bs-target="#myModal"><h4><i class="bi bi-plus-circle" title="' . $this->app->getDef('icon_create') . '"></i></h4></a>';
    $content .= '<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">';
    $content .= '<div class="modal-dialog">';
    $content .= '<div class="modal-content">';
    $content .= '<div class="modal-body"><div class="te"></div></div>';
    $content .= '</div> <!-- /.modal-content -->';
    $content .= '</div><!-- /.modal-dialog -->';
    $content .= '</div><!-- /.modal -->';
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';

    $smanufacturers_ajax =  $this->app->link('ProductsAjax');
    $smanufacturers_ajax = HTML::AjaxAppLink($smanufacturers_ajax);

    $output = <<<EOD
<!-- ######################## -->
<!--  Start Manufacturer Hooks      -->
<!-- ######################## -->
<script>
$('#tab1ContentRow2').append(
    '{$content}'
);
</script>

<script>
window.addEventListener("load", function(){
  // Add a keyup event listener to our input element
  document.getElementById('ajax_manufacturers_name').addEventListener("keyup", function(event){hinterManufacturer(event)});
  // create one global XHR object
  // so we can abort old requests when a new one is make
  window.hinterManufacturerXHR = new XMLHttpRequest();
});

// Autocomplete for form
function hinterManufacturer(event) {
  var input = event.target;

  var ajax_manufacturers_name = document.getElementById('manufacturer_list'); //datalist id
  
  // minimum number of characters before we start to generate suggestions
  var min_characters = 0;

  if (!isNaN(input.value) || input.value.length < min_characters ) {
    return;
  } else {
    window.hinterManufacturerXHR.abort();
    window.hinterManufacturerXHR.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        var response = JSON.parse( this.responseText );
        
        ajax_manufacturers_name.innerHTML = "";
          response.forEach(function(item) {
// Create a new <option> element.
            var option = document.createElement('option');
            option.value = item.name;//get name
            option.hidden = item.id; //get id

            ajax_manufacturers_name.appendChild(option);
          });
      }
    };

    window.hinterManufacturerXHR.open("GET", "{$smanufacturers_ajax}" + "&q=" + input.value, true);

    window.hinterManufacturerXHR.send()
  }
}
</script>
<!-- ######################## -->
<!--  End Manufacturer App      -->
<!-- ######################## -->
EOD;

    return $output;
  }
}
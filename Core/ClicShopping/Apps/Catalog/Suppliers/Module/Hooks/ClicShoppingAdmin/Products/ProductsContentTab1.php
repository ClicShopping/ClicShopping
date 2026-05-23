<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Suppliers\Classes\ClicShoppingAdmin\SupplierAdmin;
use ClicShopping\Apps\Catalog\Suppliers\Suppliers as SuppliersApp;

use function count;
use function is_array;

class ProductsContentTab1 implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  protected mixed $SupplierAdmin;

  /**
   * Constructor method for initializing the Suppliers and SupplierAdmin components.
   * Loads necessary definitions for page content management.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Suppliers')) {
      Registry::set('Suppliers', new SuppliersApp());
    }

    $this->app = Registry::get('Suppliers');

    if (!Registry::exists('SupplierAdmin')) {
      Registry::set('SupplierAdmin', new SupplierAdmin());
    }

    $this->SupplierAdmin = Registry::get('SupplierAdmin');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/page_content_tab_1');
  }

  /**
   * Generates and returns the HTML and JavaScript necessary to display a suppliers input field with autocomplete functionality and a modal popup for adding suppliers.
   * The method checks if the suppliers module is enabled, retrieves supplier data, and constructs the appropriate UI elements.
   *
   * @return string The generated HTML and JavaScript content to render the suppliers interface.
   */
  public function display(): string
  {
    if (!\defined('CLICSHOPPING_APP_SUPPLIERS_CS_STATUS') || CLICSHOPPING_APP_SUPPLIERS_CS_STATUS == 'False') {
      return false;
    }

    $suppliers_array = $this->SupplierAdmin->getSupplier();

    if (is_array($suppliers_array) && count($suppliers_array) > 0) {
      $suppliers_name = $suppliers_array[0]['suppliers_name'];
    } else {
      $suppliers_name = null;
    }

    $content = '<!-- Link trigger modal -->';
    $content .= '<div class="col-md-5">';
    $content .= '<div class="form-group row">';
    $content .= '<label for="' . $this->app->getDef('text_products_suppliers') . '" class="col-5 col-form-label">' . $this->app->getDef('text_products_suppliers') . '</label>';
    $content .= '<div class="col-md-5">';
    $content .= HTML::inputField('suppliers_name', $suppliers_name, 'id="ajax_suppliers_name" list="supplier_list" class="form-control"');
    $content .= '<datalist id="supplier_list"></datalist>';
    $content .= '<a href="' . $this->app->link('SuppliersPopUp') . '" data-bs-toggle="modal" data-refresh="true" data-bs-target="#myModal"><h4><i class="bi bi-plus-circle" title="' . $this->app->getDef('icon_create') . '"></i></h4></a>';
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

    $suppliers_ajax =  $this->app->link('ProductsAjax');
    $suppliers_ajax = HTML::AjaxAppLink($suppliers_ajax);

    $output = <<<EOD
<!-- ######################## -->
<!--  Start Supplier Hooks      -->
<!-- ######################## -->
<script>
$('#tab1ContentRow2').append(
    '{$content}'
);
</script>

<script>
window.addEventListener("load", function(){
  // Add a keyup event listener to our input element
	document.getElementById('ajax_suppliers_name').addEventListener("keyup", function(event){hinterSupplier(event)});
  // create one global XHR object
  // so we can abort old requests when a new one is make
	window.hinterSupplierXHR = new XMLHttpRequest();
});

// Autocomplete for form
function hinterSupplier(event) {
  var input = event.target;

  var ajax_suppliers_name = document.getElementById('supplier_list'); //datalist id
  
  // minimum number of characters before we start to generate suggestions
  var min_characters = 0;

  if (!isNaN(input.value) || input.value.length < min_characters ) {
    return;
  } else {
    window.hinterSupplierXHR.abort();
    window.hinterSupplierXHR.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        var response = JSON.parse( this.responseText );
        
        ajax_suppliers_name.innerHTML = "";
          response.forEach(function(item) {
// Create a new <option> element.
            var option = document.createElement('option');
            option.value = item.name;//get name
            option.hidden = item.id; //get id

            ajax_suppliers_name.appendChild(option);
          });
      }
    };

     window.hinterSupplierXHR.open("GET", "{$suppliers_ajax}" + "&q=" + input.value, true);
     
     window.hinterSupplierXHR.send()
  }
}
</script>
<!-- ######################## -->
<!--  End Supplier App        -->
<!-- ######################## -->
EOD;

    return  $output;
  }
}
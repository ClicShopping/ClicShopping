<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\Apps\Catalog\Categories\Categories as CategoriesApp;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * Class responsible for managing and displaying the category selection UI for products.
 */
class ProductsContentTab1 implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method.
   *
   * Initializes the Categories application if it does not exist in the Registry.
   * Loads the necessary definitions for the Categories application.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Categories')) {
      Registry::set('Categories', new CategoriesApp());
    }

    $this->app = Registry::get('Categories');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/page_content_tab_1');
  }

  /**
   * Displays and generates the category selection UI for managing product categories.
   *
   * This method dynamically constructs HTML content and JavaScript scripts to display
   * a category selection dropdown integrated with AJAX functionality. It handles
   * different scenarios such as detecting the current category or associated category
   * for a product, and manages user input for defining how products will be categorized
   * (e.g., link, move, duplicate, etc.).
   *
   * @return string Returns the generated HTML and JavaScript content for rendering
   *                the category management interface, or false if the categories
   *                application is disabled.
   */
  public function display()
  {
    $CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');

    if (!\defined('CLICSHOPPING_APP_CATEGORIES_CT_STATUS') || CLICSHOPPING_APP_CATEGORIES_CT_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['cPath']) && !empty($_GET['cPath'])) {
      $current_category_id = HTML::sanitize($_GET['cPath']);
    } else {
      if (isset($_GET['pID'])) {
        $QproductsCategories = $this->app->db->prepare('select categories_id
                                                          from :table_products_to_categories
                                                          where products_id = :products_id
                                                          limit 1
                                                        ');
        $QproductsCategories->bindInt('products_id', $_GET['pID']);
        $QproductsCategories->execute();

        $current_category_id = $QproductsCategories->valueInt('categories_id');
      } else {
        $current_category_id = 0;
      }
    }

    if (empty($current_category_id)) {
      $current_category_id = 0;
    }

    $category_tree = $CLICSHOPPING_CategoriesAdmin->getCategoryTree();

    $content = '<!-- Categories -->';
    $content .= '<div class="form-group row">';
    $content .= '<div class="col-md-2">' . $this->app->getDef('text_categories_name') . '</div>';

    if (isset($_GET['Insert']) && isset($_GET['Products'])) {
      $content .= '<div class="col-md-5">';
      $content .= '<label for="' . $this->app->getDef('text_products_categories') . '" class="col-5 col-form-label"></label>';
      $content .= '<div id="myCategoriesAjax">';
      $content .= HTML::selectMenu('move_to_category_id[]', $category_tree, $current_category_id, 'id="move_to_category_id"');
      $content .= '</div>';
      $content .= HTML::hiddenField('current_category_id', $current_category_id);
      $content .= '<a href="' . $this->app->link('CategoriesPopUp') . '"  data-bs-toggle="modal" data-bs-refresh="true" data-bs-target="#myModal"><h4><i class="bi bi--plus-circle" title="' . $this->app->getDef('text_create') . '"></i></h4></a>';
      $content .= '<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">';
      $content .= '<div class="modal-dialog">';
      $content .= '<div class="modal-content">';
      $content .= '<div class="modal-body"><div class="te"></div></div>';
      $content .= '</div> <!-- /.modal-content -->';
      $content .= '</div><!-- /.modal-dialog -->';
      $content .= '</div><!-- /.modal -->';
      $content .= '</div>';
      $content .= '</div>';
    } else {
      $content .= '<div class="col-md-5">';
      $content .= HTML::selectMenu('move_to_category_id[]', $category_tree, $current_category_id, 'multiple="multiple" size="10"');
      $content .= HTML::hiddenField('current_category_id', $current_category_id);
      $content .= '</div>';
      $content .= '<div class="col-md-5">';
      $content .= $this->app->getDef('text_select_category_action') . ' <br />';
      $content .= '<div class="custom-control custom-radio">';
      $content .= HTML::radioField('copy_as', 'none', true, 'class="custom-control-input" id="copy_as_none" name="copy_categories"');
      $content .= '<label class="custom-control-label" for="copy_as_none">' . $this->app->getDef('text_copy_as_none') . '</label>';
      $content .= '</div>';
      $content .= '<div class="custom-control custom-radio">';
      $content .= HTML::radioField('copy_as', 'link', null, 'class="custom-control-input" id="copy_as_link" name="copy_categories"');
      $content .= '<label class="custom-control-label" for="copy_as_link">' . $this->app->getDef('text_copy_as_link') . '</label>';
      $content .= '</div>';
      $content .= '<div class="custom-control custom-radio">';
      $content .= HTML::radioField('copy_as', 'duplicate', null, 'class="custom-control-input" id="copy_as_duplicate" name="copy_categories"');
      $content .= '<label class="custom-control-label" for="copy_as_duplicate">' . $this->app->getDef('text_copy_as_duplicate') . '</label>';
      $content .= '</div>';
      $content .= '<div class="custom-control custom-radio">';
      $content .= HTML::radioField('copy_as', 'move', null, 'class="custom-control-input" id="copy_as_move" name="copy_categories"');
      $content .= '<label class="custom-control-label" for="copy_as_move">' . $this->app->getDef('text_copy_as_move') . '</label>';
      $content .= '</div>';
      $content .= '</div>';
    }

    $content .= '</div>';
    $content .= '<!-- End Categories -->';

   $categories_ajax =  $this->app->link('ProductsCategoriesAjax');
   $categories_ajax = HTML::AjaxAppLink($categories_ajax);

    $output = <<<EOD
<!-- ######################## -->
<!--  Start Categories Hooks      -->
<!-- ######################## -->
<script>
$('#tab1ContentRow1').append(
    '{$content}'
);
</script>
<script>
  $(document).on('click', '#myCategoriesAjax', function(e) {
    const selectedOptionVal = document.querySelector('#move_to_category_id').value;
    let options_html = "";
    fetch("{$categories_ajax}&" + selectedOptionVal)
      .then(response => response.json())
      .then(jsonResponse => {
        options_html = "";
        for (const index in jsonResponse) {
          const category_id = jsonResponse[index].id;
          const category_name = jsonResponse[index].text;
          const selectedString = category_id == selectedOptionVal ? ' selected="selected"' : '';
          options_html += '<option value="' + category_id + '"' + selectedString + '>' + category_name + '</option>';
        }
        $('#move_to_category_id').html(options_html);
      })
      .catch(err => {
        console.log("error :", err);
      });
  });
</script>

<!-- ######################## -->
<!--  End Categories App      -->
<!-- ######################## -->
EOD;

    return $output;
  }
}

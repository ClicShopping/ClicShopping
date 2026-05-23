<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

$CLICSHOPPING_Categories = Registry::get('Categories');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');
$CLICSHOPPING_Language = Registry::get('Language');

$supplier_inputs_string = '';
$languages = $CLICSHOPPING_Language->getLanguages();

echo HTML::form('ajaxform', $CLICSHOPPING_Categories->link('CategoriesPopUp&Save'), 'post', 'id="ajaxform"');

if (isset($_GET['cPath'])) {
  $current_category_id = HTML::sanitize($_GET['cPath']);
} else {
  $current_category_id = 0;
}
?>


<div class="row">
  <div class="col-md-12">
    <div class="card card-block headerCard">
      <div class="row">
        <span
          class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_Categories->getDef('heading_title_categories'), '40', '40'); ?></span>
        <span
          class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Categories->getDef('heading_title'); ?></span>
        <span class="col-md-4 text-end">
          <div><?php echo HTML::button($CLICSHOPPING_Categories->getDef('button_insert'), null, null, 'success', null, 'md', null, 'simple-post'); ?></div>
          <div id="simple-msg"></div>
         </span>
      </div>
    </div>
  </div>
</div>
<div class="mt-1"></div>
<div>
  <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
    <li
      class="nav-item"><?php echo '<a href="#categoriesPopUp" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Categories->getDef('tab_general') . '</a>'; ?></li>
  </ul>
  <div class="tabsClicShopping">
    <div class="tab-content">
      <?php
      // -- ------------------------------------------------------------ //
      // --          ONGLET Information General du fabricant           //
      // -- ------------------------------------------------------------ //
      ?>
      <div class="tab-pane active" id="tab20">
        <div class="col-md-12 mainTitle">
          <div class="float-start"><?php echo $CLICSHOPPING_Categories->getDef('text_categories_name'); ?></div>
          <div
            class="float-end"><?php echo $CLICSHOPPING_Categories->getDef('text_user_name') . ' ' . AdministratorAdmin::getUserAdmin(); ?></div>
        </div>
        <div class="adminformTitle">
          <div class="col-md-12">
            <div class="form-group row">
              <?php
              for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                ?>
                <div class="row">
                  <div class="col-md-12">
                    <div class="form-group row">
                      <label for="code"
                             class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                      <div class="col-md-9">
                        <?php echo HTML::inputField('categories_name[' . $languages[$i]['id'] . ']', null, 'class="form-control" required aria-required="true" Authentificator id="categories_name" placeholder="' . $CLICSHOPPING_Categories->getDef('text_edit_categories_name') . '"', true) . '&nbsp;'; ?>
                      </div>
                    </div>
                  </div>
                </div>
                <?php
              }
              ?>
            </div>
          </div>
          <div class="row">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Categories->getDef('text_categories_name'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Categories->getDef('text_categories_name'); ?></label>
                <div class="col-md-9">
                  <?php echo HTML::selectMenu('select_category_id', $CLICSHOPPING_CategoriesAdmin->getCategoryTree(), $current_category_id) . HTML::hiddenField('current_category_id', $current_category_id); ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</form>

<script
  src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/bootstrap/ajax_form/bootstrap_ajax_form_fields_configuration.js'); ?>"></script>

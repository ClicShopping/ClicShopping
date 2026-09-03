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
use ClicShopping\Sites\ClicShoppingAdmin\ManufacturersAdmin;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Manufacturers = Registry::get('Manufacturers');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

$supplier_inputs_string = '';
$languages = $CLICSHOPPING_Language->getLanguages();

echo HTML::form('ajaxform', $CLICSHOPPING_Manufacturers->link('ManufacturersPopUp&Save'), 'post', 'id="ajaxform"');
?>

<div class="row">
  <div class="col-md-12">
    <div class="card card-block headerCard">
      <div class="row">
        <span
          class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/manufacturers.gif', $CLICSHOPPING_Manufacturers->getDef('heading_title'), '40', '40'); ?></span>
        <span
          class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Manufacturers->getDef('heading_title'); ?></span>
        <span class="col-md-4 text-end">
           <div><?php echo HTML::button($CLICSHOPPING_Manufacturers->getDef('button_insert'), null, null, 'success', null, 'md', null, 'simple-post'); ?></div>
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
      class="nav-item"><?php echo '<a href="#tab20" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Manufacturers->getDef('tab_general') . '</a>'; ?></li>
    <li
      class="nav-item"><?php echo '<a href="#tab21" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Manufacturers->getDef('tab_visuel'); ?></a></li>
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
          <div
            class="float-start"><?php echo $CLICSHOPPING_Manufacturers->getDef('title_manufacturer_general'); ?></div>
        </div>
        <div class="adminformTitle">

          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturers_name'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturers_name'); ?></label>
              <div class="col-md-5">
                <?php echo HTML::inputField('manufacturers_name', null, 'required aria-required="true" id="manufacturers_name"'); ?>
              </div>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturers_url'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturers_url'); ?></label>
            </div>
          </div>

          <?php
          for ($i = 0, $n = \count($languages); $i < $n; $i++) {
            ?>
            <div class="col-md-12">
              <div class="form-group row">
                <label for="lang"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('manufacturers_url[' . $languages[$i]['id'] . ']'); ?>
                </div>
              </div>
            </div>
            <?php
          }
          ?>

        </div>
      </div>
      <!-- ------------------------------------------------------------ //-->
      <!--          ONGLET Information visuelle          //-->
      <!-- ------------------------------------------------------------ //-->
      <div class="tab-pane" id="tab21">
        <div class="col-md-12 mainTitle">
          <div
            class="float-start"><?php echo $CLICSHOPPING_Manufacturers->getDef('title_manufacturer_image'); ?></div>
        </div>
        <div class="adminformTitle">
          <div class="row">
            <div class="col-md-12">
              <div class="row">
                <div class="col-md-12">
                  <span
                    class="col-md-3"><?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturers_new_image'); ?></span>
                  <span
                    class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'images_product.gif', $CLICSHOPPING_Manufacturers->getDef('text_products_image_vignette'), '40', '40'); ?></span>
                  <span
                    class="col-md-4"><?php echo $CLICSHOPPING_Manufacturers->getDef('text_products_image_vignette') . '<br /><br />' . $CLICSHOPPING_Wysiwyg::fileFieldImageCkEditor('manufacturers_image', null, '212', '212'); ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- ------------------------------------------------------------ //-->
      <!--          ONGLET Information seo                              //-->
      <!-- ------------------------------------------------------------ //-->
    </div>
  </div>
</div>
</form>

<script
  src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/bootstrap/ajax_form/bootstrap_ajax_form_fields_configuration.js'); ?>"></script>

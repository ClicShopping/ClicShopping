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

use ClicShopping\Apps\Catalog\Suppliers\Classes\ClicShoppingAdmin\SupplierAdmin;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Address = Registry::get('Address');
$CLICSHOPPING_Suppliers = Registry::get('Suppliers');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

Registry::set('SupplierAdmin', new SupplierAdmin());
$CLICSHOPPING_SupplierAdmin = Registry::get('SupplierAdmin');

$supplier_inputs_string = '';
$languages = $CLICSHOPPING_Language->getLanguages();

echo HTML::form('ajaxform', $CLICSHOPPING_Suppliers->link('SuppliersPopUp&Save'), 'post', 'id="ajaxform"');
?>
<div class="row">
  <div class="col-md-12">
    <div class="card card-block headerCard">
      <div class="row">
        <span
          class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/suppliers.gif', $CLICSHOPPING_Suppliers->getDef('heading_title'), '40', '40'); ?></span>
        <span class="col-md-7 pageHeading"><?php echo $CLICSHOPPING_Suppliers->getDef('heading_title'); ?></span>
        <span class="col-md-4 text-end">
                   <div><?php echo HTML::button($CLICSHOPPING_Suppliers->getDef('button_insert'), null, null, 'success', null, 'md', null, 'simple-post'); ?></div>
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
      class="nav-item"><?php echo '<a href="#tab30" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Suppliers->getDef('tab_general') . '</a>'; ?></li>
    <li
      class="nav-item"><?php echo '<a href="#tab31" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Suppliers->getDef('tab_suppliers_address'); ?></a></li>
    <li
      class="nav-item"><?php echo '<a href="#tab32" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Suppliers->getDef('tab_suppliers_note'); ?></a></li>
    <li
      class="nav-item"><?php echo '<a href="#tab33" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Suppliers->getDef('tab_visuel'); ?></a></li>
  </ul>
  <div class="tabsClicShopping">
    <div class="tab-content">
      <?php
      // -- ------------------------------------------------------------ //
      // --          ONGLET Information General du fournisseur          //
      // -- ------------------------------------------------------------ //
      ?>
      <div class="tab-pane active" id="tab30">
        <div class="col-md-12 mainTitle">
          <div class="float-start"><?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_general'); ?></div>
        </div>
        <div class="adminformTitle">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_name'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_name'); ?></label>
              <div class="col-md-5">
                <?php echo HTML::inputField('suppliers_name', null, 'required aria-required="true" id="supliers_name"'); ?>
              </div>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_manager'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_manager'); ?></label>
              <div class="col-md-5">
                <?php echo HTML::inputField('suppliers_manager', null); ?>
              </div>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_phone'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_phone'); ?></label>
              <div class="col-md-5">
                <?php echo HTML::inputField('suppliers_phone', null); ?>
              </div>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_fax'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_fax'); ?></label>
              <div class="col-md-5">
                <?php echo HTML::inputField('suppliers_fax', null); ?>
              </div>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_email_address'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_email_address'); ?></label>
              <div class="col-md-5">
                <?php echo HTML::inputField('suppliers_email_address', null); ?>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- ------------------------------------------------------------ //-->
      <!--          ONGLET Information note complementaire          //-->
      <!-- ------------------------------------------------------------ //-->
      <div class="tab-pane" id="tab31">
        <div class="col-md-12 mainTitle">
          <div class="float-start"><?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_address'); ?></div>
        </div>
        <div class="adminformTitle">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_address'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_address'); ?></label>
              <div class="col-md-5">
                <?php echo HTML::inputField('suppliers_address', null, null, 'email'); ?>
              </div>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_suburb'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_suburb'); ?></label>
              <div class="col-md-5">
                <?php echo HTML::inputField('suppliers_suburb', null); ?>
              </div>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_postcode'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_postcode'); ?></label>
              <div class="col-md-5">
                <?php echo HTML::inputField('suppliers_postcode', null); ?>
              </div>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_city'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_city'); ?></label>
              <div class="col-md-5">
                <?php echo HTML::inputField('suppliers_city', null); ?>
              </div>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_country'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_country'); ?></label>
              <div class="col-md-5">
                <?php echo HTML::selectMenuCountryList('suppliers_country_id'); ?>
              </div>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_states'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_states'); ?></label>
              <div class="col-md-5">
                <?php echo HTML::inputField('suppliers_states'); ?>
              </div>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_url'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_url'); ?></label>
            </div>
          </div>

          <?php
          for ($i = 0, $n = \count($languages); $i < $n; $i++) {
            ?>
            <div class="col-md-12">
              <div class="form-group row">
                <label for="lang>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('suppliers_url[' . $languages[$i]['id'] . ']', $CLICSHOPPING_SupplierAdmin->GetSupplierUrl(null, $languages[$i]['id'])) ?>
                </div>
              </div>
            </div>

            <?php
          }
          ?>
        </div>
      </div>

      <!-- ------------------------------------------------------------ //-->
      <!--          ONGLET Information note complementaire          //-->
      <!-- ------------------------------------------------------------ //-->
      <div class="tab-pane" id="tab32">
        <div class="col-md-12 mainTitle">
          <div class="float-start"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_general'); ?></div>
        </div>

        <div class="adminformTitle">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_notes'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_notes'); ?></label>
            </div>
          </div>
          <div class="col-md-12">
            <div class="form-group row">
              <div class="col-md-12">
                <?php echo HTML::textAreaField('suppliers_notes', null, 45, 20); ?>
              </div>
            </div>
          </div>

        </div>
      </div>
      <!-- ------------------------------------------------------------ //-->
      <!--          ONGLET Information visuelle          //-->
      <!-- ------------------------------------------------------------ //-->
      <div class="tab-pane" id="tab33">
        <div class="col-md-12 mainTitle">
          <div class="float-start"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_image'); ?></div>
        </div>
        <div class="adminformTitle">
          <div>&nbsp;</div>
          <div class="row">
            <div class="col-md-12">
              <div class="row">
                <div class="col-md-12">
                  <span
                    class="col-md-3"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_new_image'); ?></span>
                  <span
                    class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'images_product.gif', $CLICSHOPPING_Suppliers->getDef('text_products_image_vignette'), '40', '40'); ?></span>
                  <span
                    class="col-md-4"><?php echo $CLICSHOPPING_Suppliers->getDef('text_products_image_vignette') . '<br /><br />' . $CLICSHOPPING_Wysiwyg::fileFieldImageCkEditor('suppliers_image', null, '212', '212'); ?></span>
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

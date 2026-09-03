<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Suppliers\Classes\ClicShoppingAdmin\SupplierAdmin;

$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Address = Registry::get('Address');
$CLICSHOPPING_Suppliers = Registry::get('Suppliers');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$form_action = 'Insert';
$variable = '';

if ((isset($_GET['Edit']) && isset($_GET['mID']) && !empty($_GET['mID']))) {
  $form_action = 'Update';
  $variable = '&mID=' . HTML::sanitize($_GET['mID']);
}

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

echo $CLICSHOPPING_Wysiwyg::getWysiwyg();

Registry::set('SupplierAdmin', new SupplierAdmin());
$CLICSHOPPING_SupplierAdmin = Registry::get('SupplierAdmin');
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/suppliers.gif', $CLICSHOPPING_Suppliers->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Suppliers->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
            <?php
            echo HTML::form('suppliers', $CLICSHOPPING_Suppliers->link('Suppliers&' . $form_action . $variable));
            if ($form_action == 'Update') echo HTML::hiddenField('suppliers_id', HTML::sanitize($_GET['mID']));

            echo HTML::button($CLICSHOPPING_Suppliers->getDef('button_cancel'), null, $CLICSHOPPING_Suppliers->link('Suppliers&page=' . $page . $variable), 'warning') . '&nbsp;';
            echo(($form_action == 'Insert') ? HTML::button($CLICSHOPPING_Suppliers->getDef('button_insert'), null, null, 'success') : HTML::button($CLICSHOPPING_Suppliers->getDef('button_update'), null, null, 'success'));
            ?>
            </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  if (isset($_GET['Edit']) && isset($_GET['mID']) && !empty($_GET['mID'])) {
    $Qsuppliers = $CLICSHOPPING_Suppliers->db->prepare('select *
                                                        from :table_suppliers
                                                        where suppliers_id = :suppliers_id
                                                      ');
    $Qsuppliers->bindInt(':suppliers_id', (int)$_GET['mID']);
    $Qsuppliers->execute();

    $suppliers = $Qsuppliers->fetch();

    $mInfo = new ObjectInfo($Qsuppliers->toArray());
  } else {
    $mInfo = new ObjectInfo([]);
  }
  ?>
  <div id="suppliersTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Suppliers->getDef('tab_general') . '</a>'; ?></li>
      <li
        class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Suppliers->getDef('tab_suppliers_note'); ?></a></li>
      <li
        class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Suppliers->getDef('tab_visuel'); ?></a></li>
    </ul>

    <div class="tabsClicShopping">
      <div class="tab-content">
        <?php
        // -- ------------------------------------------------------------ //
        // --          ONGLET Information Général de la Marque          //
        // -- ------------------------------------------------------------ //
        ?>
        <div class="tab-pane active" id="tab1">
          <div class="col-md-12 mainTitle">
            <div class="float-start"><?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_general'); ?></div>
          </div>
          <div class="adminformTitle">

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_name'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_name'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('suppliers_name', $mInfo->suppliers_name ?? null, 'required aria-required="true" id="suppliers_name"'); ?>
                  </div>
                </div>
              </div>
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_manager'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_manager'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('suppliers_manager', $mInfo->suppliers_manager ?? null); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_phone'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_phone'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('suppliers_phone', $mInfo->suppliers_phone ?? null); ?>
                  </div>
                </div>
              </div>
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_fax'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_fax'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('suppliers_fax', $mInfo->suppliers_fax ?? null); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_email_address'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_email_address'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('suppliers_email_address', $mInfo->suppliers_email_address ?? null); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_address'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_address'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('suppliers_address', $mInfo->suppliers_address ?? null); ?>
                  </div>
                </div>
              </div>
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_suburb'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_suburb'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('suppliers_suburb', $mInfo->suppliers_suburb ?? null); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_postcode'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_postcode'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('suppliers_postcode', $mInfo->suppliers_postcode ?? null); ?>
                  </div>
                </div>
              </div>
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_city'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_city'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('text_suppliers_city', $mInfo->suppliers_city ?? null); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_country'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_country'); ?></label>
                  <div
                    class="col-md-5"><?php echo HTML::selectMenuCountryList('suppliers_country_id', $mInfo->suppliers_country_id ?? null); ?></div>
                </div>
              </div>
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_states'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_states'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('suppliers_states', $mInfo->suppliers_states ?? null); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="spaceRow"></div>
            <div class="row">
              <div class="col-md-12">
                <span
                  class="col-md-2 centerInputFields"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_url'); ?></span>
              </div>
            </div>

            <?php
            $languages = $CLICSHOPPING_Language->getLanguages();

            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              ?>
              <div class="form-group row">
                <label for="code"
                       class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('suppliers_url[' . $languages[$i]['id'] . ']', $CLICSHOPPING_SupplierAdmin->getSupplierUrl($mInfo->suppliers_id ?? null, $languages[$i]['id'])) ?>
                </div>
              </div>
              <?php
            }
            ?>
          </div>
        </div>
        <!-- //################################################################################################################ -->
        <!--          ONGLET Information note complementaire          //-->
        <!-- //################################################################################################################ -->
        <div class="tab-pane" id="tab2">
          <div class="col-md-12 mainTitle">
            <div class="float-start"><?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_general'); ?></div>
          </div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_notes'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_notes'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::textAreaField('suppliers_notes', $mInfo->suppliers_notes ?? '', 70, 10); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- //################################################################################################################ -->
        <!--          ONGLET Information visuelle          //-->
        <!-- //################################################################################################################ -->
        <div class="tab-pane" id="tab3">
          <div class="mainTitle"><?php echo $CLICSHOPPING_Suppliers->getDef('title_suppliers_image'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-12">
                <span
                  class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'images_product.gif', $CLICSHOPPING_Suppliers->getDef('text_products_image_vignette'), '40', '40'); ?></span>
                <span
                  class="col-md-3 main"><?php echo $CLICSHOPPING_Suppliers->getDef('text_products_image_vignette'); ?></span>
                <span
                  class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'images_product.gif', $CLICSHOPPING_Suppliers->getDef('text_products_image_visuel'), '40', '40'); ?></span>
                <span
                  class="col-md-7 main"><?php echo $CLICSHOPPING_Suppliers->getDef('text_products_image_visuel'); ?></span>
              </div>
              <div class="col-md-12">
                <div class="adminformAide">
                  <div class="row">
                    <span
                      class="col-md-4 text-center float-start"><?php echo $CLICSHOPPING_Wysiwyg::fileFieldImageCkEditor('suppliers_image', '212', '212', null); ?></span>
                    <span class="col-md-8 text-center float-end">
                        <div class="col-md-12">
                          <?php echo $CLICSHOPPING_ProductsAdmin->getInfoImage($mInfo->suppliers_image ?? '', $CLICSHOPPING_Suppliers->getDef('text_products_image_vignette')); ?>
                         </div>
                        <div class="col-md-12 text-end">
                          <?php echo $CLICSHOPPING_Suppliers->getDef('text_suppliers_delete_image') . HTML::checkboxField('delete_image', 'yes', false); ?>
                        </div>
                      </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Suppliers->getDef('title_help_image') . '"></i></h4> ' . $CLICSHOPPING_Suppliers->getDef('title_help_image') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_Suppliers->getDef('help_image_suppliers'); ?></div>
          </div>
        </div>
      </div>
      <div class="mt-1"></div>
      <?php echo $CLICSHOPPING_Hooks->output('Suppliers', 'PageContent', null, 'display'); ?>
    </div>
  </div>
</div>
</form>
</div>


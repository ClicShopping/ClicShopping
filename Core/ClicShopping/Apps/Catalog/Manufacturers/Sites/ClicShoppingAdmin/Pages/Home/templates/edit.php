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

use ClicShopping\Apps\Catalog\Manufacturers\Classes\ClicShoppingAdmin\ManufacturerAdmin;
use ClicShopping\Apps\Marketing\SEO\Classes\ClicShoppingAdmin\SeoAdmin;

$CLICSHOPPING_Manufacturers = Registry::get('Manufacturers');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$form_action = 'Insert';
$variable = '';

if ((isset($_GET['Edit']) && isset($_GET['mID']) && !empty($_GET['mID']))) {
  $form_action = 'Update';
  $variable = '&mID=' . HTML::sanitize($_GET['mID']);
}

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

echo $CLICSHOPPING_Wysiwyg::getWysiwyg();

echo HTML::form('manufacturers', $CLICSHOPPING_Manufacturers->link('Manufacturers&' . $form_action . $variable));

if ($form_action == 'Update') {
  echo HTML::hiddenField('manufacturers_id', HTML::sanitize($_GET['mID']));
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/manufacturers.gif', $CLICSHOPPING_Manufacturers->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Manufacturers->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
<?php
echo HTML::button($CLICSHOPPING_Manufacturers->getDef('button_cancel'), null, $CLICSHOPPING_Manufacturers->link('Manufacturers&page=' . $page . $variable), 'warning') . '&nbsp;';
echo(($form_action == 'Insert') ? HTML::button($CLICSHOPPING_Manufacturers->getDef('button_insert'), null, null, 'success') : HTML::button($CLICSHOPPING_Manufacturers->getDef('button_update'), null, null, 'success'));
?>
            </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  if (isset($_GET['mID'])) {
    $mId = $_GET['mID'];
  } else {
    $mId = null;
  }

  $Qmanufacturers = $CLICSHOPPING_Manufacturers->db->prepare('select m.manufacturers_id,
                                                                         m.manufacturers_name,
                                                                         m.manufacturers_image,
                                                                         m.date_added,
                                                                         m.last_modified,
                                                                         md.manufacturer_description,
                                                                         md.manufacturers_url,
                                                                         md.manufacturer_seo_title,
                                                                         md.manufacturer_seo_description,
                                                                         md.manufacturer_seo_keyword,
                                                                         m.manufacturers_id,
                                                                         m.suppliers_id
                                                                  from :table_manufacturers  m,
                                                                       :table_manufacturers_info md
                                                                  where m.manufacturers_id = md.manufacturers_id
                                                                  and md.languages_id = :languages_id
                                                                  and m.manufacturers_id = :manufacturers_id
                                                                ');
  $Qmanufacturers->bindValue(':languages_id', (int)$CLICSHOPPING_Language->getId());
  $Qmanufacturers->bindInt(':manufacturers_id', $mId);
  $Qmanufacturers->execute();

  ?>
  <div id="manufacturersTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Manufacturers->getDef('tab_general') . '</a>'; ?></li>
      <li
        class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Manufacturers->getDef('tab_description'); ?></a></li>
      <li
        class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Manufacturers->getDef('tab_visuel'); ?></a></li>
      <li
        class="nav-item"><?php echo '<a href="#tab4" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Manufacturers->getDef('tab_seo'); ?></a></li>
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
            <div
              class="float-start"><?php echo $CLICSHOPPING_Manufacturers->getDef('title_manufacturer_general'); ?></div>
          </div>
          <div class="adminformTitle">

            <div class="row" id="manufacturerName">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturers_name'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturers_name'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('manufacturers_name', $Qmanufacturers->value('manufacturers_name') ?? null, 'required aria-required="true" id="manufacturers_name" placeholder="' . $CLICSHOPPING_Manufacturers->getDef('text_manufacturers_name') . '"', 'manufacturers_name'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <div class="row" id="manufacturersUrl">
              <div class="col-md-12">
                <span
                  class="col-md-2"><?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturers_url'); ?></span>
              </div>
            </div>

            <div id="manufacturersLanguage">
              <?php
              $languages = $CLICSHOPPING_Language->getLanguages();
              for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                ?>
                <div class="mt-1"></div>
                <div class="form-group row">
                  <label for="code"
                         class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('manufacturers_url[' . $languages[$i]['id'] . ']', $CLICSHOPPING_ProductsAdmin->getManufacturerUrl($Qmanufacturers->valueInt('manufacturers_id') ?? null, $languages[$i]['id'])); ?>
                  </div>
                </div>
                <?php
              }
              ?>
            </div>
          </div>
          <?php echo $CLICSHOPPING_Hooks->output('Manufacturers', 'ProductsContentTab1', null, 'display'); ?>
        </div>
        <!-- //################################################################################################################ -->
        <!--          ONGLET Information description       //-->
        <!-- //################################################################################################################ -->
        <div class="tab-pane" id="tab2">
          <div class="col-md-12 mainTitle">
            <span><?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturers_description'); ?></span>
          </div>
          <div class="adminformTitle" id="manufactuerDescription">
            <div class="accordion" id="accordionExample">
              <?php
              echo $CLICSHOPPING_Wysiwyg::getWysiwyg();

              for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                ?>
                <div class="accordion-item">
                  <h2 class="accordion-header" id="heading<?php echo $i; ?>">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                      <?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?>
                    </button>
                  </h2>
                  <?php
                  if ($i == 0) {
                    $show = ' show';
                  } else {
                    $show = '';
                  }
                  ?>

                  <div id="collapseOne" class="accordion-collapse collapse <?php echo $show; ?>"
                       aria-labelledby="heading<?php $i; ?>" data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                      <?php
                      $name = 'manufacturer_description[' . $languages[$i]['id'] . ']';
                      $ckeditor_id = $CLICSHOPPING_Wysiwyg::getWysiwygId($name);

                      echo $CLICSHOPPING_Wysiwyg::textAreaCkeditor($name, 'soft', '750', '300', (isset($manufacturer_description[$languages[$i]['id']]) ? str_replace('& ', '&amp; ', trim($manufacturer_description[$languages[$i]['id']])) : ManufacturerAdmin::getManufacturerDescription($Qmanufacturers->valueInt('manufacturers_id'), $languages[$i]['id'])), 'id="' . $ckeditor_id . '"');
                      ?>
                    </div>
                  </div>
                </div>
                <?php
              }
              ?>
            </div>
          </div>
          <div class="mt-1"></div>
          <?php echo $CLICSHOPPING_Hooks->output('Manufacturers', 'ProductsContentTab2', null, 'display'); ?>
        </div>

        <!-- //################################################################################################################ -->
        <!--          ONGLET Information visuelle          //-->
        <!-- //################################################################################################################ -->
        <div class="tab-pane" id="tab3">
          <div class="mainTitle"><?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturer_image'); ?></div>
          <div class="adminformTitle" id="manufacturerImage">
            <div class="row">
              <div class="col-md-12">
                <span
                  class="col-md-6"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/banner_manager.gif', $CLICSHOPPING_Manufacturers->getDef('text_products_image_vignette'), '40', '40'); ?></span>
                <span
                  class="col-md-6 main"><?php echo $CLICSHOPPING_Manufacturers->getDef('text_products_image_vignette'); ?></span>
                <span
                  class="col-md-6"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'images_product.gif', $CLICSHOPPING_Manufacturers->getDef('text_products_image_visuel'), '40', '40'); ?></span>
                <span
                  class="col-md-7 main"><?php echo $CLICSHOPPING_Manufacturers->getDef('text_products_image_visuel'); ?></span>
              </div>
              <div class="col-md-12">
                <div class="adminformAide">
                  <div class="row">
                    <span
                      class="col-md-4 text-center float-start">
                      <?php echo $CLICSHOPPING_Wysiwyg::fileFieldImageCkEditor('manufacturers_image', null, '212', '212'); ?>
                    </span>
                    <span class="col-md-8 text-center float-end">
                        <div
                          class="col-md-12"><?php echo $CLICSHOPPING_ProductsAdmin->getInfoImage($Qmanufacturers->value('manufacturers_image') ?? null, $CLICSHOPPING_Manufacturers->getDef('text_products_image_vignette')); ?></div>
                        <div class="col-md-12 text-end">
                          <?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturers_image_delete') . ' ' . HTML::checkboxField('delete_image', 'yes', false); ?>
                        </div>
                        </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php echo $CLICSHOPPING_Hooks->output('Manufacturers', 'ProductsContentTab3', null, 'display'); ?>
          <div class="mt-1"></div>
          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Manufacturers->getDef('title_help_image') . '"></i></h4>' . $CLICSHOPPING_Manufacturers->getDef('title_help_image') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_Manufacturers->getDef('help_image_manufacturers'); ?></div>
          </div>
        </div>
        <!-- //################################################################################################################ -->
        <!--          ONGLET SEO          //-->
        <!-- //################################################################################################################ -->
        <!-- decompte caracteres -->
        <div class="tab-pane" id="tab4">
          <div class="col-md-12 mainTitle">
            <div
              class="float-start"><?php echo $CLICSHOPPING_Manufacturers->getDef('title_manufacturer_seo'); ?></div>
          </div>
          <div class="adminformTitle">
            <div class="mt-1"></div>
            <div class="col-md-12">
              <div class="row text-center" id="productsGoogleKeywords">
                <a href="https://www.google.fr/trends"
                   target="_blank"><?php echo CLICSHOPPING::getDef('keywords_google_trend'); ?></a>
              </div>
            </div>
            <div class="mt-1"></div>

            <div class="accordion" id="accordionExample">
              <?php
              echo $CLICSHOPPING_Wysiwyg::getWysiwyg();

              for ($i = 0, $n = \count($languages);
              $i < $n;
              $i++) {
              $languages_id = $languages[$i]['id'];
              ?>
              <div class="accordion-item">
                <h2 class="accordion-header" id="heading<?php echo $i; ?>">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne"
                          aria-expanded="true" aria-controls="collapseOne">
                    <?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?>
                  </button>
                </h2>
                <?php
                if ($i == 0) {
                  $show = ' show';
                } else {
                  $show = '';
                }
                ?>

                <div id="collapseOne" class="accordion-collapse collapse <?php echo $show; ?>"
                     aria-labelledby="heading<?php $i; ?>" data-bs-parent="#accordionExample">
                  <div class="accordion-body">
                    <div class="mt-1"></div>
                    <div class="row" id="manufacturerSeoTitle<?php echo $languages_id; ?>">
                      <div class="col-md-10">
                        <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                          <label for="<?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturer_seo_title'); ?>"
                                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Manufacturers->getDef('text_manufacturer_seo_title'); ?></label>
                          <div class="col-md-7 input-group" id="manufacturer_seo_title<?php echo $languages_id; ?>">
                            <?php echo HTML::inputField('manufacturer_seo_title[' . $languages_id . ']', SeoAdmin::getManufacturerSeoTitle($Qmanufacturers->valueInt('manufacturers_id') ?? null, $languages_id), 'maxlength="70" size="77" id="manufacturer_seo_title_' . $languages_id . '"', false); ?>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="mt-1"></div>
                    <div class="row" id="manufacturerSeoDescription<?php echo $languages_id; ?>">
                      <div class="col-md-6">
                        <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                          <label
                            for="<?php echo $CLICSHOPPING_Manufacturers->getDef('title_manufacturer_seo_description'); ?>"
                            class="col-1 col-form-label"><?php echo $CLICSHOPPING_Manufacturers->getDef('title_manufacturer_seo_description'); ?></label>
                          <div class="col-md-8 input-group"
                               id="manufacturer_seo_description<?php echo $languages_id; ?>">
                            <?php echo HTML::textAreaField('manufacturer_seo_description[' . $languages_id . ']', SeoAdmin::getManufacturerSeoDescription($Qmanufacturers->valueInt('manufacturers_id') ?? null, $languages_id), '110', '5', 'id="manufacturer_seo_description_' . $languages_id . '"'); ?>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="mt-1"></div>
                    <div class="row" id="manufacturerSeoKeywords<?php echo $languages_id; ?>">
                      <div class="col-md-10">
                        <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                          <label
                            for="<?php echo $CLICSHOPPING_Manufacturers->getDef('title_manufacturer_seo_keywords'); ?>"
                            class="col-5 col-form-label"><?php echo $CLICSHOPPING_Manufacturers->getDef('title_manufacturer_seo_keywords'); ?></label>
                          <div class="col-md-7 input-group" id="manufacturer_seo_keyword<?php echo $languages_id; ?>">
                            <?php echo HTML::inputField('manufacturer_seo_keyword[' . $languages_id . ']', SeoAdmin::getManufacturerSeoKeyword($Qmanufacturers->valueInt('manufacturers_id') ?? null, $languages_id), 'maxlength="70" size="77" id="manufacturer_seo_keyword_' . $languages_id . '"', false); ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php
                }
                ?>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="alert alert-info" role="alert">
              <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Manufacturers->getDef('title_help_seo') . '"></i></h4>' . $CLICSHOPPING_Manufacturers->getDef('title_help_seo') ?></div>
              <div class="mt-1"></div>
              <div><?php echo $CLICSHOPPING_Manufacturers->getDef('help_seo'); ?></div>
            </div>
          </div>
          <div class="mt-1"></div>
          <?php echo $CLICSHOPPING_Hooks->output('Manufacturers', 'ManufacturersTab4', null, 'display'); ?>
        </div>
      </div>
      <?php echo $CLICSHOPPING_Hooks->output('Manufacturers', 'PageContent', null, 'display'); ?>
    </div>
    </form>
  </div>
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Categories = Registry::get('Categories');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

$CLICSHOPPING_Hooks->call('Categories', 'PreAction');

// check if the catalog image directory exists
if (is_dir($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages())) {
  if (!FileSystem::isWritable($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages())) $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Categories->getDef('error_catalog_image_directory_not_writeable'), 'error');
} else {
  $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Categories->getDef('error_catalog_image_directory_does_not_exist'), 'error');
}

$languages = $CLICSHOPPING_Language->getLanguages();


if (isset($_GET['cPath'])) {
  $cPath = $_GET['cPath'];
} else {
  $cPath = '';
}

if (isset($_GET['cID'])) {
  $cID = $_GET['cID'];
} else {
  $cID = '';
}


echo HTML::form('new_category', $CLICSHOPPING_Categories->link('Categories&Insert&cPath=' . $cPath . '&cID=' . $cID), 'post', 'enctype="multipart/form-data"');

echo $CLICSHOPPING_Wysiwyg::getWysiwyg();
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_Categories->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Categories->getDef('heading_title'); ?></span>
          <span class="col-md-6 text-end">
<?php
echo HTML::hiddenField('categories_date_added', date('Y-m-d')) . HTML::button($CLICSHOPPING_Categories->getDef('button_update'), null, null, 'success') . ' ';
echo HTML::button($CLICSHOPPING_Categories->getDef('button_cancel'), null, $CLICSHOPPING_Categories->link('Categories&cPath=' . $cPath . '&cID=' . $cID), 'warning');
?>
            </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div id="categoriesTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Categories->getDef('tab_general') . '</a>'; ?></li>
      <li
        class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Categories->getDef('tab_description') . '</a>'; ?></li>
      <li
        class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Categories->getDef('tab_ref') . '</a>'; ?></li>
      <li
        class="nav-item"><?php echo '<a href="#tab4" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Categories->getDef('tab_img') . '</a>'; ?></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <?php
        // -------------------------------------------------------------------
        //          ONGLET General sur la description de la categorie
        // -------------------------------------------------------------------
        ?>
        <div class="tab-pane active" id="tab1">
          <div class="col-md-12 mainTitle">
            <div class="float-start"><?php echo $CLICSHOPPING_Categories->getDef('text_products_name'); ?></div>
            <div
              class="float-end"><?php echo $CLICSHOPPING_Categories->getDef('text_user_name') . AdministratorAdmin::getUserAdmin(); ?></div>
          </div>
          <div class="adminformTitle" id="categoriesLanguage">
            <?php
            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              ?>
              <div class="row">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="code"
                           class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                    <div class="col-md-5">
                      <?php echo HTML::inputField('categories_name[' . $languages[$i]['id'] . ']', null, 'class="form-control" required aria-required="true" Authentificator id="' . 'categories_name[' . $languages[$i]['id'] . ']' . '" placeholder="' . $CLICSHOPPING_Categories->getDef('text_edit_categories_name') . '"', true) . '&nbsp;'; ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
            ?>
            <div class="row" id="categoriesName">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Categories->getDef('text_categories_name'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Categories->getDef('text_categories_name'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::selectMenu('move_to_category_id', $CLICSHOPPING_CategoriesAdmin->getCategoryTree(), $cPath); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-1"></div>
          <div class="col-md-12 mainTitle"><?php echo $CLICSHOPPING_Categories->getDef('text_divers_title'); ?></div>
          <div class="adminformTitle" id="categoriesSortOrder">
            <div class="row">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Categories->getDef('text_edit_sort_order'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Categories->getDef('text_edit_sort_order'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('sort_order', null, 'size="2"'); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php echo $CLICSHOPPING_Hooks->output('Categories', 'CategoriesContentTab1', null, 'display'); ?>
        </div>
        <?php
        // ----------------------------------------------------------- //-->
        //          categories description                              //-->
        // ----------------------------------------------------------- //-->
        ?>
        <div class="tab-pane" id="tab2">
          <div class="col-md-12 mainTitle">
            <span><?php echo $CLICSHOPPING_Categories->getDef('text_description_categories'); ?></span>
          </div>
          <div class="adminformTitle">
            <div class="accordion" id="accordionExample">
              <?php
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
                      $name = 'categories_description[' . $languages[$i]['id'] . ']';
                      $ckeditor_id = $CLICSHOPPING_Wysiwyg::getWysiwygId($name);

                      echo $CLICSHOPPING_Wysiwyg::textAreaCkeditor($name, 'soft', '750', '300', null, 'id="' . $ckeditor_id . '"');
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
          <?php echo $CLICSHOPPING_Hooks->output('Categories', 'CategoriesContentTab2', null, 'display'); ?>
        </div>
        <?php
        // -----------------------------------------------------//-->
        //          categories SEO      //-->
        // ---------------------------------------------------- //-->
        ?>
        <div class="tab-pane" id="tab3">
          <div class="col-md-12 mainTitle">
            <span><?php echo $CLICSHOPPING_Categories->getDef('text_products_page_seo'); ?></span>
          </div>
          <div class="adminformTitle">
            <div class="spaceRow"></div>
            <div class="row">
              <div class="col-md-12 text-center">
                <span class="col-md-3"></span>
                <span class="col-md-3"><a href="https://www.google.fr/trends" target="_blank"
                                          rel="noreferrer"><?php echo $CLICSHOPPING_Categories->getDef('keywords_google_trend'); ?></a></span>

              </div>
            </div>
            <?php
            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              ?>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="code"
                           class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                  </div>
                </div>
              </div>
              <div class="mt-1"></div>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Categories->getDef('text_categories_seo_url'); ?>"
                           class="col-1 col-form-label"><?php echo $CLICSHOPPING_Categories->getDef('text_categories_seo_url'); ?></label>
                    <div class="col-md-8">
                      <?php echo HTML::inputField('categories_seo_url[' . $languages[$i]['id'] . ']', null, 'maxlength="70" size="77" id="default_url_' . $i . '"', false); ?>
                    </div>
                  </div>
                </div>
              </div>
              <div class="mt-1"></div>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Categories->getDef('text_products_page_title'); ?>"
                           class="col-1 col-form-label"><?php echo $CLICSHOPPING_Categories->getDef('text_products_page_title'); ?></label>
                    <div class="col-md-8">
                      <?php echo HTML::inputField('categories_head_title_tag[' . $languages[$i]['id'] . ']', null, 'maxlength="70" size="77" id="default_title_' . $i . '"', false); ?>
                    </div>
                  </div>
                </div>
              </div>
              <div class="mt-1"></div>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Categories->getDef('text_products_page_title'); ?>"
                           class="col-1 col-form-label"><?php echo $CLICSHOPPING_Categories->getDef('text_products_header_description'); ?></label>
                    <div class="col-md-8">
                      <?php echo HTML::textAreaField('categories_head_desc_tag[' . $languages[$i]['id'] . ']', null, '75', '2', 'id="default_description_' . $i . '"'); ?>
                    </div>
                  </div>
                </div>
              </div>
              <div class="mt-1"></div>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Categories->getDef('text_products_page_title'); ?>"
                           class="col-1 col-form-label"><?php echo $CLICSHOPPING_Categories->getDef('text_products_keywords'); ?></label>
                    <div class="col-md-8">
                      <?php echo HTML::textAreaField('categories_head_keywords_tag[' . $languages[$i]['id'] . ']', null, '75', '5'); ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
            ?>
            <div class="mt-1"></div>
            <div class="alert alert-info" role="alert">
              <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Categories->getDef('title_help_seo') . '"></i></h4> ' . $CLICSHOPPING_Categories->getDef('title_help_seo') ?></div>
              <div class="mt-1"></div>
              <div><?php echo $CLICSHOPPING_Categories->getDef('help_seo'); ?></div>
            </div>
          </div>
          <?php echo $CLICSHOPPING_Hooks->output('Categories', 'CategoriesContentTab3', null, 'display'); ?>
        </div>
        <?php
        // -----------------------------------------------------//-->
        //          ONGLET sur l'image de la categorie          //-->
        // ---------------------------------------------------- //-->
        ?>
        <div class="tab-pane" id="tab4">
          <div class="mainTitle">
            <span><?php echo $CLICSHOPPING_Categories->getDef('text_categories_image_title'); ?></span>
          </div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-12">
                <span
                  class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/banner_manager.gif', $CLICSHOPPING_Categories->getDef('text_categories_image_vignette'), '40', '40'); ?></span>
                <span
                  class="col-md-3 main"><?php echo $CLICSHOPPING_Categories->getDef('text_categories_image_vignette'); ?></span>
              </div>
              <div class="col-md-12">
                <div class="adminformAide">
                  <div class="row">
                    <span
                      class="col-md-4 text-center float-start"><?php echo $CLICSHOPPING_Wysiwyg::fileFieldImageCkEditor('categories_image', null, '300', '300'); ?></span>
                    <span class="col-md-8 text-center float-end">
                        <div class="col-md-12">
                          <?php echo $CLICSHOPPING_ProductsAdmin->getInfoImage(null, $CLICSHOPPING_Categories->getDef('text_categories_image_vignette')); ?>
                        </div>
                        <div class="col-md-12 text-end">
                          <?php echo $CLICSHOPPING_Categories->getDef('text_categories_image_delete') . HTML::checkboxField('delete_image', 'yes', false); ?>
                        </div>
                      </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Categories->getDef('title_help_image') . '"></i></h4> ' . $CLICSHOPPING_Categories->getDef('title_help_image') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_Categories->getDef('help_image_categories'); ?></div>
          </div>
          <?php echo $CLICSHOPPING_Hooks->output('Categories', 'CategoriesContentTab4', null, 'display'); ?>
        </div>
        <?php
        //***********************************
        // extension
        //***********************************
        echo $CLICSHOPPING_Hooks->output('Categories', 'PageContent', null, 'display');
        ?>
      </div>
    </div>
  </div>
  </form>
</div>

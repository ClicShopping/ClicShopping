<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\Marketing\SEO\Classes\ClicShoppingAdmin\SeoAdmin;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
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

$Qcategories = $CLICSHOPPING_Categories->db->prepare('select c.categories_id,
                                                               c.categories_image,
                                                               c.parent_id,
                                                               c.sort_order,
                                                               c.date_added,
                                                               c.last_modified,
                                                               cd.categories_name,
                                                               cd.categories_description,
                                                               cd.categories_seo_url,
                                                               cd.categories_head_title_tag,
                                                               cd.categories_head_desc_tag,
                                                               cd.categories_head_keywords_tag
                                                       from :table_categories c,
                                                            :table_categories_description cd
                                                       where c.categories_id = :categories_id
                                                       and c.categories_id = cd.categories_id
                                                       and cd.language_id = :language_id
                                                       order by c.sort_order,
                                                                cd.categories_name
                                                      ');
$Qcategories->bindInt(':categories_id', (int)$_GET['cID']);
$Qcategories->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$Qcategories->execute();

$cInfo = new ObjectInfo($Qcategories->toArray());

$languages = $CLICSHOPPING_Language->getLanguages();
$form_action = (isset($_GET['cID'])) ? 'update_category' : 'insert_category';

$current_category_id = HTML::sanitize($_GET['cID']);
$cPath = HTML::sanitize($_GET['cPath']);

echo HTML::form('new_category', $CLICSHOPPING_Categories->link('Categories&Update&cPath=' . $_GET['cPath'] . '&cID=' . $_GET['cID']), 'post', 'enctype="multipart/form-data"');

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
            echo HTML::hiddenField('categories_date_added', (($cInfo->date_added) ? $cInfo->date_added : date('Y-m-d'))) . HTML::hiddenField('parent_id', $cInfo->parent_id) . HTML::button($CLICSHOPPING_Categories->getDef('button_update'), null, null, 'success') . ' ';
            echo HTML::button($CLICSHOPPING_Categories->getDef('button_cancel'), null, $CLICSHOPPING_Categories->link('Categories&cPath=' . $_GET['cPath'] . '&cID=' . $_GET['cID']), 'warning');
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
                      <?php echo HTML::inputField('categories_name[' . $languages[$i]['id'] . ']', ($categories_name[$languages[$i]['id']] ?? $CLICSHOPPING_CategoriesAdmin->getCategoryName($cInfo->categories_id, $languages[$i]['id'])), 'class="form-control" required aria-required="true" id="' . 'categories_name[' . $languages[$i]['id'] . ']' . '" placeholder="' . $CLICSHOPPING_Categories->getDef('text_edit_categories_name') . '"', true) . '&nbsp;'; ?>
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
                    <?php echo HTML::selectMenu('move_to_category_id', $CLICSHOPPING_CategoriesAdmin->getCategoryTree(), $cPath) . HTML::hiddenField('current_category_id', $current_category_id); ?>
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
                    <?php echo HTML::inputField('sort_order', $cInfo->sort_order, 'size="2"'); ?>
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
                $languageId = $languages[$i]['id'];
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
                      <div class="col-md-12" id="categoriesDescription<?php echo $languageId; ?>">
                        <?php
                        $name = 'categories_description[' . $languageId . ']';
                        $ckeditor_id = $CLICSHOPPING_Wysiwyg::getWysiwygId($name);

                        echo $CLICSHOPPING_Wysiwyg::textAreaCkeditor($name, 'soft', '750', '300', (isset($categories_description[$languageId]) ? str_replace('& ', '&amp; ', trim($categories_description[$languageId])) : $CLICSHOPPING_CategoriesAdmin->getCategoryDescription($cInfo->categories_id, $languageId)), 'id="' . $ckeditor_id . '"');
                        ?>
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
            <div class="mt-1"></div>
            <div class="col-md-12">
              <div class="row text-center" id="productsGoogleKeywords">
                <a href="https://www.google.fr/trends"
                   target="_blank"><?php echo $CLICSHOPPING_Categories->getDef('keywords_google_trend'); ?></a>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="accordion" id="accordionExample">
              <?php
              for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                $languages_id = $languages[$i]['id'];
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

                      <div class="mt-1"></div>
                      <div class="row">
                        <div class="col-md-12">
                          <div class="form-group row">
                            <label for="<?php echo $CLICSHOPPING_Categories->getDef('text_categories_seo_url'); ?>"
                                   class="col-1 col-form-label"><?php echo $CLICSHOPPING_Categories->getDef('text_categories_seo_url'); ?></label>
                            <div class="col-md-8">
                              <?php echo HTML::inputField('categories_seo_url[' . $languages[$i]['id'] . ']', SeoAdmin::getCategoriesSeoUrl($cInfo->categories_id, $languages[$i]['id']), 'maxlength="70" size="77" id="default_url_' . $i . '"', false); ?>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="mt-1"></div>
                      <div class="row" id="categoriesSeoTitle<?php echo $languages_id; ?>">
                        <div class="col-md-10">
                          <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                            <label for="<?php echo $CLICSHOPPING_Categories->getDef('text_products_page_title'); ?>"
                                   class="col-5 col-form-label"><?php echo $CLICSHOPPING_Categories->getDef('text_products_page_title'); ?></label>
                            <div class="col-md-7 input-group"
                                 id="categories_head_title_tag<?php echo $languages_id; ?>">
                              <?php echo HTML::inputField('categories_head_title_tag[' . $languages[$i]['id'] . ']', SeoAdmin::getcategoriesSeoTitle($cInfo->categories_id, $languages[$i]['id']), 'maxlength="70" size="77" id="categories_head_title_tag_' . $languages_id . '"', false); ?>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="mt-1"></div>
                      <div class="row" id="categoriesSeoDescription<?php echo $languages_id; ?>">
                        <div class="col-md-6">
                          <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                            <label
                              for="<?php echo $CLICSHOPPING_Categories->getDef('text_products_header_description'); ?>"
                              class="col-5 col-form-label"><?php echo $CLICSHOPPING_Categories->getDef('text_products_header_description'); ?></label>
                            <div class="col-md-8 input-group" id="categories_head_desc_tag<?php echo $languages_id; ?>">
                              <?php echo HTML::textAreaField('categories_head_desc_tag[' . $languages[$i]['id'] . ']', SeoAdmin::getcategoriesSeoDescription($cInfo->categories_id, $languages[$i]['id']), '110', '5', 'id="categories_head_desc_tag_' . $languages_id . '"'); ?>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="mt-1"></div>
                      <div class="row" id="categoriesSeoKeywords<?php echo $languages_id; ?>">
                        <div class="col-md-10">
                          <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                            <label for="<?php echo $CLICSHOPPING_Categories->getDef('text_products_keywords'); ?>"
                                   class="col-5 col-form-label"><?php echo $CLICSHOPPING_Categories->getDef('text_products_keywords'); ?></label>
                            <div class="col-md-7 input-group"
                                 id="categories_head_keywords_tag<?php echo $languages_id; ?>">
                              <?php echo HTML::inputField('categories_head_keywords_tag[' . $languages[$i]['id'] . ']', SeoAdmin::getCategoriesSeoKeywords($cInfo->categories_id, $languages[$i]['id']), 'maxlength="70" size="77" id="categories_head_keywords_tag_' . $languages_id . '"', false); ?>
                            </div>
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
                      <?php echo $CLICSHOPPING_ProductsAdmin->getInfoImage($cInfo->categories_image, $CLICSHOPPING_Categories->getDef('text_categories_image_vignette')); ?>
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
</div>
</form>

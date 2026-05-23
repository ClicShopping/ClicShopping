<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\SEO\Classes\ClicShoppingAdmin\SeoAdmin;

$CLICSHOPPING_SEO = Registry::get('SEO');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

if ($CLICSHOPPING_MessageStack->exists('SEO')) {
  echo $CLICSHOPPING_MessageStack->get('SEO');
}

$Qseo = $CLICSHOPPING_SEO->db->prepare('select  seo_id,
                                                  language_id,
                                                  seo_defaut_language_title,
                                                  seo_defaut_language_keywords,
                                                  seo_defaut_language_description,
                                                  seo_defaut_language_footer,
                                                  seo_language_products_info_title,
                                                  seo_language_products_info_keywords,
                                                  seo_language_products_info_description,
                                                  seo_language_products_new_title,
                                                  seo_language_products_new_keywords,
                                                  seo_language_products_new_description,
                                                  seo_language_special_title,
                                                  seo_language_special_keywords,
                                                  seo_language_special_description,
                                                  seo_language_reviews_title,
                                                  seo_language_reviews_keywords,
                                                  seo_language_reviews_description,
                                                  seo_language_favorites_title,
                                                  seo_language_favorites_keywords,
                                                  seo_language_favorites_description,
                                                  seo_language_featured_title,
                                                  seo_language_featured_keywords,
                                                  seo_language_featured_description,
                                                  seo_defaut_language_title_h1,
                                                  seo_language_recommendations_title,
                                                  seo_language_recommendations_description,
                                                  seo_language_recommendations_keywords
                                         from :table_seo
                                         where seo_id = 1
                                        ');
$Qseo->execute();

$seoBject = $Qseo->toArray();

if (\is_array($seoBject)) {
  $seo = new ObjectInfo($Qseo->toArray());

  $languages = $CLICSHOPPING_Language->getLanguages();

  echo HTML::form('seo', $CLICSHOPPING_SEO->link('SEO&Update'));
  ?>
  <div class="contentBody">
    <div class="row">
      <div class="col-md-12">
        <div class="card card-block headerCard">
          <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/referencement.gif', $CLICSHOPPING_SEO->getDef('heading_title'), '40', '40'); ?></span>
            <span
              class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_SEO->getDef('heading_title'); ?></span>
            <span
              class="col-md-6 text-end"><?php echo HTML::button($CLICSHOPPING_SEO->getDef('button_update'), null, null, 'success'); ?></span>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-1"></div>
    <!-- ############################################################# //-->
    <!--          ONGLET Information General de la Page Accueil          //-->
    <!-- ############################################################# //-->
    <div id="pagesSeoTabs" style="overflow: auto;">
      <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
        <li
          class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_SEO->getDef('tab_seo_default') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_SEO->getDef('tab_seo_products_info'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_SEO->getDef('tab_seo_products_new'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab4" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_SEO->getDef('tab_seo_specials'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab5" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_SEO->getDef('tab_seo_reviews'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab6" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_SEO->getDef('tab_seo_favorites'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab7" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_SEO->getDef('tab_seo_featured'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab8" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_SEO->getDef('tab_seo_recommendations'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab9" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_SEO->getDef('tab_seo_sitemap'); ?></a></li>
      </ul>


      <div class="tabsClicShopping">
        <div class="tab-content">
          <!-- ############################################################# //-->
          <!--          ONGLET Information General                          //-->
          <!-- ############################################################# //-->
          <div class="tab-pane active" id="tab1">
            <div class="col-md-12 mainTitle">
              <div
                class="float-start"><?php echo $CLICSHOPPING_SEO->getDef('text_pages_seo_information_default'); ?></div>
            </div>
            <div class="adminformTitle">
              <div class="col-md-12">
                <div class="row text-center" id="productsGoogleKeywords">
                  <a href="https://www.google.fr/trends"
                     target="_blank"><?php echo CLICSHOPPING::getDef('keywords_google_trend'); ?></a>
                </div>
              </div>
              <div class="mt-1"></div>

              <div class="accordion" id="accordionExample">
                <?php

                for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                  $languages_id = $languages[$i]['id'];
                  ?>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php $i; ?>">
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
                         aria-labelledby="heading<?php $languages_id; ?>" data-bs-parent="#accordionExample">
                      <div class="accordion-body">
                        <div class="row" id="seoDefaultTitleH<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_defaut_language_title_h1'); ?>"
                                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_defaut_language_title_h1'); ?></label>
                              <div class="col-md-7 input-group" id="seo_default_title_h<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_defaut_language_title_h1_[' . $languages_id . ']', ($seo_defaut_language_title[$languages_id] ?? SeoAdmin::getSeoDefaultLanguageTitleH1($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_default_title_h_' . $languages_id . '"', false); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoDefaultLanguageTitle<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_default_language_title'); ?>"
                                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_default_language_title'); ?></label>
                              <div class="col-md-7 input-group" id="seo_default_title_tag<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_defaut_language_title_[' . $languages_id . ']', ($seo_defaut_language_title[$languages_id] ?? SeoAdmin::getSeoDefaultLanguageTitle($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_default_title_tag_' . $languages_id . '"', false); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoDefaultDescription<?php echo $languages_id; ?>">
                          <div class="col-md-6">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_default_language_description'); ?>"
                                class="col-1 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_default_language_description'); ?></label>
                              <div class="col-md-8 input-group" id="seo_default_desc_tag<?php echo $languages_id; ?>">
                                <?php echo HTML::textAreaField('seo_defaut_language_description_[' . $languages_id . ']', ($seo_defaut_language_description[$languages_id] ?? SeoAdmin::getSeoDefaultLanguageDescription($seo->seo_id, $languages_id)), '110', '5', 'id="seo_defaut_language_description_' . $languages_id . '"'); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoDefautLanguageKeywords<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_default_language_keywords'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_default_language_keywords'); ?></label>
                              <div class="col-md-7 input-group"
                                   id="seo_defaut_language_keywords<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_defaut_language_keywords_[' . $languages_id . ']', ($seo_defaut_language_keywords[$languages_id] ?? SeoAdmin::getSeoDefaultLanguageKeywords($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_defaut_language_keywords_' . $languages_id . '"', false); ?>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="mt-1"></div>
                        <div class="row" id="seoDefautLanguageFooter<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_default_language_footer'); ?>"
                                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_default_language_footer'); ?></label>
                              <div class="col-md-7 input-group"
                                   id="seo_defaut_language_footer<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_defaut_language_footer_[' . $languages_id . ']', ($seo_defaut_language_footer[$languages_id] ?? SeoAdmin::getSeoDefaultLanguageFooter($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_defaut_language_footer_' . $languages_id . '"', false); ?>
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
            </div>

            <div class="mt-1"></div>
            <div class="alert alert-info" role="alert">
              <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_SEO->getDef('title_help_seo') . '"></i></h4> ' . $CLICSHOPPING_SEO->getDef('title_help_seo') ?></div>
              <div class="mt-1"></div>
              <div><?php echo $CLICSHOPPING_SEO->getDef('help_seo'); ?></div>
            </div>
          </div>

          <!-- ############################################################# //-->
          <!--     ONGLET Information General de page information produit   //-->
          <!-- ############################################################# //-->
          <div class="tab-pane" id="tab2">
            <div class="col-md-12 mainTitle">
              <div
                class="float-start"><?php echo $CLICSHOPPING_SEO->getDef('text_pages_seo_information_products_info'); ?></div>
            </div>

            <div class="adminformTitle">
              <div class="col-md-12">
                <div class="row text-center" id="productsGoogleKeywords">
                  <a href="https://www.google.fr/trends"
                     target="_blank"><?php echo CLICSHOPPING::getDef('keywords_google_trend'); ?></a>
                </div>
              </div>
              <div class="mt-1"></div>

              <div class="accordion" id="accordionExample">
                <?php
                for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                  $languages_id = $languages[$i]['id'];
                  ?>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php $i; ?>">
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
                         aria-labelledby="heading<?php $languages_id; ?>" data-bs-parent="#accordionExample">
                      <div class="accordion-body">
                        <div class="row" id="seoProductDescriptionTitle<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_info_title'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_info_title'); ?></label>
                              <div class="col-md-7 input-group"
                                   id="seo_product_description_title_tag<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_language_products_info_title_[' . $languages_id . ']', ($seo_language_products_info_title[$languages_id] ?? SeoAdmin::getSeoProductsInfoTitle($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_product_description_title_tag_' . $languages_id . '"', false); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoProductDescription<?php echo $languages_id; ?>">
                          <div class="col-md-6">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_info_description'); ?>"
                                class="col-1 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_info_description'); ?></label>
                              <div class="col-md-8 input-group"
                                   id="seo_product_description<?php echo $languages_id; ?>">
                                <?php echo HTML::textAreaField('seo_language_products_info_description_[' . $languages_id . ']', ($seo_defaut_language_description[$languages_id] ?? SeoAdmin::getSeoProductsInfoDescription($seo->seo_id, $languages_id)), '110', '5', 'id="seo_product_description_' . $languages_id . '"'); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoProductDescriptionKeywords<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_info_keywords'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_info_keywords'); ?></label>
                              <div class="col-md-7 input-group"
                                   id="seo_product_description_keywords<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_language_products_info_keywords_[' . $languages_id . ']', ($seo_language_products_info_keywords[$languages_id] ?? SeoAdmin::getSeoProductsInfoKeywords($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_product_description_keywords_' . $languages_id . '"', false); ?>
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
            </div>
            <div class="mt-1"></div>
            <div class="alert alert-info" role="alert">
              <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_SEO->getDef('title_help_seo') . '"></i></h4> ' . $CLICSHOPPING_SEO->getDef('title_help_seo') ?></div>
              <div class="mt-1"></div>
              <div><?php echo $CLICSHOPPING_SEO->getDef('help_seo'); ?></div>
            </div>
          </div>

          <!-- ############################################################# //-->
          <!--          ONGLET Information General de la page nouveautes          //-->
          <!-- ############################################################# //-->

          <div class="tab-pane" id="tab3">

            <div class="col-md-12 mainTitle">
              <div
                class="float-start"><?php echo $CLICSHOPPING_SEO->getDef('text_pages_seo_information_products_new'); ?></div>
            </div>
            <div class="adminformTitle">
              <div class="col-md-12">
                <div class="row text-center" id="productsGoogleKeywords">
                  <a href="https://www.google.fr/trends"
                     target="_blank"><?php echo CLICSHOPPING::getDef('keywords_google_trend'); ?></a>
                </div>
              </div>
              <div class="mt-1"></div>

              <div class="accordion" id="accordionExample">
                <?php
                for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                  $languages_id = $languages[$i]['id'];
                  ?>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php $i; ?>">
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
                        <div class="row" id="seoProductNewTitle<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_new_title'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_new_title'); ?></label>
                              <div class="col-md-7 input-group"
                                   id="seo_product_new_title_tag<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_language_products_new_title_[' . $languages_id . ']', ($seo_language_products_new_title[$languages_id] ?? SeoAdmin::getSeoProductsNewTitle($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_product_new_title_tag_' . $languages_id . '"', false); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoProductNewDescription<?php echo $languages_id; ?>">
                          <div class="col-md-6">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_new_description'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_new_description'); ?></label>
                              <div class="col-md-9 input-group"
                                   id="seo_product_new_description<?php echo $languages_id; ?>">
                                <?php echo HTML::textAreaField('seo_language_products_new_description_[' . $languages_id . ']', ($seo_language_products_new_description[$languages_id] ?? SeoAdmin::getSeoProductsNewDescription($seo->seo_id, $languages_id)), '110', '5', 'id="seo_product_new_description_' . $languages_id . '"'); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoProductNewKeywords<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_info_keywords'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_info_keywords'); ?></label>
                              <div class="col-md-7 input-group"
                                   id="seo_product_new_keywords<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_language_products_new_keywords_[' . $languages_id . ']', ($seo_language_products_new_keywords[$languages_id] ?? SeoAdmin::getSeoProductsNewKeywords($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_product_new_keywords_' . $languages_id . '"', false); ?>
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
            </div>
            <div class="mt-1"></div>
            <div class="alert alert-info" role="alert">
              <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_SEO->getDef('title_help_seo') . '"></i></h4> ' . $CLICSHOPPING_SEO->getDef('title_help_seo') ?></div>
              <div class="mt-1"></div>
              <div><?php echo $CLICSHOPPING_SEO->getDef('help_seo'); ?></div>
            </div>
          </div>

          <!-- ############################################################# //-->
          <!--          ONGLET Information General de la page promotion          //-->
          <!-- ############################################################# //-->

          <div class="tab-pane" id="tab4">
            <div class="col-md-12 mainTitle">
              <div
                class="float-start"><?php echo $CLICSHOPPING_SEO->getDef('text_pages_seo_information_specials'); ?></div>
            </div>
            <div class="adminformTitle">
              <div class="col-md-12">
                <div class="row text-center" id="productsGoogleKeywords">
                  <a href="https://www.google.fr/trends"
                     target="_blank"><?php echo CLICSHOPPING::getDef('keywords_google_trend'); ?></a>
                </div>
              </div>
              <div class="mt-1"></div>

              <div class="accordion" id="accordionExample">
                <?php
                for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                  $languages_id = $languages[$i]['id'];
                  ?>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php $i; ?>">
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
                        <div class="row" id="seoSpecialTitle<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_specials_title'); ?>"
                                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_specials_title'); ?></label>
                              <div class="col-md-7 input-group" id="seo_special_title_tag<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_language_special_title_[' . $languages_id . ']', ($seo_language_special_title[$languages_id] ?? SeoAdmin::getSeoProductsSpecialsTitle($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_special_title_tag_' . $languages_id . '"', false); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoSpecialDescription<?php echo $languages_id; ?>">
                          <div class="col-md-6">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_specials_description'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_specials_description'); ?></label>
                              <div class="col-md-9 input-group"
                                   id="seo_special_description<?php echo $languages_id; ?>">
                                <?php echo HTML::textAreaField('seo_language_special_description_[' . $languages_id . ']', ($seo_language_special_description[$languages_id] ?? SeoAdmin::getSeoProductsSpecialsDescription($seo->seo_id, $languages_id)), '110', '5', 'id="seo_special_description_' . $languages_id . '"'); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoSpecialKeywords<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_specials_keywords'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_specials_keywords'); ?></label>
                              <div class="col-md-7 input-group" id="seo_special_keywords<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_language_special_keywords_[' . $languages_id . ']', ($seo_language_special_keywords[$languages_id] ?? SeoAdmin::getSeoProductsSpecialsKeywords($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_special_keywords_' . $languages_id . '"', false); ?>
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
            </div>
            <div class="mt-1"></div>
            <div class="alert alert-info" role="alert">
              <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_SEO->getDef('title_help_seo') . '"></i></h4> ' . $CLICSHOPPING_SEO->getDef('title_help_seo') ?></div>
              <div class="mt-1"></div>
              <div><?php echo $CLICSHOPPING_SEO->getDef('help_seo'); ?></div>
            </div>
          </div>

          <!-- ############################################################# //-->
          <!--          ONGLET Information  Commentaires                    //-->
          <!-- ############################################################# //-->

          <div class="tab-pane" id="tab5">
            <div class="col-md-12 mainTitle">
              <div
                class="float-start"><?php echo $CLICSHOPPING_SEO->getDef('text_pages_seo_information_reviews'); ?></div>
            </div>
            <div class="adminformTitle">
              <div class="col-md-12">
                <div class="row text-center" id="productsGoogleKeywords">
                  <a href="https://www.google.fr/trends"
                     target="_blank"><?php echo CLICSHOPPING::getDef('keywords_google_trend'); ?></a>
                </div>
              </div>
              <div class="mt-1"></div>

              <div class="accordion" id="accordionExample">
                <?php
                for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                  $languages_id = $languages[$i]['id'];
                  ?>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php $i; ?>">
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
                        <div class="row" id="seoReviewTitle<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_reviews_title'); ?>"
                                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_reviews_title'); ?></label>
                              <div class="col-md-7 input-group" id="seo_review_title_tag<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_language_reviews_title_[' . $languages_id . ']', ($seo_language_reviews_title[$languages_id] ?? SeoAdmin::getSeoProductsSpecialsTitle($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_review_title_tag_' . $languages_id . '"', false); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoReviewDescription<?php echo $languages_id; ?>">
                          <div class="col-md-6">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_reviews_description'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_reviews_description'); ?></label>
                              <div class="col-md-9 input-group" id="seo_review_description<?php echo $languages_id; ?>">
                                <?php echo HTML::textAreaField('seo_language_reviews_description_[' . $languages_id . ']', ($seo_language_reviews_description[$languages_id] ?? SeoAdmin::getSeoProductsReviewsDescription($seo->seo_id, $languages_id)), '110', '5', 'id="seo_review_description_' . $languages_id . '"'); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoReviewKeywords<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_reviews_keywords'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_reviews_keywords'); ?></label>
                              <div class="col-md-7 input-group" id="seo_review_keywords<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_language_reviews_keywords_[' . $languages_id . ']', ($seo_language_reviews_keywords[$languages_id] ?? SeoAdmin::getSeoProductsReviewsKeywords($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_review_keywords_' . $languages_id . '"', false); ?>
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
            </div>
          </div>

          <!-- ############################################################# //-->
          <!--          ONGLET Information  Favorites                    //-->
          <!-- ############################################################# //-->

          <div class="tab-pane" id="tab6">
            <div class="col-md-12 mainTitle">
              <div
                class="float-start"><?php echo $CLICSHOPPING_SEO->getDef('text_pages_seo_information_favorites'); ?></div>
            </div>
            <div class="adminformTitle">
              <div class="col-md-12">
                <div class="row text-center" id="productsGoogleKeywords">
                  <a href="https://www.google.fr/trends"
                     target="_blank"><?php echo CLICSHOPPING::getDef('keywords_google_trend'); ?></a>
                </div>
              </div>
              <div class="mt-1"></div>

              <div class="accordion" id="accordionExample">
                <?php
                for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                  $languages_id = $languages[$i]['id'];
                  ?>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php $i; ?>">
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
                        <div class="row" id="seoFavoriteTitle<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_favorites_title'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_favorites_title'); ?></label>
                              <div class="col-md-7 input-group" id="seo_favorite_title_tag<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_language_favorites_title_[' . $languages_id . ']', ($seo_language_favorites_title[$languages_id] ?? SeoAdmin::getSeoFavoritesTitle($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_favorite_title_tag_' . $languages_id . '"', false); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoFavoriteDescription<?php echo $languages_id; ?>">
                          <div class="col-md-6">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_favorites_description'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_favorites_description'); ?></label>
                              <div class="col-md-9 input-group"
                                   id="seo_favorite_description<?php echo $languages_id; ?>">
                                <?php echo HTML::textAreaField('seo_language_favorites_description_[' . $languages_id . ']', ($seo_language_favorites_description[$languages_id] ?? SeoAdmin::getSeoFavoritesDescription($seo->seo_id, $languages_id)), '110', '5', 'id="seo_favorite_description_' . $languages_id . '"'); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoFavoriteKeywords<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_favorites_keywords'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_favorites_keywords'); ?></label>
                              <div class="col-md-7 input-group" id="seo_favorite_keywords<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_language_favorites_keywords_[' . $languages_id . ']', ($seo_language_favorites_keywords[$languages_id] ?? SeoAdmin::getSeoFavoritesKeywords($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_favorite_keywords_' . $languages_id . '"', false); ?>
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
            </div>
          </div>


          <!-- ############################################################# //-->
          <!--          ONGLET Information  featured                    //-->
          <!-- ############################################################# //-->

          <div class="tab-pane" id="tab7">
            <div class="col-md-12 mainTitle">
              <div
                class="float-start"><?php echo $CLICSHOPPING_SEO->getDef('text_pages_seo_information_featured'); ?></div>
            </div>
            <div class="adminformTitle">
              <div class="col-md-12">
                <div class="row text-center" id="productsGoogleKeywords">
                  <a href="https://www.google.fr/trends"
                     target="_blank"><?php echo CLICSHOPPING::getDef('keywords_google_trend'); ?></a>
                </div>
              </div>

              <div class="mt-1"></div>
              <div class="accordion" id="accordionExample">
                <?php
                for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                  $languages_id = $languages[$i]['id'];
                  ?>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php $i; ?>">
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
                        <div class="row" id="seoFeaturedTitle<?php echo $i; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_featured_title'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_products_featured_title'); ?></label>
                              <div class="col-md-7 input-group" id="seo_featured_title_tag<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_language_featured_title_[' . $languages_id . ']', ($seo_language_featured_title[$languages_id] ?? SeoAdmin::getSeoFeaturedTitle($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_featured_title_tag_' . $languages_id . '"', false); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoFeaturedDescription<?php echo $languages_id; ?>">
                          <div class="col-md-6">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_featured_description'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_featured_description'); ?></label>
                              <div class="col-md-9 input-group"
                                   id="seo_featured_description<?php echo $languages_id; ?>">
                                <?php echo HTML::textAreaField('seo_language_featured_description_[' . $languages_id . ']', ($seo_language_featured_description[$languages_id] ?? SeoAdmin::getSeoFeaturedDescription($seo->seo_id, $languages_id)), '110', '5', 'id="seo_featured_description_' . $languages_id . '"'); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoFeaturedKeywords<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_featured_keywords'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_language_featured_keywords'); ?></label>
                              <div class="col-md-7 input-group" id="seo_featured_keywords<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_language_featured_keywords_[' . $languages_id . ']', ($seo_language_featured_keywords[$languages_id] ?? SeoAdmin::getSeoFeaturedKeywords($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_featured_keywords_' . $languages_id . '"', false); ?>
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
            </div>

            <div class="mt-1"></div>
            <div class="alert alert-info" role="alert">
              <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_SEO->getDef('title_help_seo') . '"></i></h4> ' . $CLICSHOPPING_SEO->getDef('title_help_seo') ?></div>
              <div class="mt-1"></div>
              <div><?php echo $CLICSHOPPING_SEO->getDef('help_seo'); ?></div>
            </div>
          </div>
          <!-- ############################################################# //-->
          <!--          ONGLET Information  Recommendations                    //-->
          <!-- ############################################################# //-->


          <div class="tab-pane" id="tab8">
            <div class="col-md-12 mainTitle">
              <div
                class="float-start"><?php echo $CLICSHOPPING_SEO->getDef('text_pages_seo_recommendations'); ?></div>
            </div>
            <div class="adminformTitle">
              <div class="col-md-12">
                <div class="row text-center" id="productsGoogleKeywords">
                  <a href="https://www.google.fr/trends"
                     target="_blank"><?php echo CLICSHOPPING::getDef('keywords_google_trend'); ?></a>
                </div>
              </div>

              <div class="mt-1"></div>
              <div class="accordion" id="accordionExample">
                <?php
                for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                  $languages_id = $languages[$i]['id'];
                  ?>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?php $i; ?>">
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
                         aria-labelledby="heading<?php echo $languages_id; ?>" data-bs-parent="#accordionExample">
                      <div class="accordion-body">
                        <div class="mt-1"></div>
                        <div class="row" id="seoRecommendationsLanguageTitle<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_recommendations_language_title'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_recommendations_language_title'); ?></label>
                              <div class="col-md-7 input-group"
                                   id="seo_recommendations_title_tag<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_recommendations_language_title_[' . $languages_id . ']', ($seo_recommendations_language_title[$languages_id] ?? SeoAdmin::getSeoRecommendationsLanguageTitle($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_recommendations_title_tag_' . $languages_id . '"', false); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoRecommendationsLanguageDescription<?php echo $languages_id; ?>">
                          <div class="col-md-6">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_recommendations_language_description'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_recommendations_language_description'); ?></label>
                              <div class="col-md-9 input-group"
                                   id="seo_recommendations_description_tag<?php echo $languages_id; ?>">
                                <?php echo HTML::textAreaField('seo_recommendations_language_description_[' . $languages_id . ']', ($seo_language_favorites_description[$languages_id] ?? SeoAdmin::getSeoRecommendationsLanguageDescription($seo->seo_id, $languages_id)), '110', '5', 'id="seo_recommendations_description_tag_' . $languages_id . '"'); ?>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="mt-1"></div>
                        <div class="row" id="seoRecommendationsLanguageKeywords<?php echo $languages_id; ?>">
                          <div class="col-md-10">
                            <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                              <label
                                for="<?php echo $CLICSHOPPING_SEO->getDef('text_seo_recommendations_language_keywords'); ?>"
                                class="col-5 col-form-label"><?php echo $CLICSHOPPING_SEO->getDef('text_seo_recommendations_language_keywords'); ?></label>
                              <div class="col-md-7 input-group"
                                   id="seo_recommendations_keywords_tag<?php echo $languages_id; ?>">
                                <?php echo '&nbsp;' . HTML::inputField('seo_recommendations_language_keywords_[' . $languages_id . ']', ($seo_recommendations_language_title[$languages_id] ?? SeoAdmin::getSeoRecommendationsLanguageKeywords($seo->seo_id, $languages_id)), 'maxlength="70" size="77" id="seo_recommendations_keywords_tag_' . $languages_id . '"', false); ?>
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
            </div>

            <div class="mt-1"></div>
            <div class="alert alert-info" role="alert">
              <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_SEO->getDef('title_help_seo') . '"></i></h4> ' . $CLICSHOPPING_SEO->getDef('title_help_seo') ?></div>
              <div class="mt-1"></div>
              <div><?php echo $CLICSHOPPING_SEO->getDef('help_seo'); ?></div>
            </div>
          </div>

          <!-- ############################################################# //-->
          <!--          ONGLET Information Sitemap                           //-->
          <!-- ############################################################# //-->

          <div class="tab-pane" id="tab9">
            <div class="adminformTitle">
              <div class="row">
                <table border="0" width="100%" cellspacing="0" cellpadding="2">
                  <td>
                    <table class="table table-sm table-hover table-striped">
                      <thead>
                      <tr class="dataTableHeadingRow">
                        <td>Link</td>
                        <td>Description</td>
                        <td>google</td>
                        <td>bing</td>
                      </tr>
                      <td></td>
                      </thead>
                      <tbody>
                      <tr>
                        <td><h5><i
                              class="bi bi-link"></i> <?php echo HTML::link(HTTP::getShopUrlDomain() . 'index.php?Sitemap&GoogleSitemapProducts', 'Products Sitemap', 'target="_blank"'); ?>
                          </h5></td>
                        <td>Products Sitemap</td>
                        <td><?php echo HTML::link('https://search.google.com/search-console/about', 'Google Search Console', 'target="_blank"'); ?></td>
                        <td><?php echo HTML::link('https://www.bing.com/webmasters/', 'Bing Console', 'target="_blank"'); ?></td>
                      </tr>
                      <td><h5><i
                            class="bi bi-link"></i> <?php echo HTML::link(HTTP::getShopUrlDomain() . 'index.php?Sitemap&GoogleSitemapIndex', 'Index Sitemap', 'target="_blank"'); ?>
                        </h5></td>
                      <td>Index Sitemap</td>
                      <td></td>
                      <td></td>
                      <tr>
                        <td><h5><i
                              class="bi bi-link"></i> <?php echo HTML::link(HTTP::getShopUrlDomain() . 'index.php?Sitemap&GoogleSitemapCategories', 'Categories Sitemap', 'target="_blank"'); ?>
                          </h5></td>
                        <td>Categories Sitemap</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td><h5><i
                              class="bi bi-link"></i> <?php echo HTML::link(HTTP::getShopUrlDomain() . 'index.php?Sitemap&GoogleSitemapFavorites', 'Favorites Sitemap', 'target="_blank"'); ?>
                          </h5></td>
                        <td>Favorites Sitemap</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td><h5><i
                              class="bi bi-link"></i> <?php echo HTML::link(HTTP::getShopUrlDomain() . 'index.php?Sitemap&GoogleSitemapFeatured', 'Featured Sitemap', 'target="_blank"'); ?>
                          </h5></td>
                        <td>Featured Sitemap</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td><h5><i
                              class="bi bi-link"></i> <?php echo HTML::link(HTTP::getShopUrlDomain() . 'index.php?Sitemap&GoogleSitemapManufacturers', 'Manufacturers Sitemap', 'target="_blank"'); ?>
                          </h5></td>
                        <td>Manufacturers Sitemap</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td><h5><i
                              class="bi bi-link"></i> <?php echo HTML::link(HTTP::getShopUrlDomain() . 'index.php?Sitemap&GoogleSitemapSpecials', 'Specials Sitemap', 'target="_blank"'); ?>
                          </h5></td>
                        <td>Specials Sitemap</td>
                        <td></td>
                        <td></td>
                      </tr>
                      <tr>
                        <td><h5><i
                              class="bi bi-link"></i> <?php echo HTML::link(HTTP::getShopUrlDomain() . 'index.php?Info&RSS', 'Rss', 'target="_blank"'); ?>
                          </h5></td>
                        <td>RSS</td>
                        <td></td>
                        <td></td>
                      </tr>
                      </tbody>
                    </table>
                  </td>
                </table>
              </div>
            </div>
          </div>

          <div class="mt-1"></div>
          <?php
          //***********************************
          // extension
          //***********************************
          echo $CLICSHOPPING_Hooks->output('SEO', 'PageTab', null, 'display');
          ?>
        </div>
      </div>
    </div>
    </form>
  </div>
  <?php
}
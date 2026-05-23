<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Reviews\Classes\ClicShoppingAdmin\ReviewsAdmin;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

$CLICSHOPPING_Reviews = Registry::get('Reviews');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

$Qreviews = $CLICSHOPPING_Reviews->db->prepare('select r.reviews_id,
                                                       r.products_id,
                                                       p.products_image,
                                                       rs.id,
                                                       rs.sentiment_status,
                                                       rs.sentiment_approved,
                                                       rsd.description
                                                from :table_reviews r 
                                                        left join :table_reviews_sentiment rs on (r.reviews_id = rs.reviews_id)
                                                        left join :table_reviews_sentiment_description rsd on (rs.id = rsd.id),
                                                     :table_products p
                                                where p.products_id = r.products_id
                                                and r.reviews_id = :reviews_id
                                                and rs.id = rsd.id
                                                ');

$Qreviews->bindInt('reviews_id', (int)$_GET['rID']);
$Qreviews->execute();

$languages = $CLICSHOPPING_Language->getLanguages();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

echo HTML::form('sentiment', $CLICSHOPPING_Reviews->link('ReviewsSentiment&Save&rID=' . (int)$_GET['rID'] . '&page=' . $page), 'post', 'enctype="multipart/form-data"');
echo $CLICSHOPPING_Wysiwyg::getWysiwyg();
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/reviews.gif', $CLICSHOPPING_Reviews->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Reviews->getDef('heading_title'); ?></span>
          <span class="col-md-6 text-end">
           <?php
             echo HTML::button($CLICSHOPPING_Reviews->getDef('button_back'), null, $CLICSHOPPING_Reviews->link('ReviewsSentiment&page=' . $page), 'primary') . '&nbsp;';
             echo HTML::button($CLICSHOPPING_Reviews->getDef('button_save'), null, null, 'success') . '&nbsp;';
           ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div id="reviewsTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Reviews->getDef('tab_general') . '</a>'; ?></li>
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
            <div class="row">
              <span class="col-md-6"><?php echo $CLICSHOPPING_Reviews->getDef('text_description_sentiment'); ?></span>
              <span class="col-md-6 text-end"><?php echo AdministratorAdmin::getUserAdmin() . HTML::hiddenField('user_admin', AdministratorAdmin::getUserAdmin()); ?></span>
            </div>
          </div>
          <div class="adminformTitle">
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
                      <div class="col-md-12" id="ReviewsSentimentDescription<?php echo $languages[$i]['id']; ?>">
                        <?php
                        $name = 'reviews_sentiment_description[' . $languages_id . ']';
                        $ckeditor_id = $CLICSHOPPING_Wysiwyg::getWysiwygId($name);

                        echo $CLICSHOPPING_Wysiwyg::textAreaCkeditor($name, 'soft', '750', '300', (isset($reviews_sentiment_description[$languages_id]) ? str_replace('& ', '&amp; ', trim($reviews_sentiment_description[$languages_id])) : ReviewsAdmin::getSentimentDescription($Qreviews->valueInt('id'), $languages_id)), 'id="' . $ckeditor_id . '"');
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
        </div>
        <div class="mt-1"></div>
        <?php echo $CLICSHOPPING_Hooks->output('ReviewsSentimentEdit', 'PageTab', null, 'display'); ?>
      </div>
    </div>
  </div>
</div>
</form>

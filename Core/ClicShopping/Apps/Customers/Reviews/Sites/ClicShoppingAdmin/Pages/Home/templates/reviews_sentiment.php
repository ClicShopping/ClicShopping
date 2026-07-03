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

use ClicShopping\Apps\Customers\Reviews\Classes\ClicShoppingAdmin\ReviewsAdmin;

$CLICSHOPPING_Reviews = Registry::get('Reviews');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$review_number = (int)CLICSHOPPING_APP_REVIEWS_RV_REVIEW_NUMBER ?? 1;
?>

<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/reviews.gif', $CLICSHOPPING_Reviews->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Reviews->getDef('heading_title'); ?></span>
          <span
            class="col-md-6 text-md-end"><?php echo HTML::button($CLICSHOPPING_Reviews->getDef('button_configure'), null, CLICSHOPPING::link(null, 'A&Customers\Reviews&Configure'), 'primary'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  $QavgReviews = $CLICSHOPPING_Reviews->db->prepare('select count(reviews_id) as count,
                                                            avg(reviews_rating) as avg
                                                    from :table_reviews
                                                   ');

  $QavgReviews->execute();

  $QavgReviewsSentiment = $CLICSHOPPING_Reviews->db->prepare('select count(sentiment_status) as count_sentiment
                                                              from :table_reviews_sentiment
                                                               where sentiment_status = 1
                                                             ');

  $QavgReviewsSentiment->execute();

  $QavgReviewsSentimentApproved = $CLICSHOPPING_Reviews->db->prepare('select count(sentiment_approved) as count_approved
                                                                      from :table_reviews_sentiment
                                                                      where sentiment_approved = 1
                                                                     ');

  $QavgReviewsSentimentApproved->execute();
  ?>
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <?php
          if ($QavgReviews->valueInt('count') > 0) {
            ?>
            <div class="col-md-2 col-12">
              <div class="card bg-primary">
                <div class="card-body">
                  <h6 class="card-title text-white"><i
                      class="bi bi-bar-chart text-white"></i> <?php echo '&nbsp;' . $CLICSHOPPING_Reviews->getDef('text_statistics_sentiment'); ?>
                  </h6>
                  <div class="card-text">
                    <div class="col-sm-12">
                      <span class="float-end">
                        <div
                          class="col-sm-12 text-white"><?php echo $CLICSHOPPING_Reviews->getDef('text_count_sentiment') . '  ' . $QavgReviewsSentiment->valueInt('count_sentiment'); ?></div>
                        <div
                          class="col-sm-12 text-white"><?php echo $CLICSHOPPING_Reviews->getDef('text_count_sentiment_approved') . '  ' . $QavgReviewsSentimentApproved->valueInt('count_approved'); ?></div>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }

          if (ReviewsAdmin::countCustomersTags() > 0) {
            ?>
            <div class="col-md-2 col-12">
              <div class="card bg-success">
                <div class="card-body">
                  <h6 class="card-title text-white"><i
                      class="bi bi-bar-chart text-white"></i> <?php echo '&nbsp;' . $CLICSHOPPING_Reviews->getDef('text_statistics'); ?>
                  </h6>
                  <div class="card-text">
                    <div class="col-sm-12">
                      <span class="float-end">
                        <div
                          class="col-sm-12 text-white"><?php echo $CLICSHOPPING_Reviews->getDef('text_average_review') . '  ' . $QavgReviews->valueInt('avg'); ?></div>
                        <div
                          class="col-sm-12 text-white"><?php echo $CLICSHOPPING_Reviews->getDef('text_count_review') . '  ' . $QavgReviews->valueInt('count'); ?></div>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }

          echo $CLICSHOPPING_Hooks->output('Reviews', 'StatsReviewsSentiment');
          ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="alert alert-warning"><?php echo $CLICSHOPPING_Reviews->getDef('text_info_warning_cost', ['nb_comment' => $review_number]); ?></div>
  <div class="mt-1"></div>

  <!-- //################################################################################################################ -->
  <!-- //                                            LISTING DES AVIS CLIENTS                                             -->
  <!-- //################################################################################################################ -->
  <?php echo HTML::form('delete_all', $CLICSHOPPING_Reviews->link('ReviewsSentiment&DeleteAll&page=' . $page)); ?>

  <div id="toolbar" class="float-end">
    <button id="button" class="btn btn-danger"><?php echo $CLICSHOPPING_Reviews->getDef('button_delete'); ?></button>
  </div>

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-id-field="selected"
    data-select-item-name="selected[]"
    data-click-to-select="true"
    data-sort-order="asc"
    data-sort-name="selected"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-checkbox="true" data-field="state"></th>
      <th data-field="selected" data-sortable="true" data-visible="false"
          data-switchable="false"><?php echo $CLICSHOPPING_Reviews->getDef('id'); ?></th>
      <th data-switchable="false"></th>
      <th data-switchable="false"></th>
      <th data-field="products"
          data-sortable="true"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_products'); ?></th>
      <th data-field="rating"
          data-sortable="true"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_rating'); ?></th>
      <th data-field="review_read" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_review_analyse'); ?></th>
      <th data-field="review_group" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_review_count_comment'); ?></th>
      <th data-field="review_vote_sentiment" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_review_count_vote_sentiment'); ?></th>
      <th data-field="approved" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_approved'); ?></th>
      <th data-field="action"
          class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_action'); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qreviews = $CLICSHOPPING_Reviews->db->prepare('select distinct SQL_CALC_FOUND_ROWS  r.reviews_id,
                                                                                         count(r.reviews_id) as count,
                                                                                         r.products_id,
                                                                                         avg(r.reviews_rating) as average,
                                                                                         r.status,
                                                                                         r.customers_group_id,
                                                                                         p.products_image,
                                                                                         rs.id,
                                                                                         rs.sentiment_status,
                                                                                         rs.sentiment_approved
                                                            from :table_reviews r left join :table_reviews_sentiment rs on (r.reviews_id = rs.reviews_id),
                                                                 :table_products p
                                                            where p.products_id = r.products_id
                                                            group by r.products_id
                                                            order by r.date_added desc
                                                            limit :page_set_offset, 
                                                                  :page_set_max_results
                                                            ');

    $Qreviews->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qreviews->execute();

    $listingTotalRow = $Qreviews->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qreviews->fetch()) {

        if ((!isset($_GET['rID']) || (isset($_GET['rID']) && ((int)$_GET['rID'] === $Qreviews->valueInt('reviews_id')))) && !isset($rInfo)) {
          $array_db = [
            'reviews r',
            'reviews_description rd'
          ];
          $array_field = [
            'r.reviews_id',
            'r.reviews_read',
            'r.customers_name',
            'length(rd.reviews_text) as reviews_text_size',
          ];

          $QreviewsText = $CLICSHOPPING_Reviews->db->get($array_db, $array_field,
            [
                'r.reviews_id' => [
                'val' => $Qreviews->valueInt('reviews_id'),
                'ref' => 'rd.reviews_id'
              ]
            ]
          );
        }

        $image_array = $CLICSHOPPING_ProductsAdmin->getImage($Qreviews->valueInt('products_id'));
        $products_image = $image_array['products_image'];

        $products_name = $CLICSHOPPING_ProductsAdmin->getProductsName($Qreviews->valueInt('products_id'), $CLICSHOPPING_Language->getId());

        $QcustomerGroup = $CLICSHOPPING_Reviews->db->prepare('select customers_group_id,
                                                                     customers_group_name
                                                               from :table_customers_groups
                                                               where customers_group_id = :customers_group_id
                                                              ');
        $QcustomerGroup->bindInt(':customers_group_id', $Qreviews->valueInt('customers_group_id'));
        $QcustomerGroup->execute();

        if ($QcustomerGroup->valueInt('customers_group_id') == 0) {
          $customer_group['customers_group_name'] = $CLICSHOPPING_Reviews->getDef('visitor_name');
        }
        ?>
        <tr>
          <td></td>
          <td><?php echo $Qreviews->valueInt('id'); ?></td>
          <th scope="row" width="50px"></th>
          <td><?php echo HTML::image($CLICSHOPPING_Template->getDirectoryShopTemplateImages() . $products_image, $products_name, (int)SMALL_IMAGE_WIDTH_ADMIN, (int)SMALL_IMAGE_HEIGHT_ADMIN); ?></td>
          <td>
            <strong><?php echo HTML::link($CLICSHOPPING_Reviews->link('&Edit&page=' . $page . '&rID=' . $Qreviews->valueInt('reviews_id')), $products_name); ?></strong>
          </td>
          <td><?php echo '<i>' . HTML::stars($Qreviews->valueDecimal('average')) . '</i>'; ?></td>
          <td class="text-center">
            <?php
            if ($Qreviews->valueInt('sentiment_status') == 1) {
              echo '<i class="bi-check text-success"></i>';
            } else {
              echo '<i class="bi bi-x text-danger"></i>';
            }
            ?>
          </td>
          <td class="text-center">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php echo $Qreviews->valueInt('count') . '&nbsp;<h6><i class="bi bi-question-circle text-warning" title="' . $CLICSHOPPING_Reviews->getDef('help_info_warning') . '"></i></h6>'; ?>
            </div>
          </td>
          <td class="text-center"><span class="text-success"><?php echo ReviewsAdmin::getTotalReviewsSentimentVoteYes($Qreviews->valueInt('products_id')) ?><span> | <span class="text-danger"><?php echo ReviewsAdmin::getTotalReviewsSentimentVoteNo($Qreviews->valueInt('products_id')) ?><span></td>
          <td class="text-center">
            <?php
            if ($Qreviews->valueInt('sentiment_approved') == 1) {
              echo HTML::link($CLICSHOPPING_Reviews->link('ReviewsSentiment&SetFlag&flag=0&id=' . $Qreviews->valueInt('reviews_id')), '<i class="bi-check text-success"></i>');
            } else {
              echo HTML::link($CLICSHOPPING_Reviews->link('ReviewsSentiment&SetFlag&flag=1&id=' . $Qreviews->valueInt('reviews_id')), '<i class="bi bi-x text-danger"></i>');
            }
            ?>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
            <?php
              if ($Qreviews->valueInt('count') >= $review_number) {
                echo HTML::link($CLICSHOPPING_Reviews->link('ReviewsSentiment&Update&page=' . $page . '&rID=' . $Qreviews->valueInt('reviews_id')), '<h4><i class="bi bi-gear" title="' . $CLICSHOPPING_Reviews->getDef('icon_update') . '"></i></h4>');
              } else {
                echo '&nbsp;';
              }

              echo '&nbsp;';

              if($Qreviews->valueInt('sentiment_status') == 1) {
                echo HTML::link($CLICSHOPPING_Reviews->link('ReviewsSentimentEdit&page=' . $page . '&rID=' . $Qreviews->valueInt('reviews_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Reviews->getDef('icon_edit') . '"></i></h4>');
                echo '&nbsp;';
                echo HTML::link($CLICSHOPPING_Reviews->link('ReviewsSentimentEdit&page=' . $page . '&rID=' . $Qreviews->valueInt('reviews_id')) . '#tab2', '<h4><i class="bi bi-eye" title="' . $CLICSHOPPING_Reviews->getDef('button_view_analysis') . '"></i></h4>');
              }
            ?>
          </td>
        </tr>
        <?php
      }
    }
    ?>
    </tbody>
  </table>
  </form>
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qreviews->getPageSetLabel($CLICSHOPPING_Reviews->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qreviews->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>
<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
  use ClicShopping\OM\Hash;
  use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Reviews\Classes\ClicShoppingAdmin\ReviewsAdmin;

$CLICSHOPPING_Reviews = Registry::get('Reviews');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
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
            <span class="col-md-6 text-md-end">
              <?php
              if (CLICSHOPPING_APP_CHATGPT_CH_STATUS == 'True') {
               echo HTML::button($CLICSHOPPING_Reviews->getDef('button_reviews_sentiment'), null, CLICSHOPPING::link(null, 'A&Customers\Reviews&ReviewsSentiment'), 'success') . '&nbsp;';
              }
	      
              if (MODE_DEMO == 'False') {
                echo HTML::button($CLICSHOPPING_Reviews->getDef('button_configure'), null, CLICSHOPPING::link(null, 'A&Customers\Reviews&Configure'), 'primary');
              }
               ?>
            </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- ################# -->
  <!-- Hooks Stats - just use execute function to display the hook-->
  <!-- ################# -->
  <?php
  $QavgReviews = $CLICSHOPPING_Reviews->db->prepare('select count(reviews_id) as count,
                                                            count(customers_tag) as count_customers_tag,
                                                            avg(reviews_rating) as avg
                                                    from :table_reviews
                                                   ');

  $QavgReviews->execute();
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
                      class="bi bi-bar-chart text-white"></i> <?php echo '&nbsp;' . $CLICSHOPPING_Reviews->getDef('text_statistics'); ?>
                  </h6>
                  <div class="card-text">
                    <div class="col-sm-12">
                      <span class="float-end">
                        <div
                          class="col-sm-12 text-white"><?php echo $CLICSHOPPING_Reviews->getDef('text_count_reviews') . '  ' . $QavgReviews->valueInt('count'); ?></div>
                        <div
                          class="col-sm-12 text-white"><?php echo $CLICSHOPPING_Reviews->getDef('text_average_reviews') . '  ' . $QavgReviews->valueDecimal('avg'); ?></div>
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
                          <div class="col-sm-12 text-white">
                            <?php
                            $total = ReviewsAdmin::countCustomersTags();
                            echo $CLICSHOPPING_Reviews->getDef('text_count_customers_tag') . '  ' . $total;
                            ?>
                          </div>
                          <br/>
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
              <div class="card bg-info">
                <div class="card-body">
                  <h6 class="card-title text-white"><i
                            class="bi bi-bar-chart text-white"></i> <?php echo '&nbsp;' . $CLICSHOPPING_Reviews->getDef('text_statistics_customer_review_evaluation'); ?>
                  </h6>
                  <div class="card-text">
                    <div class="col-sm-12">
                        <span class="float-end">
                          <div class="col-sm-12 text-white">
                             <span class="float-end">
                              <div class="col-sm-12 text-white">
                                <?php
                                  $total = ReviewsAdmin::getTotalReviewsVoteYes();
                                  echo $CLICSHOPPING_Reviews->getDef('text_count_vote_yes') . '  ' . $total;
                                ?>
                              </div>
                              <div class="col-sm-12 text-white">
                                <?php
                                  $total = ReviewsAdmin::getTotalReviewsVoteNo();
                                  echo $CLICSHOPPING_Reviews->getDef('text_count_vote_no') . '  ' . $total;
                                ?>
                              </div>
                            </span>
                          </div>
                          <br/>
                        </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }
	  
          echo $CLICSHOPPING_Hooks->output('Reviews', 'StatsReviews');
          ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                            LISTING DES AVIS CLIENTS                                             -->
  <!-- //################################################################################################################ -->
  <?php echo HTML::form('delete_all', $CLICSHOPPING_Reviews->link('Reviews&DeleteAll&page=' . $page)); ?>

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
    data-check-on-init="true"
    data-search="true">

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
      <th data-field="author"
          class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_review_author'); ?></th>
      <th data-field="review_read" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_review_read'); ?></th>
      <th data-field="review_group" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_review_group'); ?></th>
      <th data-field="last_modified"
          class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_last_modified'); ?></th>
      <th data-field="approved" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_approved'); ?></th>
      <th data-field="action"
          class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_action'); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qreviews = $CLICSHOPPING_Reviews->db->prepare('select SQL_CALC_FOUND_ROWS  r.reviews_id,
                                                                                 r.products_id,
                                                                                 r.date_added,
                                                                                 r.last_modified,
                                                                                 r.reviews_rating,
                                                                                 r.status,
                                                                                 r.customers_group_id,
                                                                                 r.customers_name,
                                                                                 p.products_image
                                                    from :table_reviews r,
                                                         :table_products p
                                                    where p.products_id = r.products_id
                                                    order by r.date_added desc
                                                    limit :page_set_offset, :page_set_max_results
                                                    ');

    $Qreviews->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qreviews->execute();

    $listingTotalRow = $Qreviews->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qreviews->fetch()) {
        if ((!isset($_GET['rID']) || (isset($_GET['rID']) && ((int)$_GET['rID'] === $Qreviews->valueInt('reviews_id')))) && !isset($rInfo)) {
          $QreviewsText = $CLICSHOPPING_Reviews->db->get(['reviews r',
            'reviews_description rd',
          ], [
            'r.reviews_id',
            'r.reviews_read',
            'r.customers_name',
            'length(rd.reviews_text) as reviews_text_size',
          ], [
              'r.reviews_id' => [
                'val' => $Qreviews->valueInt('reviews_id'),
                'ref' => 'rd.reviews_id'
              ]
            ]
          );

          $Qproducts_image = $CLICSHOPPING_Reviews->db->prepare('select products_image
                                                                   from :table_products
                                                                   where products_id = :products_id
                                                                  ');
          $Qproducts_image->bindInt(':products_id', $Qreviews->valueInt('products_id'));
          $Qproducts_image->execute();

          $Qproducts = $CLICSHOPPING_Reviews->db->prepare('select products_name
                                                             from :table_products_description
                                                             where products_id = :products_id
                                                             and language_id = :language_id
                                                             ');
          $Qproducts->bindInt(':products_id', $Qreviews->valueInt('products_id'));
          $Qproducts->bindint(':language_id', $CLICSHOPPING_Language->getId());
          $Qproducts->execute();

          $Qaverage = $CLICSHOPPING_Reviews->db->get('reviews', ['(avg(reviews_rating) / 5 * 100) as average_rating'], ['products_id' => $Qreviews->valueInt('products_id')]);

          $review_info = array_merge($QreviewsText->toArray(), $Qaverage->toArray(), $Qproducts->toArray());

          if ($Qproducts_image->fetch()) {
            $rInfo_array = array_merge($Qreviews->toArray(), (array)$review_info, $Qproducts_image->toArray());
          } else {
            $rInfo_array = array_merge($Qreviews->toArray(), (array)$review_info);
          }

          $rInfo = new ObjectInfo($rInfo_array);
        }

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
          <td></td>
          <td><?php echo $Qreviews->valueInt('reviews_id'); ?></td>
          <td scope="row"
              width="50px"><?php echo HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&Preview&pID=' . $Qreviews->valueInt('products_id') . '?page=' . $page), '<h4><i class="bi bi-easil3" title="' . $CLICSHOPPING_Reviews->getDef('icon_preview_comment') . '"></i></h4>'); ?></td>
          <td><?php echo HTML::image($CLICSHOPPING_Template->getDirectoryShopTemplateImages() . $Qreviews->value('products_image'), $Qreviews->value('products_name'), (int)SMALL_IMAGE_WIDTH_ADMIN, (int)SMALL_IMAGE_HEIGHT_ADMIN); ?></td>
          <td>
            <strong<?php echo HTML::link($CLICSHOPPING_Reviews->link('&Edit&page=' . $page . '&rID=' . $Qreviews->valueInt('reviews_id')), $CLICSHOPPING_ProductsAdmin->getProductsName($Qreviews->valueInt('products_id'))); ?></strong>
          </td>
          <td><?php echo '<i>' . HTML::stars($Qreviews->valueInt('reviews_rating')) . '</i>'; ?></td>
          <td class="text-center"><?php echo HTML::outputProtected(Hash::displayDecryptedDataText($Qreviews->value('customers_name'))); ?></td>
          <td class="text-center"><?php echo number_format($Qreviews->valueInt('reviews_read', 2)); ?></td>
          <td class="text-center"><?php echo $customer_group['customers_group_name']; ?></td>
          <td class="text-center"><?php echo DateTime::toLong($Qreviews->value('last_modified')); ?></td>
          <td class="text-center">
            <?php
            if ($Qreviews->valueInt('status') == 1) {
              echo HTML::link($CLICSHOPPING_Reviews->link('Reviews&SetFlag&flag=0&id=' . $Qreviews->valueInt('reviews_id')), '<i class="bi-check text-success"></i>');
            } else {
              echo HTML::link($CLICSHOPPING_Reviews->link('Reviews&SetFlag&flag=1&id=' . $Qreviews->valueInt('reviews_id')), '<i class="bi bi-x text-danger"></i>');
            }
            ?>
          <td
            class="text-end"><?php echo '<a href="' . $CLICSHOPPING_Reviews->link('Edit&page=' . $page . '&rID=' . $Qreviews->valueInt('reviews_id')) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Reviews->getDef('icon_edit') . '"></i></h4></a>'; ?></td>
          </td>
        </tr>
        <?php
      } //end while
    } //end $listingTotalRow
    ?>
    </tbody>
  </table>
  </form><!-- end form delete all -->
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
  } // end $listingTotalRow
  ?>
</div>
<div class="py-4"></div>
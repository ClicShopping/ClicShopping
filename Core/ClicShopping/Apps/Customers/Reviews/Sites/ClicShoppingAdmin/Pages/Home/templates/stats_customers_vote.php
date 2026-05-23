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

$CLICSHOPPING_Reviews = Registry::get('Reviews');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

if ($CLICSHOPPING_MessageStack->exists('reviews')) {
  echo $CLICSHOPPING_MessageStack->get('reviews');
}

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$QTotal = $CLICSHOPPING_Reviews->db->prepare('select id as count,
                                                    SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) AS vote_yes,
                                                    SUM(CASE WHEN vote = 0 THEN 1 ELSE 0 END) AS vote_no,
                                                    SUM(CASE WHEN vote IN (0, 1) THEN 1 ELSE 0 END) AS total_votes
                                             from :table_reviews_vote
                                             where reviews_id <> 0
                                            ');

$QTotal->execute();

$QTotalIa = $CLICSHOPPING_Reviews->db->prepare('select id as count_ia,
                                                      SUM(CASE WHEN sentiment = 1 THEN 1 ELSE 0 END) AS vote_ia_yes,
                                                      SUM(CASE WHEN sentiment = 0 THEN 1 ELSE 0 END) AS vote_ia_no,
                                                      SUM(CASE WHEN sentiment IN (0, 1) THEN 1 ELSE 0 END) AS total_ia_votes
                                              from :table_reviews_vote
                                              where reviews_id = 0
                                            ');

$QTotalIa->execute();
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/reviews.gif', $CLICSHOPPING_Reviews->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Reviews->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <?php
          if ($QTotal->valueInt('count') > 0) {
            $count_vote_yes = $QTotal->valueInt('vote_yes');
            $count_vote_no = $QTotal->valueInt('vote_no');
            $percentage_yes =  ($QTotal->valueInt('vote_yes') / $QTotal->valueInt('total_votes')) * 100;
            $percentage_no = ($QTotal->valueInt('vote_no') / $QTotal->valueInt('total_votes')) * 100;

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
                          class="col-sm-12 text-white"><?php echo $CLICSHOPPING_Reviews->getDef('text_count_vote_yes') . ' ' . $count_vote_yes . ' - ' . round($percentage_yes, 2) . '%'; ?></div>
                        <div
                          class="col-sm-12 text-white"><?php echo $CLICSHOPPING_Reviews->getDef('text_count_vote_no') . ' ' . $count_vote_no . ' - ' . round($percentage_no, 2) . '%'; ?></div>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="mt-1"></div>
            </div>
            <?php
          }

          if ($QTotalIa->valueInt('count_ia') > 0) {
            $count_ia_yes = $QTotalIa->valueInt('vote_ia_yes');
            $count_ia_no = $QTotalIa->valueInt('vote_ia_no');
            $percentage_ia_yes =  ($QTotalIa->valueInt('vote_ia_yes') / $QTotalIa->valueInt('total_ia_votes')) * 100;
            $percentage_ia_no = ($QTotalIa->valueInt('vote_ia_no') / $QTotalIa->valueInt('total_ia_votes')) * 100;
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
                                class="col-sm-12 text-white"><?php echo $CLICSHOPPING_Reviews->getDef('text_count_vote_yes') . ' ' . $count_ia_yes . ' - ' . round($percentage_ia_yes, 2) . '%'; ?></div>
                        <div
                                class="col-sm-12 text-white"><?php echo $CLICSHOPPING_Reviews->getDef('text_count_vote_no') . ' ' . $count_ia_no . ' - ' . round($percentage_ia_no, 2) . '%'; ?></div>
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="mt-1"></div>
            </div>
            <?php
          }

          echo $CLICSHOPPING_Hooks->output('Reviews', 'ReportVote');
          ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                            LISTING DES AVIS CLIENTS                                             -->
  <!-- //################################################################################################################ -->
  <?php echo HTML::form('delete_all_vote', $CLICSHOPPING_Reviews->link('Reviews&DeleteAllVote&page=' . $page)); ?>

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
    data-show-export="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-checkbox="true" data-field="state"></th>
      <th data-field="selected" data-sortable="true" data-visible="false"
          data-switchable="false"><?php echo $CLICSHOPPING_Reviews->getDef('id'); ?></th>
      <th><?php echo $CLICSHOPPING_Reviews->getDef('id'); ?></th>
      <th data-field="products"
          data-sortable="true"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_products'); ?></th>
      <th data-field="product_name"
          data-sortable="true"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_products_name'); ?></th>
      <th data-field="voteYes"
          data-sortable="true"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_vote_yes'); ?></th>
      <th data-field="voteNo"
          data-sortable="true"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_vote_no'); ?></th>
      <th data-field="percentYes"
          data-sortable="true" class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_percentage_yes'); ?></th>
      <th data-field="percentNo"
          data-sortable="true" class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_percentage_no'); ?></th>

      <th data-field="IaYes"
          data-sortable="true" class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_ai_vote_yes'); ?></th>
      <th data-field="YaNo"
          data-sortable="true" class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_ai_vote_no'); ?></th>
      <th data-field="percentIaYes"
          data-sortable="true" class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_percentage_ai_yes'); ?></th>
      <th data-field="percentIaNo"
          data-sortable="true" class="text-center"><?php echo $CLICSHOPPING_Reviews->getDef('table_heading_percentage_ai_no'); ?></th>


    </tr>
    </thead>
    <tbody>
    <?php
      $QReport = $CLICSHOPPING_Reviews->db->prepare('select distinct SQL_CALC_FOUND_ROWS  r.id,
                                                                                          r.products_id,
                                                                                          r.reviews_id,
                                                                                          SUM(CASE WHEN r.vote = 1 AND r.reviews_id <> 0 THEN 1 ELSE 0 END) AS vote_yes,
                                                                                          SUM(CASE WHEN r.vote = 0 AND r.reviews_id <> 0 THEN 1 ELSE 0 END) AS vote_no,
                                                                                          SUM(CASE WHEN r.vote IN (0, 1) AND r.reviews_id <> 0 THEN 1 ELSE 0 END) AS total_votes,
                                                                                          SUM(CASE WHEN r.sentiment = 1 and r.reviews_id = 0 THEN 1 ELSE 0 END) AS ia_yes,
                                                                                          SUM(CASE WHEN r.sentiment = 0 and r.reviews_id = 0 THEN 1 ELSE 0 END) AS ia_no,
                                                                                          SUM(CASE WHEN r.sentiment IN (0, 1) and r.reviews_id = 0 THEN 1 ELSE 0 END) AS total_ai_votes,
                                                                                          p.products_image
                                                      from :table_reviews_vote r,
                                                           :table_products p
                                                      where r.products_id = p.products_id
                                                      group by r.products_id
                                                      order by r.products_id desc                                                    
                                                      limit :page_set_offset, :page_set_max_results
                                                      ');

        $QReport->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
        $QReport->execute();

        $listingTotalRow = $QReport->getPageSetTotalRows();

        if ($listingTotalRow > 0) {
          while ($QReport->fetch()) {
            $count_vote_yes = $QReport->valueInt('vote_yes');
            $count_vote_no = $QReport->valueInt('vote_no');
            $percentage_yes =  ($QReport->valueInt('vote_yes') /  $QReport->valueInt('total_votes')) * 100;
            $percentage_no = ($QReport->valueInt('vote_no') /  $QReport->valueInt('total_votes')) * 100;

            $count_ai_yes = $QReport->valueInt('ia_yes');
            $count_ai_no = $QReport->valueInt('ia_no');
            $percentage_ai_yes =  ($QReport->valueInt('ia_yes') / $QReport->valueInt('total_ai_votes')) * 100;
            $percentage_ai_no = ($QReport->valueInt('ia_no') /  $QReport->valueInt('total_ai_votes')) * 100;
        ?>
            <td></td>
            <td><?php echo $QReport->valueInt('products_id'); ?></td>
            <td><?php echo $QReport->valueInt('products_id'); ?></td>

            <td><?php echo HTML::image($CLICSHOPPING_Template->getDirectoryShopTemplateImages() . $QReport->value('products_image'), $QReport->value('products_name'), (int)SMALL_IMAGE_WIDTH_ADMIN, (int)SMALL_IMAGE_HEIGHT_ADMIN); ?></td>
            <td><?php echo  $CLICSHOPPING_ProductsAdmin->getProductsName($QReport->valueInt('products_id')); ?></td>
            <td><?php echo $count_vote_yes;?></td>
            <td><?php echo $count_vote_no;?></td>
            <td><?php echo round($percentage_yes, 2);?> %</td>
            <td><?php echo round($percentage_no, 2);?> %</td>
            <td><?php echo $count_ai_yes;?></td>
            <td><?php echo $count_ai_no;?></td>
            <td><?php echo round($percentage_ai_yes, 2);?> %</td>
            <td><?php echo round($percentage_ai_no, 2);?> %</td>
          </tr>
          <?php
        }
      }
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $QReport->getPageSetLabel($CLICSHOPPING_Reviews->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $QReport->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>
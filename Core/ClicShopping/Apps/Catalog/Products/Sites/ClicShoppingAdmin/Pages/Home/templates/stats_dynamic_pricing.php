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
use ClicShopping\OM\DateTime;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Products = Registry::get('Products');
$CLICSHOPPING_Db = Registry::get('Db');
$CLICSHOPPING_Image = Registry::get('Image');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/stats_products_purchased.gif', $CLICSHOPPING_Products->getDef('heading_dynamic_pricing_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Products->getDef('heading_dynamic_pricing_title'); ?></span>
          <span
            class="col-md-7 text-end">
             <?php 
             echo HTML::link($CLICSHOPPING_Products->link('DynamicPricingRules&DeleteStatsDynamicPricing&resetHistory=1'), HTML::button($CLICSHOPPING_Products->getDef('button_delete_all'), 'danger'));
	           ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="date_added"
    data-sort-order="desc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true"
    data-show-export="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-switchable="false" width="50"></th>
      <th data-field="products"
          data-sortable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_products'); ?></th>
<!--
      <th data-field="rules_id"
          data-sortable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_id'); ?></th>
-->
      <th data-field="base_price"
          data-sortable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_base_price'); ?></th>
      <th data-field="dynamic_price"
          data-sortable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_dynamic_price'); ?></th>
      <th data-field="rule_applied" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rule_applied'); ?></th>
      <th data-field="date_added" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_last_date'); ?></th>
      <th data-field="occurences" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_total_occurrences'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Products->getDef('table_heading_action'); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qhistory = $CLICSHOPPING_Products->db->prepare('SELECT SQL_CALC_FOUND_ROWS dpr.rules_id,
                                                                                dph.products_id,
                                                                                p.products_image,
                                                                                pd.products_name,
                                                                                COUNT(dph.products_id) AS total_occurrences,
                                                                                MAX(dph.date_added) AS last_date,
                                                                                MAX(dph.base_price) AS base_price,
                                                                                MAX(dph.dynamic_price) AS dynamic_price,
                                                                                MAX(dph.rule_applied) AS rule_applied
                                                 FROM :table_dynamic_pricing_history dph
                                                 JOIN :table_dynamic_pricing_rules dpr 
                                                   ON dpr.rules_id = dph.rules_id
                                                 JOIN :table_products p 
                                                   ON p.products_id = dph.products_id
                                                 JOIN :table_products_description pd 
                                                   ON pd.products_id = p.products_id
                                                 WHERE pd.language_id = :language_id
                                                 GROUP BY dph.products_id
                                                 ORDER BY last_date DESC
                                                 LIMIT :page_set_offset, :page_set_max_results
                                                ');

    $Qhistory->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $Qhistory->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qhistory->execute();

    $listingTotalRow = $Qhistory->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($history = $Qhistory->fetch()) {
        ?>
        <tr>

          <td><?php echo $CLICSHOPPING_Image->getSmallImageAdmin($history['products_id']); ?></td>
          <td><?php echo HTML::link( CLICSHOPPING::link(null, 'A&Catalog\Products&Preview&pID=' . $history['products_id']), $history['products_name']); ?></td>
          <td><?php echo $history['base_price']; ?></td>
          <td><?php echo $history['dynamic_price']; ?></td>
          <td class="text-center"><?php echo $history['rule_applied']; ?></td>
          <td class="text-center"><?php echo DateTime::toShort($history['last_date']); ?></td>
          <td class="text-end"><?php echo $history['total_occurrences']; ?></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group">
              <?php
              echo HTML::link( $CLICSHOPPING_Products->link('Preview&pID=' . $history['products_id']),'<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Products->getDef('icon_preview') . '"></i></h4>');
              ?>
            </div>
          </td>
        </tr>
        <?php
      }
    }

    ?>
    </tbody>
  </table>
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qhistory->getPageSetLabel($CLICSHOPPING_Products->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qhistory->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>
</form>
<div class="py-4"></div>
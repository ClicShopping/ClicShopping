<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Weight\Classes\ClicShoppingAdmin\WeightAdmin;

$CLICSHOPPING_Weight = Registry::get('Weight');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/weight.png', $CLICSHOPPING_Weight->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Weight->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
            <?php
              echo HTML::button($CLICSHOPPING_Weight->getDef('button_insert_weight'), null, $CLICSHOPPING_Weight->link('WeightInsert&page=' . $page), 'primary') . ' ';
              echo HTML::button($CLICSHOPPING_Weight->getDef('button_insert_class'), null, $CLICSHOPPING_Weight->link('ClassInsert&page=' . $page), 'success');
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING                                                            -->
  <!-- //################################################################################################################ -->

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="symbol"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true"
    data-search="true">

  <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="id"><?php echo $CLICSHOPPING_Weight->getDef('table_heading_weight_class_id'); ?></th>
      <th data-field="symbol"
          data-sortable="true"><?php echo $CLICSHOPPING_Weight->getDef('table_heading_weight_class_symbol'); ?></th>
      <th data-field="type"
          data-sortable="true"><?php echo $CLICSHOPPING_Weight->getDef('table_heading_weight_class_type'); ?></th>
      <th data-field="class_to_id"><?php echo $CLICSHOPPING_Weight->getDef('table_heading_weight_class_to_id'); ?></th>
      <th data-field="rule"><?php echo $CLICSHOPPING_Weight->getDef('table_heading_weight_class_rule'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Weight->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qweight = $CLICSHOPPING_Weight->db->prepare('select SQL_CALC_FOUND_ROWS  wc.weight_class_id,
                                                                                 wc.weight_class_key,
                                                                                 wc.language_id,
                                                                                 wc.weight_class_title,
                                                                                 tc.weight_class_from_id,
                                                                                 tc.weight_class_to_id,
                                                                                 tc.weight_class_rule
                                                  from :table_weight_classes wc,
                                                       :table_weight_classes_rules tc 
                                                  where wc.weight_class_id = tc.weight_class_from_id
                                                  and wc.language_id = :language_id
                                                  limit :page_set_offset,
                                                        :page_set_max_results
                                                  ');
    $Qweight->bindInt(':language_id', $CLICSHOPPING_Language->getID());
    $Qweight->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qweight->execute();

    $listingTotalRow = $Qweight->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qweight->fetch()) {
        if ((!isset($_GET['wID']) || (isset($_GET['wID']) && ((int)$_GET['wID'] === $Qweight->valueInt('weight_id')))) && !isset($trInfo)) {
          $trInfo = new ObjectInfo($Qweight->toArray());
        }

        $weight_class_title = WeightAdmin::getTitle($Qweight->valueInt('weight_class_to_id'), $CLICSHOPPING_Language->getID());
        ?>
        <tr>
          <td scope="row"><?php echo $Qweight->valueInt('weight_class_id'); ?></td>
          <td><?php echo $Qweight->value('weight_class_key'); ?></td>
          <td><?php echo $Qweight->value('weight_class_title'); ?></td>
          <td><?php echo $weight_class_title; ?></td>
          <td><?php echo $Qweight->value('weight_class_rule'); ?></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_Weight->link('ClassEdit&page=' . $page . '&wID=' . $Qweight->valueInt('weight_class_id') . '&tID=' . $Qweight->valueInt('weight_class_to_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Weight->getDef('icon_edit') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_Weight->link('WeightEdit&page=' . $page . '&wID=' . $Qweight->valueInt('weight_class_id')), '<h4><i class="bi bi-pencil-fill" title="' . $CLICSHOPPING_Weight->getDef('icon_edit_class_title') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_Weight->link('ClassDelete&page=' . $page . '&wID=' . $Qweight->valueInt('weight_class_id') . '&tID=' . $Qweight->valueInt('weight_class_to_id')), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Weight->getDef('icon_delete') . '"></i></h4>');
              echo '&nbsp;';
              ?>
            </div>
          </td>
        </tr>
        <?php
      } // end while
    }
    ?>
    </tbody>
  </table>
  <div class="mt-1"></div>
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qweight->getPageSetLabel($CLICSHOPPING_Weight->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qweight->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>

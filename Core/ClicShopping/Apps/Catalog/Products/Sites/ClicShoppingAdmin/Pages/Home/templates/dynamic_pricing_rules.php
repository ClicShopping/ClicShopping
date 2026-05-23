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

$CLICSHOPPING_Products = Registry::get('Products');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/produit.gif', $CLICSHOPPING_Products->getDef('heading_title'), '40', '40'); ?></span>
          <span class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Products->getDef('heading_title'); ?></span>
          <span class="col-md-6 text-end">
            <?php echo HTML::button($CLICSHOPPING_Products->getDef('button_insert'), null, $CLICSHOPPING_Products->link('EditDynamicPricingRules&Insert'), 'success') . '&nbsp;'; ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="row">
    <div class="col-md-12 mainTitle">
      <?php echo $CLICSHOPPING_Products->getDef('text_info_heading_rules'); ?>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <?php echo HTML::form('delete_all', $CLICSHOPPING_Products->link('DynamicPricingRules&DeleteAll')); ?>
      <div id="toolbar" class="float-end">
        <button id="button" class="btn btn-danger"><?php echo $CLICSHOPPING_Products->getDef('button_delete'); ?></button>
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
        data-sort-name="sort_order"
        data-toolbar="#toolbar"
        data-buttons-class="primary"
        data-show-toggle="true"
        data-show-columns="true"
        data-mobile-responsive="true"
        data-check-on-init="true">

        <thead class="dataTableHeadingRow">
        <tr>
          <th data-checkbox="true" data-field="state"></th>
          <th data-field="selected" data-sortable="true" data-visible="false" data-switchable="false"><?php echo $CLICSHOPPING_Products->getDef('id'); ?></th>
          <th data-field="rules_name"  data-sortable="true" data-switchable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_name'); ?></th>
          <th data-field="rules_condition"  data-sortable="true" data-switchable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_condition'); ?></th>
          <th data-field="rules_type"  data-sortable="true" data-switchable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_type'); ?></th>
          <th data-field="rules_value"  data-sortable="true" data-switchable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_value'); ?></th>
          <th data-field="rules_priority"  data-sortable="true" data-switchable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_priority'); ?></th>
          <th data-field="rules_status"  data-sortable="true" data-switchable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_status'); ?></th>
          <th data-field="rules_customer_group"  data-sortable="true" data-switchable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_customer_group'); ?></th>
          <th data-field="rules_special"  data-sortable="true" data-switchable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_special'); ?></th>
          <th data-field="heading_action"  data-sortable="true" data-switchable="true" class="text-end"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_action'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        $Qrules = $CLICSHOPPING_Products->db->prepare('select SQL_CALC_FOUND_ROWS rules_id,
                                                                               rules_name,
                                                                               rules_condition,
                                                                               rules_type,
                                                                               rules_value,
                                                                               rules_priority,
                                                                               rules_status,
                                                                               rules_status_special,
                                                                               customers_group,
                                                                               rules_status_promotion
                                                    from :table_dynamic_pricing_rules
                                                    order by rules_priority asc
                                                    limit :page_set_offset,
                                                          :page_set_max_results
                                                   ');

        $Qrules->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
        $Qrules->execute();

        $listingTotalRow = $Qrules->getPageSetTotalRows();

        if ($listingTotalRow > 0) {
          while ($Qrules->fetch()) {
            ?>
            <tr>
              <td data-field="state"></td>
              <td data-field="rules_id"><?php echo $Qrules->valueInt('rules_id'); ?></td>
              <td data-field="rules_name"><?php echo $Qrules->valueProtected('rules_name'); ?></td>
              <td data-field="rules_condition"><?php echo $Qrules->valueProtected('rules_condition'); ?></td>
              <td data-field="rules_type"><?php echo $Qrules->valueProtected('rules_type'); ?></td>
              <td data-field="rules_value"><?php echo $Qrules->valueProtected('rules_value'); ?></td>
              <td><?php echo $Qrules->valueInt('rules_priority'); ?></td>

              <td class="text-center">
                <?php
                if ($Qrules->valueInt('rules_status') == 1) {
                  echo '<a href="' . $CLICSHOPPING_Products->link('DynamicPricingRules&SetFlag&flag=0&rID=' . $Qrules->valueInt('rules_id')) . '"><i class="bi-check text-success"></i></a>';
                } else {
                  echo '<a href="' . $CLICSHOPPING_Products->link('DynamicPricingRules&SetFlag&flag=1&rID=' . $Qrules->valueInt('rules_id')) . '"><i class="bi bi-x text-danger"></i></a>';
                }
                ?>
              </td>

              <td class="text-center">
                <?php
                if ($Qrules->valueInt('customers_group') == 1) {
                  echo '<a href="' . $CLICSHOPPING_Products->link('DynamicPricingRules&SetFlagCustomerGroup&flag=0&rID=' . $Qrules->valueInt('rules_id')) . '"><i class="bi-check text-success"></i></a>';
                } else {
                  echo '<a href="' . $CLICSHOPPING_Products->link('DynamicPricingRules&SetFlagCustomerGroup&flag=1&rID=' . $Qrules->valueInt('rules_id')) . '"><i class="bi bi-x text-danger"></i></a>';
                }
                ?>
              </td>

              <td class="text-center">
                <?php
                  if ($Qrules->valueInt('rules_status_promotion') == 1) {
                    echo '<a href="' . $CLICSHOPPING_Products->link('DynamicPricingRules&SetFlagSpecialPrice&flag=0&rID=' . $Qrules->valueInt('rules_id')) . '"><i class="bi-check text-success"></i></a>';
                  } else {
                  echo '<a href="' . $CLICSHOPPING_Products->link('DynamicPricingRules&SetFlagSpecialPrice&flag=1&rID=' . $Qrules->valueInt('rules_id')) . '"><i class="bi bi-x text-danger"></i></a>';
                }
                ?>
              </td>

              <td class="text-end">
                <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
                  <?php
                  echo HTML::link($CLICSHOPPING_Products->link('EditDynamicPricingRules&rID=' . $Qrules->valueInt('rules_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Products->getDef('icon_edit') . '"></i></h4>');
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
    </div>
  </div>

  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qrules->getPageSetLabel($CLICSHOPPING_Products->getDef('text_display_number_of_link')); ?></div>
        <div class="float-end text-end"><?php echo $Qrules->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>

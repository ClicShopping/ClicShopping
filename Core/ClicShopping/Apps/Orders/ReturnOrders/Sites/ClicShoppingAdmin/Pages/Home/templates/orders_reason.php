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

$CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
              <span
                class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/order_status.gif', $CLICSHOPPING_ReturnOrders->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ReturnOrders->getDef('heading_title'); ?></span>
          <span
            class="col-md-7 text-end">
              <?php
              echo HTML::button($CLICSHOPPING_ReturnOrders->getDef('button_insert'), null, $CLICSHOPPING_ReturnOrders->link('OrdersReasonEdit'), 'success');
              echo '&nbsp;';
              echo HTML::button($CLICSHOPPING_ReturnOrders->getDef('button_back'), null, $CLICSHOPPING_ReturnOrders->link('ReturnOrders'), 'primary');
              ?>
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
    data-sort-name="status"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="status"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_orders_status'); ?></th>
      <th data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_return_orders_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $QReaseonStatus = $CLICSHOPPING_ReturnOrders->db->prepare('select SQL_CALC_FOUND_ROWS return_reason_id,
                                                                                             language_id,
                                                                                             name
                                                                  from :table_return_orders_reason 
                                                                  where language_id = :language_id
                                                                  order by return_reason_id
                                                                  limit :page_set_offset,
                                                                       :page_set_max_results
                                                                  ');

    $QReaseonStatus->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $QReaseonStatus->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $QReaseonStatus->execute();


    $listingTotalRow = $QReaseonStatus->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($QReaseonStatus->fetch()) {
        echo '<tr>';

        echo '                <th scope="row">' . $QReaseonStatus->value('name') . '</th>' . "\n";
        ?>
        <td class="text-end">
          <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
            <?php
            echo '<a href="' . $CLICSHOPPING_ReturnOrders->link('OrdersReasonEdit&page=' . (int)$page . '&oID=' . $QReaseonStatus->valueInt('return_reason_id')) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_ReturnOrders->getDef('icon_edit') . '"></i></h4></a>';
            echo '&nbsp;';
            echo '<a href="' . $CLICSHOPPING_ReturnOrders->link('OrdersReason&DeleteConfirm&page=' . (int)$page . '&oID=' . $QReaseonStatus->valueInt('return_reason_id')) . '"><h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_ReturnOrders->getDef('image_delete') . '"></i></h4></a>';
            ?>
          </div>
        </td>
        </tr>
        <?php
      }
    } // end $listingTotalRow
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $QReaseonStatus->getPageSetLabel($CLICSHOPPING_ReturnOrders->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $QReaseonStatus->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>

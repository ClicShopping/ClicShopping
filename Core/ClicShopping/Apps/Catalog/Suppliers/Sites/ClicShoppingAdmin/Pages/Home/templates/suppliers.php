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

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Suppliers = Registry::get('Suppliers');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

  if ($CLICSHOPPING_MessageStack->exists('main')) {
    echo $CLICSHOPPING_MessageStack->get('main');
  }

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/suppliers.gif', $CLICSHOPPING_Suppliers->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Suppliers->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
            <?php echo HTML::button($CLICSHOPPING_Suppliers->getDef('button_new'), null, $CLICSHOPPING_Suppliers->link('Edit'), 'success'); ?>
           </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- ################# -->
  <!-- Hooks Stats - just use execute function to display the hook-->
  <!-- ################# -->
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <?php echo $CLICSHOPPING_Hooks->output('Stats', 'StatsSuppliers', null, 'display'); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                            LISTING                                                           -->
  <!-- //################################################################################################################ -->

  <?php echo HTML::form('delete_all', $CLICSHOPPING_Suppliers->link('Suppliers&DeleteAll&page=' . $page)); ?>

  <div id="toolbar" class="float-end">
    <button id="button" class="btn btn-danger"><?php echo $CLICSHOPPING_Suppliers->getDef('button_delete'); ?></button>
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
    data-sort-name="suppliers"
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
          data-switchable="false"><?php echo $CLICSHOPPING_Suppliers->getDef('id'); ?></th>
      <th data-switchable="false"></th>
      <th data-field="suppliers"
          data-sortable="true"><?php echo $CLICSHOPPING_Suppliers->getDef('table_heading_suppliers'); ?></th>
      <th data-field="products"
          data-sortable="true"><?php echo $CLICSHOPPING_Suppliers->getDef('table_heading_products_suppliers'); ?></th>
      <th data-field="manager"><?php echo $CLICSHOPPING_Suppliers->getDef('table_heading_manager'); ?></th>
      <th data-field="phone"><?php echo $CLICSHOPPING_Suppliers->getDef('table_heading_phone'); ?></th>
      <th data-field="fax"><?php echo $CLICSHOPPING_Suppliers->getDef('table_heading_fax'); ?></th>
      <th data-field="email"><?php echo $CLICSHOPPING_Suppliers->getDef('table_heading_email_address'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Suppliers->getDef('table_heading_status'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Suppliers->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qsuppliers = $CLICSHOPPING_Suppliers->db->prepare('select SQL_CALC_FOUND_ROWS  *
                                                          from :table_suppliers
                                                          order by suppliers_name
                                                          limit :page_set_offset, :page_set_max_results
                                                          ');

    $Qsuppliers->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qsuppliers->execute();

    $listingTotalRow = $Qsuppliers->getPageSetTotalRows();

    if ($listingTotalRow > 0) {

      while ($Qsuppliers->fetch()) {
        if ((!isset($_GET['mID']) || (isset($_GET['mID']) && ((int)$_GET['mID'] == $Qsuppliers->valueInt('suppliers_id')))) && !isset($mInfo)) {

          $Qproducts = $CLICSHOPPING_Suppliers->db->get('products', 'count(*) as products_count', ['suppliers_id' => $Qsuppliers->valueInt('suppliers_id')]);

          $mInfo_array = array_merge($Qsuppliers->toArray(), $Qproducts->toArray());
          $mInfo = new ObjectInfo($mInfo_array);
        }
        ?>
        <tr>
          <td></td>
          <td><?php echo $Qsuppliers->valueInt('suppliers_id'); ?></td>
          <td>
            <?php
            if (!empty($Qsuppliers->value('suppliers_image'))) {
              echo HTML::image($CLICSHOPPING_Template->getDirectoryShopTemplateImages() . $Qsuppliers->value('suppliers_image'), $Qsuppliers->value('suppliers_name'), (int)SMALL_IMAGE_WIDTH_ADMIN, (int)SMALL_IMAGE_HEIGHT_ADMIN);
            }
            ?>
          </td>
          <td scope="row"><?php echo $Qsuppliers->value('suppliers_name'); ?></td>
          <td>
            <?php
            $Qproducts = $CLICSHOPPING_Suppliers->db->prepare('select count(products_id) as count
                                                       from :table_products
                                                       where suppliers_id = :suppliers_id
                                                     ');
            $Qproducts->bindInt(':suppliers_id', $Qsuppliers->valueInt('suppliers_id'));

            $Qproducts->execute();

            echo $Qproducts->valueInt('count');
            ?>
          </td>
          <td><?php echo $Qsuppliers->value('suppliers_manager'); ?></td>
          <td><?php echo $Qsuppliers->value('suppliers_phone'); ?></td>
          <td><?php echo $Qsuppliers->value('suppliers_fax'); ?></td>
          <td><?php echo $Qsuppliers->value('suppliers_email_address'); ?></td>
          <td class="text-center">
            <?php
            if ($Qsuppliers->valueInt('suppliers_status') == '0') {
              echo '<a href="' . $CLICSHOPPING_Suppliers->link('Suppliers&SetFlag&page=' . $page . '&flag=1&id=' . $Qsuppliers->valueInt('suppliers_id')) . '"><i class="bi-check text-success"></i></a>';
            } else {
              echo '<a href="' . $CLICSHOPPING_Suppliers->link('Suppliers&SetFlag&page=' . $page . '&flag=0&id=' . $Qsuppliers->valueInt('suppliers_id')) . '"><i class="bi bi-x text-danger"></i></a>';
            }
            ?>
          </td>
          <td class="text-end">
            <?php
            echo '<a href="' . $CLICSHOPPING_Suppliers->link('Edit&page=' . $page . '&mID=' . $Qsuppliers->valueInt('suppliers_id')) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Suppliers->getDef('icon_edit') . '"></i></h4></a>';
            echo '&nbsp;';
            ?>
          </td>
        </tr>
        <?php
      } // end while
    } // end $listingTotalRow
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qsuppliers->getPageSetLabel($CLICSHOPPING_Suppliers->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qsuppliers->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>

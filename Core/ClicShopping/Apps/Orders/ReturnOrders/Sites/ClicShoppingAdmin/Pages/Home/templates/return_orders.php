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
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Image = Registry::get('Image');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$languages = $CLICSHOPPING_Language->getLanguages();
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <div
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/rma.png', $CLICSHOPPING_ReturnOrders->getDef('heading_title'), '40', '40'); ?></div>
          <div
            class="col-md-3 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ReturnOrders->getDef('heading_title'); ?></div>

          <div class="col-md-4">
            <?php
              if (AdministratorAdmin::getAdminIdByAccess() === 1) {
                echo HTML::button($CLICSHOPPING_ReturnOrders->getDef('button_return_action_status'), null, $CLICSHOPPING_ReturnOrders->link('OrdersAction'), 'primary');
                echo '&nbsp;';
                echo HTML::button($CLICSHOPPING_ReturnOrders->getDef('button_return_status'), null, $CLICSHOPPING_ReturnOrders->link('OrdersStatus'), 'primary');
                echo '&nbsp;';
                echo HTML::button($CLICSHOPPING_ReturnOrders->getDef('button_return_reason_status'), null, $CLICSHOPPING_ReturnOrders->link('OrdersReason'), 'primary');
                echo '&nbsp;';
              }
            ?>
          </div>
          <div class="col-md-2">
            <?php
            echo HTML::form('search', $CLICSHOPPING_ReturnOrders->link('ReturnOrders'), 'post', 'role="form" ', ['session_id' => true]);
            echo HTML::inputField('search', null, 'id="inputKeywords" placeholder=" ' . $CLICSHOPPING_ReturnOrders->getDef('heading_title_search') . ' "');
            ?>
              </form>
          </div>
          <div class="col-md-2 text-end">
            <?php
            if (isset($_POST['search']) && !\is_null($_POST['search'])) {
              echo HTML::button($CLICSHOPPING_ReturnOrders->getDef('button_reset'), null, $CLICSHOPPING_ReturnOrders->link('ReturnOrders&page=' . $page), 'warning');
            }
            echo '&nbsp;';
            echo HTML::button($CLICSHOPPING_ReturnOrders->getDef('button_archive'), null, $CLICSHOPPING_ReturnOrders->link('Archives'), 'info');
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING DES produits                                      -->
  <!-- //################################################################################################################ -->
  <?php echo HTML::form('delete_all', $CLICSHOPPING_ReturnOrders->link('ReturnOrders&DeleteAll&page=' . $page)); ?>

  <div id="toolbar" class="float-end">
    <button id="button"
            class="btn btn-danger"><?php echo $CLICSHOPPING_ReturnOrders->getDef('button_delete'); ?></button>
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
          data-switchable="false"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_return_orders_id'); ?></th>
      <th data-switchable="false"></th>
      <th data-field="ref"
          data-sortable="true"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_return_orders_ref'); ?></th>
      <th data-field="order_id"
          data-sortable="true"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_return_orders_order_id'); ?></th>
      <th data-field="customer"
          data-sortable="true"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_return_orders_customer'); ?></th>
      <th data-field="model"
          data-sortable="true"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_return_orders_model'); ?></th>
      <th data-field="products_name" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_return_orders_products_name'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_return_orders_status'); ?></th>
      <th data-field="opened" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_return_orders_date_opened'); ?></th>
      <th data-field="added" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_return_orders_date_added'); ?></th>
      <th data-field="modified" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_return_orders_date_modified'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_ReturnOrders->getDef('table_heading_return_orders_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $search = '';

    if (isset($_POST['search']) && !\is_null($_POST['search'])) {
      $keywords = HTML::sanitize($_POST['search']);

      $Qreturn = $CLICSHOPPING_ReturnOrders->db->prepare('select SQL_CALC_FOUND_ROWS r.return_id,
                                                                                       r.return_ref,
                                                                                         r.order_id,
                                                                                         r.customer_id,
                                                                                         r.customer_firstname,
                                                                                         r.customer_lastname,
                                                                                         r.product_id,
                                                                                         r.product_model,
                                                                                         r.product_name,
                                                                                         r.return_status_id,
                                                                                         r.date_ordered,
                                                                                         r.date_added,
                                                                                         r.date_modified,
                                                                                         r.archive
                                                             from :table_return_orders r
                                                             where r.archive = 0
                                                             and (r.product_model like :search 
                                                                  or r.product_name like :search 
                                                                  or r.customer_lastname  like :search
                                                                  or r.customer_firstname like :search
                                                                  or r.return_ref like :search
                                                                 )
                                                             order by r.date_modified DESC
                                                             limit :page_set_offset,
                                                                  :page_set_max_results
                                                            ');

      $Qreturn->bindInt(':archive', 0);
      $Qreturn->bindValue(':search', '%' . $keywords . '%');
      $Qreturn->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $Qreturn->execute();

    } else {
      $Qreturn = $CLICSHOPPING_ReturnOrders->db->prepare('select SQL_CALC_FOUND_ROWS r.return_id,
                                                                                       r.return_ref,
                                                                                       r.order_id,
                                                                                       r.customer_id,
                                                                                       r.customer_firstname,
                                                                                       r.customer_lastname,
                                                                                       r.product_id,
                                                                                       r.product_model,
                                                                                       r.product_name,
                                                                                       r.return_status_id,
                                                                                       r.date_ordered,
                                                                                       r.date_added,
                                                                                       r.date_modified,
                                                                                       r.archive,
                                                                                       r.opened
                                                             from :table_return_orders r
                                                             where r.archive = 0
                                                             order by r.date_modified DESC
                                                             limit :page_set_offset,
                                                                  :page_set_max_results
                                                        ');

      $Qreturn->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);

      $Qreturn->execute();
    }

    $listingTotalRow = $Qreturn->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qreturn->fetch()) {
        $QstatusName = $CLICSHOPPING_ReturnOrders->db->prepare('select name
                                                                  from :table_return_orders_status
                                                                  where return_status_id = :return_status_id
                                                                  and language_id = :language_id
                                                                 ');
        $QstatusName->bindInt(':return_status_id', $Qreturn->valueInt('return_status_id'));
        $QstatusName->bindInt(':language_id', $CLICSHOPPING_Language->getId());
        $QstatusName->execute();
        ?>
        <tr>
          <td></td>
          <td><?php echo $Qreturn->valueInt('return_id'); ?></td>
          <td></td>
          <td><?php echo $Qreturn->value('return_ref'); ?></td>
          <td><?php echo $Qreturn->valueInt('order_id'); ?></td>
          <td><?php echo Hash::displayDecryptedDataText($Qreturn->value('customer_firstname')) . ' ' . Hash::displayDecryptedDataText($Qreturn->value('customer_lastname')); ?></td>
          <td><?php echo $Qreturn->value('product_model'); ?></td>
          <td><?php echo $Qreturn->value('product_name'); ?></td>
          <td><?php echo $QstatusName->value('name'); ?></td>
          <td class="text-center">
            <?php
            if ($Qreturn->valueInt('opened') === 1) {
              echo '<a href="' . $CLICSHOPPING_ReturnOrders->link('ReturnOrders&SetFlag&flag=0&rID=' . $Qreturn->valueInt('return_id')) . '"><i class="bi-check text-success"></i></a>';
            } else {
              echo '<a href="' . $CLICSHOPPING_ReturnOrders->link('ReturnOrders&SetFlag&flag=1&rID=' . $Qreturn->valueInt('return_id')) . '"><i class="bi bi-x text-danger"></i></a>';
            }
            ?>

          </td>
          <?php
          if (!\is_null($Qreturn->value('date_added'))) {
            echo '<td class="text-center">' . DateTime::toShort($Qreturn->value('date_added')) . '</td>';
          } else {
            echo '<td class="text-center"></td>';
          }
          ?>

          <?php
          if (!\is_null($Qreturn->value('date_modified'))) {
            echo '<td class="text-center">' . DateTime::toShort($Qreturn->value('date_modified')) . '</td>';
          } else {
            echo '<td class="text-center"></td>';
          }
          ?>
          <th class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_ReturnOrders->link('EditReturnOrders&rID=' . $Qreturn->valueInt('return_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_ReturnOrders->getDef('icon_edit') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_ReturnOrders->link('ReturnOrders&Archive&rID=' . $Qreturn->valueInt('return_id')), '<h4><i class="bi bi-archive" title="' . $CLICSHOPPING_ReturnOrders->getDef('icon_archive_to') . '"></i></h4>');
              ?>
            </div>
          </th>
        </tr>
        <?php
      } // end while
    } // end $listingTotalRow
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qreturn->getPageSetLabel($CLICSHOPPING_ReturnOrders->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qreturn->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>

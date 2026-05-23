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

use ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin\GroupsB2BAdmin;
use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\OrderAdmin;

$CLICSHOPPING_Orders = Registry::get('Orders');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Language = Registry::get('Language');

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

$orders_statuses = [];
$orders_status_array = [];

$QordersStatus = $CLICSHOPPING_Orders->db->prepare('select orders_status_id,
                                                             orders_status_name,
                                                             authorize_to_delete_order
                                                      from :table_orders_status
                                                      where language_id = :language_id
                                                      ');
$QordersStatus->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$QordersStatus->execute();

while ($QordersStatus->fetch() !== false) {
  $orders_statuses[] = [
    'id' => $QordersStatus->valueInt('orders_status_id'),
    'text' => $QordersStatus->value('orders_status_name')
  ];

  $orders_status_array[$QordersStatus->valueInt('orders_status_id')] = $QordersStatus->value('orders_status_name');
}

if (isset($_GET['oID']) && is_numeric($_GET['oID']) && ($_GET['oID'] > 0)) {
  $oID = HTML::sanitize($_GET['oID']);

  $Qorders = $CLICSHOPPING_Orders->db->get('orders', ['orders_id', 'customers_group_id'], ['orders_id' => (int)$oID]);

  if ($Qorders->fetch()) {
    Registry::set('Order', new OrderAdmin($Qorders->valueInt('orders_id')));
    $order = Registry::get('Order');
  } else {
    $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_order_does_not_exist', ['order_id' => $oID]), 'error');
  }
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <span class="row col-md-12">
          <div
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/client.gif', $CLICSHOPPING_Orders->getDef('heading_title_'), '40', '40'); ?></div>
          <div
            class="col-md-2 pageHeading">
            <?php
            if (isset($_GET['aID'])) {
              echo '&nbsp;' . $CLICSHOPPING_Orders->getDef('heading_title_archive');
            } else {
              echo '&nbsp;' . $CLICSHOPPING_Orders->getDef('heading_title');
            }
            ?>
          </div>
          <div class="col-md-5">
           <span class="col-md-3 float-start">
              <?php
              echo HTML::form('orders', $CLICSHOPPING_Orders->link('Orders'), 'post', ' role="form"', ['session_id' => true]);
              echo HTML::inputField('orders_id', '', 'id="inputKeywords" placeholder="' . $CLICSHOPPING_Orders->getDef('heading_title_search') . '"');
              echo HTML::hiddenField('action', 'edit');
              echo '</form>';
              ?>
            </span>
            <span class="col-md-3 float-start">
                <?php
                // Permettre l'affichage des couleurs des groupes en mode B2B
                if (MODE_B2B_B2C == 'True') {
                  echo HTML::form('grouped', $CLICSHOPPING_Orders->link('Orders'), 'post', ' role="form"');
                  echo HTML::selectField('customers_group_id', GroupsB2BAdmin::getCustomersGroup($CLICSHOPPING_Orders->getDef('visitor_name')), '', 'onchange="this.form.submit();"');
                  echo '</form>';
                }
                ?>
            </span>
            <span class="col-md-3 float-start">
              <?php
              echo HTML::form('status', $CLICSHOPPING_Orders->link('Orders'), 'post', ' role="form"', ['session_id' => true]);
              echo HTML::selectField('status', array_merge(array(array('id' => '0', 'text' => $CLICSHOPPING_Orders->getDef('text_all_orders'))), $orders_statuses), '', 'onchange="this.form.submit();"');
              echo '</form>';
              ?>
            </span>
          </div>

        <div class="col-md-4">
          <?php
          if (isset($_POST['customers_group_id']) || isset($_POST['orders_id']) || isset($_POST['status']) || isset($_GET['aID'])) {
            ?>
            <span class="col-md-6 text-end" id="buttonReset">
                      <?php echo HTML::button($CLICSHOPPING_Orders->getDef('button_reset'), null, $CLICSHOPPING_Orders->link('Orders'), 'danger'); ?>
                    </span>
            <?php
          }
          ?>
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
          <?php echo $CLICSHOPPING_Hooks->output('Stats', 'StatsOrdersStatus', null, 'display'); ?>
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
    data-sort-name="orders"
    data-sort-order="desc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="orders" data-sortable="true"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_orders'); ?>
        &nbsp;
      </th>
      <th data-field="customers"
          data-sortable="true"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_customers'); ?>&nbsp;
      </th>
      <th data-field="support" data-sortable="true"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_support'); ?>
        &nbsp;
      </th>
      <th data-field="guest"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_guest'); ?>&nbsp;</th>
      <?php
      if (MODE_B2B_B2C == 'True') {
        ?>
        <th data-field="group" data-sortable="true"
            class="text-center"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_color_group'); ?></th>
        <?php
      } else {
        ?>
        <th></th>
        <?php
      }
      ?>
      <th data-field="order_total"
          class="text-end"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_order_total'); ?></th>
      <th data-field="dae_purchased"
          class="text-center"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_date_purchased'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-end"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_status'); ?>&nbsp;
      </th>
      <th data-field="erp" class="text-center"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_erp'); ?>&nbsp;
      </th>
      <th data-field="realised_by"
          class="text-end"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_realised_by'); ?>&nbsp;
      </th>
      <th data-field="action" data-switchable="false" data-width="150"
          class="text-end"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    if (isset($_GET['aID'])) {
      $archive_id = HTML::sanitize($_GET['aID']);
    } else {
      $archive_id = 0;
    }

    if (isset($_GET['cID'])) {
      $cID = HTML::sanitize($_GET['cID']);
    } elseif (isset($_POST['cID'])) {
      $cID = HTML::sanitize($_POST['cID']);
    } else {
      $cID = '';
    }

    if (!empty($cID)) {
      $Qorders = $CLICSHOPPING_Orders->db->prepare('select SQL_CALC_FOUND_ROWS o.orders_id,
                                                                                o.customers_id,
                                                                                o.customers_name,
                                                                                o.customers_company,
                                                                                o.customers_id,
                                                                                o.customers_group_id,
                                                                                o.payment_method,
                                                                                o.date_purchased,
                                                                                o.last_modified,
                                                                                o.currency,
                                                                                o.currency_value,
                                                                                s.orders_status_name,
                                                                                ot.text as order_total,
                                                                                o.erp_invoice
                                                       from :table_orders o left join :table_orders_total ot on (o.orders_id = ot.orders_id),
                                                            :table_orders_status s
                                                       where o.customers_id = :customers_id
                                                       and o.orders_status = s.orders_status_id
                                                       and s.language_id = :language_id
                                                       and o.orders_archive = :orders_archive
                                                       and (ot.class = :class or ot.class = :class1)
                                                       order by o.orders_id DESC
                                                       limit :page_set_offset,
                                                             :page_set_max_results
                                                      ');

      $Qorders->bindInt(':customers_id', $cID);
      $Qorders->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qorders->bindInt(':orders_archive', $archive_id);
      $Qorders->bindValue(':class', 'ot_total');
      $Qorders->bindValue(':class1', 'TO'); //total order

    } elseif (isset($_POST['customers_group_id'])) {
      $customers_group_id = HTML::sanitize($_POST['customers_group_id']);
      $Qorders = $CLICSHOPPING_Orders->db->prepare('select SQL_CALC_FOUND_ROWS o.orders_id,
                                                                                o.customers_id,
                                                                                o.customers_name,
                                                                                o.customers_group_id,
                                                                                o.customers_company,
                                                                                o.payment_method,
                                                                                o.date_purchased,
                                                                                o.last_modified,
                                                                                o.currency,
                                                                                o.currency_value,
                                                                                s.orders_status_name,
                                                                                ot.text as order_total,
                                                                                o.erp_invoice
                                                     from :table_orders o left join :table_orders_total ot on (o.orders_id = ot.orders_id),
                                                          :table_orders_status s
                                                     where o.orders_status = s.orders_status_id
                                                     and s.language_id = :language_id
                                                     and o.customers_group_id = :customers_group_id
                                                     and o.orders_archive = :orders_archive
                                                     and (ot.class = :class or ot.class = :class1)
                                                     order by o.orders_id DESC
                                                     limit :page_set_offset,
                                                           :page_set_max_results
                                                    ');

      $Qorders->bindInt(':customers_group_id', $customers_group_id);
      $Qorders->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qorders->bindInt(':orders_archive', $archive_id);
      $Qorders->bindValue(':class', 'ot_total');
      $Qorders->bindValue(':class1', 'TO');

    } elseif (isset($_POST['status'])) {
      $status = HTML::sanitize($_POST['status']);

      if ($status == 0) {
        $Qorders = $CLICSHOPPING_Orders->db->prepare('select SQL_CALC_FOUND_ROWS o.orders_id,
                                                                                  o.customers_id,
                                                                                  o.customers_name,
                                                                                  o.customers_group_id,
                                                                                  o.customers_company,
                                                                                  o.payment_method,
                                                                                  o.date_purchased,
                                                                                  o.last_modified,
                                                                                  o.currency,
                                                                                  o.currency_value,
                                                                                  s.orders_status_name,
                                                                                  ot.text as order_total,
                                                                                  o.erp_invoice
                                                       from :table_orders o left join :table_orders_total ot on (o.orders_id = ot.orders_id),
                                                            :table_orders_status s
                                                       where o.orders_status = s.orders_status_id
                                                       and s.language_id = :language_id
                                                       and o.orders_archive = :orders_archive
                                                       and (ot.class = :class or ot.class = :class1)
                                                       order by o.orders_id DESC
                                                       limit :page_set_offset,
                                                             :page_set_max_results
                                                    ');

        $Qorders->bindInt(':language_id', $CLICSHOPPING_Language->getId());
        $Qorders->bindInt(':orders_archive', $archive_id);
        $Qorders->bindValue(':class', 'ot_total');
        $Qorders->bindValue(':class1', 'TO');
      } else {
        $Qorders = $CLICSHOPPING_Orders->db->prepare('select SQL_CALC_FOUND_ROWS o.orders_id,
                                                                                    o.customers_id,
                                                                                    o.customers_name,
                                                                                    o.customers_group_id,
                                                                                    o.customers_company,
                                                                                    o.payment_method,
                                                                                    o.date_purchased,
                                                                                    o.last_modified,
                                                                                    o.currency,
                                                                                    o.currency_value,
                                                                                    s.orders_status_name,
                                                                                    ot.text as order_total,
                                                                                    o.erp_invoice
                                                           from :table_orders o left join :table_orders_total ot on (o.orders_id = ot.orders_id),
                                                                :table_orders_status s
                                                           where o.orders_status = s.orders_status_id
                                                           and s.language_id = :language_id
                                                           and s.orders_status_id = :orders_status_id
                                                           and o.orders_archive = :orders_archive
                                                           and (ot.class = :class or ot.class = :class1)
                                                           order by o.orders_id DESC
                                                           limit :page_set_offset,
                                                                 :page_set_max_results
                                                        ');

        $Qorders->bindInt(':orders_status_id', $status);
        $Qorders->bindInt(':language_id', $CLICSHOPPING_Language->getId());
        $Qorders->bindInt(':orders_archive', $archive_id);
        $Qorders->bindValue(':class', 'ot_total');
        $Qorders->bindValue(':class1', 'TO');
      }
    } elseif (isset($_POST['orders_id'])) {
      $orders_id = HTML::sanitize($_POST['orders_id']);

      $Qorders = $CLICSHOPPING_Orders->db->prepare('select SQL_CALC_FOUND_ROWS  o.orders_id,
                                                                                  o.customers_id,
                                                                                  o.customers_name,
                                                                                  o.customers_group_id,
                                                                                  o.customers_company,
                                                                                  o.payment_method,
                                                                                  o.date_purchased,
                                                                                  o.last_modified,
                                                                                  o.currency,
                                                                                  o.currency_value,
                                                                                  s.orders_status_name,
                                                                                  ot.text as order_total,
                                                                                  o.erp_invoice
                                                       from :table_orders o left join :table_orders_total ot on (o.orders_id = ot.orders_id),
                                                            :table_orders_status s
                                                       where o.orders_status = s.orders_status_id
                                                       and s.language_id = :language_id
                                                       and o.orders_id = :orders_id
                                                       and o.orders_archive = :orders_archive
                                                       and (ot.class = :class or ot.class = :class1)
                                                       order by o.orders_id DESC
                                                       limit :page_set_offset,
                                                             :page_set_max_results
                                                      ');

      $Qorders->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qorders->bindInt(':orders_archive', $archive_id);
      $Qorders->bindInt(':orders_id', $orders_id);
      $Qorders->bindValue(':class', 'ot_total');
      $Qorders->bindValue(':class1', 'TO');
    } else {
      $Qorders = $CLICSHOPPING_Orders->db->prepare('select SQL_CALC_FOUND_ROWS  o.orders_id,
                                                                                  o.customers_id,
                                                                                  o.customers_name,
                                                                                  o.customers_group_id,
                                                                                  o.customers_company,
                                                                                  o.payment_method,
                                                                                  o.date_purchased,
                                                                                  o.last_modified,
                                                                                  o.currency,
                                                                                  o.currency_value,
                                                                                  s.orders_status_name,
                                                                                  s.orders_status_id,
                                                                                  ot.text as order_total,
                                                                                  o.erp_invoice
                                                       from :table_orders o left join :table_orders_total ot on (o.orders_id = ot.orders_id),
                                                            :table_orders_status s
                                                       where o.orders_status = s.orders_status_id
                                                       and s.language_id = :language_id
                                                       and o.orders_archive = :orders_archive
                                                       and (ot.class = :class or ot.class = :class1)
                                                       order by o.orders_id DESC
                                                       limit :page_set_offset,
                                                             :page_set_max_results
                                                      ');

      $Qorders->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $Qorders->bindInt(':orders_archive', $archive_id);
      $Qorders->bindValue(':class', 'ot_total');
      $Qorders->bindValue(':class1', 'TO');
    }

    $Qorders->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qorders->execute();

    $listingTotalRow = $Qorders->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qorders->fetch()) {
        $Qcustomers = $CLICSHOPPING_Orders->db->prepare('select customers_id,
                                                                  customers_group_id,
                                                                  customer_guest_account
                                                           from :table_customers
                                                           where customers_id = :customers_id
                                                         ');
        $Qcustomers->bindInt(':customers_id', $Qorders->valueInt('customers_id'));
        $Qcustomers->execute();

// select the last update by the admin name
        $Qhistory = $CLICSHOPPING_Orders->db->prepare('select osh.admin_user_name,
                                                                osh.orders_id,
                                                                o.orders_id,
                                                                osh.orders_status_id,
                                                                osh.orders_status_support_id
                                                         from :table_orders_status_history osh,
                                                              :table_orders o
                                                         where osh.orders_id = o.orders_id
                                                         and o.orders_id = :orders_id
                                                         order by osh.date_added desc
                                                         limit 1
                                                        ');
        $Qhistory->bindInt(':orders_id', $Qorders->valueInt('orders_id'));
        $Qhistory->execute();

// Selectionne la couleur selon le groupe client au moment de la commande
        if ($Qorders->valueInt('customers_group_id') != 0) {
          $Qcolor = $CLICSHOPPING_Orders->db->prepare('select color_bar
                                                         from :table_customers_groups
                                                         where customers_group_id = :customers_group_id
                                                        ');
          $Qcolor->bindInt(':customers_group_id', $Qorders->valueInt('customers_group_id'));
          $Qcolor->execute();
        }
        ?>
        <th scope="row"><?php echo $Qorders->valueInt('orders_id'); ?></th>
        <td><?php echo Hash::displayDecryptedDataText($Qorders->value('customers_name')) . '&nbsp;(' . Hash::displayDecryptedDataText($Qorders->value('customers_company')) . ')'; ?></td>
       <?php
        if ($Qhistory->valueInt('orders_status_support_id') > 1) {
          $QCustomerSupport = $CLICSHOPPING_Orders->db->prepare('select oss.orders_status_support_name
                                                                   from :table_orders_status_history osh,
                                                                        :table_orders_status_support oss
                                                                   where osh.orders_status_support_id = :orders_status_support_id
                                                                   and osh.orders_status_support_id = oss.orders_status_support_id
                                                                   and oss.language_id = :language_id
                                                                   order by osh.date_added desc
                                                                    limit 1
                                                                  ');

          $QCustomerSupport->bindInt(':orders_status_support_id', $Qhistory->valueInt('orders_status_support_id'));
          $QCustomerSupport->bindInt(':language_id', $CLICSHOPPING_Language->getId());
          $QCustomerSupport->execute();
          ?>
          <td><?php echo $QCustomerSupport->value('orders_status_support_name'); ?></td>
          <?php
        } else {
          ?>
          <td></td>
          <?php
        }

        if ($Qcustomers->value('customer_guest_account') == 0) {
          ?>
          <td class="text-center" width="15"></td>
          <?php
        } else {
          ?>
          <td class="text-center" width="15"><i class="bi-check text-success"></i></td>
          <?php
        }

// Permettre l'affichage couleurs du groupe B2B auquel le client ce trouvait au moment de la commande
        if (MODE_B2B_B2C == 'True') {
          if ($Qorders->valueInt('customers_group_id') != 0) {
            ?>
            <td class="text-center">
              <table width="15" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td bgcolor="<?php echo $Qcolor->value('color_bar'); ?>"></td>
                </tr>
              </table>
            </td>
            <?php
          } else {
            ?>
            <td></td>
            <?php
          }
        } else {
          ?>
          <td></td>
          <?php
        }
        ?>
        <td class="text-end"><?php echo strip_tags($Qorders->value('order_total')); ?></td>
        <?php
        if (!\is_null($Qorders->value('date_purchased'))) {
          echo '<td class="text-center">' . DateTime::toShort($Qorders->value('date_purchased')) . '</td>';
        } else {
          echo '<td class="text-center"></td>';
        }

        if ($Qorders->valueInt('orders_status_id') == 1) {
          ?>
          <td class="text-end"><span
              class="badge bg-primary"><?php echo $Qorders->value('orders_status_name'); ?></span></td>
          <?php
        } elseif ($Qorders->valueInt('orders_status') == 2) {
          ?>
          <td class="text-end"><span
              class="badge bg-warning"><?php echo $Qorders->value('orders_status_name'); ?></span></td>
          <?php
        } elseif ($Qorders->valueInt('orders_status_id') == 3) {
          ?>
          <td class="text-end"><span
              class="badge bg-success"><?php echo $Qorders->value('orders_status_name'); ?></span></td>
          <?php
        } elseif ($Qorders->valueInt('orders_status_id') == 4) {
          ?>
          <td class="text-end"><span
              class="badge bg-danger"><?php echo $Qorders->value('orders_status_name'); ?></span></td>
          <?php
        } else {
          ?>
          <td class="text-end"><span
              class="badge bg-info"><?php echo $Qorders->value('orders_status_name'); ?></span></td>
          <?php
        }

        if ($Qorders->valueInt('erp_invoice') == 1) {
          ?>
          <td
            class="text-center"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'icons/odoo_order.png', $CLICSHOPPING_Orders->getDef('image_orders_erp')); ?></td>
          <?php
        } elseif ($Qorders->valueInt('erp_invoice') == 2) {
          ?>
          <td
            class="text-center"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'icons/odoo_invoice.png', $CLICSHOPPING_Orders->getDef('image_orders_invoice_manual_erp')); ?></td>
          <?php
        } elseif ($Qorders->valueInt('erp_invoice') == 3) {
          ?>
          <td
            class="text-center"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'icons/odoo.png', $CLICSHOPPING_Orders->getDef('image_orders_invoice_erp')); ?></td>
          <?php
        } elseif ($Qorders->valueInt('erp_invoice') == 4) {
          ?>
          <td
            class="text-center"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'icons/odoo_invoice_cancelled.png', $CLICSHOPPING_Orders->getDef('image_orders_invoice_cancel_erp')); ?></td>
          <?php
        } else {
          ?>
          <td></td>
          <?php
        }
        ?>
        <td class="text-end"><?php echo $Qhistory->value('admin_user_name'); ?></td>
        <td class="text-end">
          <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
            <?php
            echo HTML::link(ClicShopping::link('index.php?A&Customers\Customers&Edit&cID=' . $Qorders->valueInt('customers_id')), '<h4><i class="bi bi-person" title="' . $CLICSHOPPING_Orders->getDef('icon_edit_customer') . '"></i></h4>');
            echo '&nbsp;';
            echo HTML::link($CLICSHOPPING_Orders->link('Edit&oID=' . $Qorders->valueInt('orders_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Orders->getDef('icon_edit') . '"></i></h4>');
            echo '&nbsp;';
            echo HTML::link($CLICSHOPPING_Orders->link('Invoice&oID=' . $Qorders->valueInt('orders_id')), '<h4><i class="bi bi-box" title="' . $CLICSHOPPING_Orders->getDef('icon_invoice') . '"></i></h4>', 'target="_blank" rel="noreferrer"');
            echo '&nbsp;';
            echo HTML::link($CLICSHOPPING_Orders->link('PackingSlip&oID=' . $Qorders->valueInt('orders_id')), '<h4><i class="bi bi-box2" title="' . $CLICSHOPPING_Orders->getDef('icon_packingslip') . '"></i></h4>', 'target="_blank" rel="noreferrer"');
            echo '&nbsp;';

            if ($archive_id != 1) {
              echo HTML::link($CLICSHOPPING_Orders->link('Archive&oID=' . $Qorders->valueInt('orders_id')), '<h4><i class="bi bi-archive" title="' . $CLICSHOPPING_Orders->getDef('icon_archive_to') . '"></i></h4>');
            } else {
              echo HTML::link($CLICSHOPPING_Orders->link('Orders&Unpack&oID=' . $Qorders->valueInt('orders_id')), '<h4><i class="bi bi-archive-fill" title="' . $CLICSHOPPING_Orders->getDef('icon_archive_to') . '"></i></h4>');
            }

            $QordersStatus = $CLICSHOPPING_Orders->db->prepare('select authorize_to_delete_order
                                                                  from :table_orders_status
                                                                  where orders_status_id = :orders_status_id    
                                                                  ');
            $QordersStatus->bindInt(':orders_status_id', $Qhistory->valueInt('orders_status_id'));
            $QordersStatus->execute();

            if ($QordersStatus->valueInt('authorize_to_delete_order') == 1) {
              echo HTML::link($CLICSHOPPING_Orders->link('Delete&oID=' . $Qorders->valueInt('orders_id')), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Orders->getDef('icon_delete') . '"></i></h4>');
            } else {
              echo '&nbsp;&nbsp;';
            }
            ?>
          </div>
        </td>
        </tr>
        <?php
      } // while
    } // end $listingTotalRow
    ?>
    </tbody>
  </table>

  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qorders->getPageSetLabel($CLICSHOPPING_Orders->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qorders->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
  <!-- ################# -->
  <!-- Hooks Invoice - just use execute function to display the hook-->
  <!-- ################# -->
  <div class="col-md-12">
    <div class="card card-block headerCard">
      <div>
        <?php
        // Batch Print order
        echo $CLICSHOPPING_Hooks->output('Invoice', 'InvoiceBatch');
        ?>
      </div>
    </div>
  </div>
  <div class="py-4"></div>
</div>

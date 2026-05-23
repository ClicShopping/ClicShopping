<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\ClicShoppingAdmin\Tax;
use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\OrderAdmin;
use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\UpdateOrder as UpdateOrderService;
use ClicShopping\Apps\Orders\Orders\Classes\Common\EInvoiceService;

$CLICSHOPPING_Orders = Registry::get('Orders');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Currencies = Registry::get('Currencies');
$CLICSHOPPING_Address = Registry::get('Address');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Image = Registry::get('Image');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

// ── Invoice lock flag for the view ───────────────────────────────────────
// Computed once here so the template doesn't need to import UpdateOrder.
// The actual mutations are handled by the UpdateOrder action controller
// (Actions/Orders/UpdateOrder.php) — this file is display-only.

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

$order_id = HTML::sanitize($_GET['oID']);

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$orders_statuses = [];
$orders_status_array = [];

$QordersStatus = $CLICSHOPPING_Orders->db->prepare('select orders_status_id,
                                                            orders_status_name
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

if (isset($order_id) && is_numeric($order_id) && ($order_id > 0)) {
  $oID = (int)$order_id;

  $Qorders = $CLICSHOPPING_Orders->db->get('orders', 'orders_id', ['orders_id' => (int)$oID]);

  if ($Qorders->fetch()) {
    Registry::set('Order', new OrderAdmin($Qorders->valueInt('orders_id')));
    $order = Registry::get('Order');
  } else {
    $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_order_does_not_exist', ['order_id' => $oID]), 'error');
  }
}

// ── Invoice lock guard ──────────────────────────────────────────────────────
// An order whose invoice status is >= 1 (validated / cancelled / credit-note)
// must NOT be edited: French law (art. L441-9 C.com.) prohibits modifying a
// validated invoice. The admin can only unlock it by reverting the invoice
// status to 0 (pending) on the Status tab before making any changes.
$order_is_invoice_locked = isset($oID) ? UpdateOrderService::isInvoiceLocked((int)$oID) : false;

// orders_invoice status Dropdown
$orders_invoice_statuses = [];
$orders_status_invoice_array = [];

$QordersStatusInvoice = $CLICSHOPPING_Orders->db->prepare('select orders_status_invoice_id,
                                                                  orders_status_invoice_name
                                                           from :table_orders_status_invoice
                                                           where language_id = :language_id
                                                          ');
$QordersStatusInvoice->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$QordersStatusInvoice->execute();

while ($QordersStatusInvoice->fetch()) {
  $orders_invoice_statuses[] = [
    'id' => $QordersStatusInvoice->valueInt('orders_status_invoice_id'),
    'text' => $QordersStatusInvoice->value('orders_status_invoice_name')
  ];

  $orders_status_invoice_array[$QordersStatusInvoice->valueInt('orders_status_invoice_id')] = $QordersStatusInvoice->value('orders_status_invoice_name');
}

$Qcustomers = $CLICSHOPPING_Orders->db->prepare('select c.customers_id,
                                                          o.customers_id,
                                                          o.orders_id
                                                  from :table_customers c,
                                                       :table_orders o
                                                  where c.customers_id = o.customers_id
                                                  and o.orders_id = :orders_id
                                                  limit 1
                                                  ');
$Qcustomers->bindInt(':orders_id', $order_id);
$Qcustomers->execute();
?>

<div class="contentBody">
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/orders.gif', $CLICSHOPPING_Orders->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Orders->getDef('heading_title') . ' #' . (int)$order_id; ?></span>
          <span class="col-md-9 text-end">
            <?php
            if ($Qcustomers->valueInt('customers_id') != 0) {
              echo HTML::button($CLICSHOPPING_Orders->getDef('button_history'), null, $CLICSHOPPING_Orders->link('Orders&cID=' . $Qcustomers->valueInt('customers_id')), 'info', null);
              echo '&nbsp;';
            }

            echo HTML::button($CLICSHOPPING_Orders->getDef('button_invoice'), null, $CLICSHOPPING_Orders->link('Invoice&oID=' . (int)$order_id), 'success', ['newwindow' => true]);
            echo '&nbsp;';
            echo HTML::button($CLICSHOPPING_Orders->getDef('button_packingslip'), null, $CLICSHOPPING_Orders->link('PackingSlip&oID=' . (int)$order_id), 'info', ['newwindow' => true]);
            echo '&nbsp;';
            echo HTML::button($CLICSHOPPING_Orders->getDef('button_back'), null, $CLICSHOPPING_Orders->link('Orders'), 'primary');
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-2"></div>
  <!-- //###########################################//-->
  <!--          Customer information tab  1        //-->
  <!-- //###########################################//-->
  <div id="orderTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Orders->getDef('tab_general'); ?></a></li>
      <li
        class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Orders->getDef('tab_orders_details'); ?></a></li>
      <li
        class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Orders->getDef('tab_statut'); ?></a></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">

        <div class="tab-pane active" id="tab1">
          <div class="mainTitle"><?php echo $CLICSHOPPING_Orders->getDef('title_orders_address'); ?></div>
          <div class="adminformTitle">
            <div class="row">
              <div class="col-md-3 card m-1">
                <div class="card-body">
                  <h4 class="card-title"><?php echo $CLICSHOPPING_Orders->getDef('entry_customer'); ?></h4>
                  <p class="card-text">
                    <strong><?php echo $CLICSHOPPING_Address->addressFormat($order->customer['format_id'], $order->customer, 1, '', '<br />'); ?></strong>
                  </p>
                </div>
                <div class="col-md-12 text-end">
                  <a
                    href="<?php echo $CLICSHOPPING_Orders->link('EditCustomerAddress&order_id=' . (int)$order_id . '&customer_id=' . $Qcustomers->valueInt('customers_id')); ?>"
                    data-bs-toggle="modal" data-refresh="true"
                    data-bs-target="#myModal1"><?php echo '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Orders->getDef('icon_edit') . '"></i></h4>'; ?></a>
                </div>
              </div>
              <div class="col-md-3 card m-1">
                <div class="card-body">
                  <h4 class="card-title"><?php echo $CLICSHOPPING_Orders->getDef('entry_shipping_address'); ?></h4>
                  <p class="card-text">
                    <strong><?php echo $CLICSHOPPING_Address->addressFormat($order->delivery['format_id'], $order->delivery, 1, '', '<br />'); ?></strong>
                  </p>
                </div>
                <div class="col-md-12 text-end">
                  <a
                    href="<?php echo $CLICSHOPPING_Orders->link('EditShippingAddress&order_id=' . (int)$order_id . '&customer_id=' . $Qcustomers->valueInt('customers_id')); ?>"
                    data-bs-toggle="modal" data-refresh="true"
                    data-bs-target="#myModal2"><?php echo '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Orders->getDef('icon_edit') . '"></i></h4>'; ?></a>
                </div>
              </div>
              <div class="col-md-3 card m-1">
                <div class="card-body">
                  <h4 class="card-title"><?php echo $CLICSHOPPING_Orders->getDef('entry_billing_address'); ?></h4>
                  <p class="card-text">
                    <strong><?php echo $CLICSHOPPING_Address->addressFormat($order->billing['format_id'], $order->billing, 1, '', '<br />'); ?></strong>.
                  </p>
                </div>
                <div class="col-md-12 text-end">
                  <a
                    href="<?php echo $CLICSHOPPING_Orders->link('EditPaymentAddress&order_id=' . (int)$order_id . '&customer_id=' . $Qcustomers->valueInt('customers_id')); ?>"
                    data-bs-toggle="modal" data-refresh="true"
                    data-bs-target="#myModal3"><?php echo '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Orders->getDef('icon_edit') . '"></i></h4>'; ?></a>
                </div>
              </div>
              <!-- Modals placed outside cards to avoid Bootstrap positioning conflicts -->
              <div class="modal fade" id="myModal1" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
                   aria-hidden="true">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <div class="modal-body">
                      <div class="te"></div>
                    </div>
                  </div> <!-- /.modal-content -->
                </div><!-- /.modal-dialog -->
              </div><!-- /.modal -->
              <div class="modal fade" id="myModal2" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
                   aria-hidden="true">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <div class="modal-body">
                      <div class="te"></div>
                    </div>
                  </div> <!-- /.modal-content -->
                </div><!-- /.modal-dialog -->
              </div><!-- /.modal -->
              <div class="modal fade" id="myModal3" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
                   aria-hidden="true">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <div class="modal-body">
                      <div class="te"></div>
                    </div>
                  </div> <!-- /.modal-content -->
                </div><!-- /.modal-dialog -->
              </div><!-- /.modal -->
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="row">
            <div class="mainTitle"><?php echo $CLICSHOPPING_Orders->getDef('title_orders_customers'); ?></div>
            <div class="adminformTitle">
              <div class="row" id="tab1ContentRow1">
                <?php
                if (MODE_B2B_B2C == 'False') {
                  ?>
                  <div class="col-md-12" id="siret">
                    <span class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_order_siret'); ?></span>
                    <span class="col-md-5"><strong><?php echo $order->customer['siret']; ?></strong></span>
                  </div>
                  <div class="mt-1"></div>

                  <div class="col-md-12" id="CodeApe">
                    <span class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_order_code_ape'); ?></span>
                    <span class="col-md-5"><strong><?php echo $order->customer['ape']; ?></strong></span>
                  </div>
                  <div class="mt-1"></div>
                  <div class="col-md-12" id="tavIntracom">
                    <span class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_tva_intracom'); ?></span>
                    <span class="col-md-5"><strong><?php echo $order->customer['tva_intracom']; ?></strong></span>
                  </div>
                  <?php
                }
                ?>
                <div class="col-md-8" id="generalSales">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Orders->getDef('text_condition_general_of_sales'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Orders->getDef('text_condition_general_of_sales'); ?></label>
                    <div class="col-md-5">
                      <a
                        href="<?php echo $CLICSHOPPING_Orders->link('PageManagerOrderHistoryContract&order_id=' . (int)$order_id . '&customer_id=' . $Qcustomers->valueInt('customers_id')); ?>"
                        data-bs-toggle="modal" data-refresh="true"
                        data-bs-target="#myModal"><?php echo '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Orders->getDef('icon_edit') . '"></i></h4>'; ?></a>
                      <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
                           aria-hidden="true">
                        <div class="modal-dialog">
                          <div class="modal-content">
                            <div class="modal-body">
                              <div class="te"></div>
                            </div>
                          </div> <!-- /.modal-content -->
                        </div><!-- /.modal-dialog -->
                      </div><!-- /.modal -->
                    </div>
                  </div>
                </div>
                <div class="mt-1"></div>
                <div class="col-md-12" id="phone">
                  <span class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_telephone_number'); ?></span>
                  <span class="col-md-3"><strong><?php echo $order->customer['telephone']; ?></strong></span>
                </div>
                <?php
                if (!empty($order->customer['cellular_phone'])) {
                  ?>
                  <div class="mt-1"></div>
                  <div class="col-md-12" id="cellular">
                    <span
                      class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_cellular_phone_number'); ?></span>
                    <span class="col-md-5"><strong><?php echo$order->customer['cellular_phone']; ?></strong></span>
                  </div>
                  <?php
                }
                ?>
                <div class="mt-1"></div>
                <div class="col-md-2 group-text" id="location">
                  <span class="col-md-6"><?php echo $CLICSHOPPING_Orders->getDef('entry_customer_location'); ?></span>
                  <span class="col-md-6 float-end text-start">
                    <a target="_blank" rel="noreferrer" href="http://maps.google.com/maps?q=<?php echo $order->delivery['street_address'], ',', $order->delivery['postcode'], ',', $order->delivery['state'], ',', $order->delivery['country']; ?>&hl=fr&um=1&ie=UTF-8&sa=N&tab=wl"><h4><i
                          class="bi bi-plus-circle"
                          title="'<?php echo $CLICSHOPPING_Orders->getDef('entry_customer_location'); ?>"></i></h4></a></span>
                </div>
                <div class="clearfix"></div>
                <div class="mt-1"></div>
                <div class="mt-1"></div>
                <div class="col-md-12  group-text" id="emailAddress">
                  <span class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_email_address'); ?></span>
                  <span
                    class="col-md-5"><strong><?php echo '<a href="mailto:' . $order->customer['email_address'] . '"><u>' . $order->customer['email_address'] . '</u></a>'; ?></strong></span>
                </div>
                <div class="mt-1"></div>
                <div class="col-md-12" id="computerIp">
                  <span class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_client_computer_ip'); ?></span>
                  <span class="col-md-5"><strong><?php echo $order->customer['client_computer_ip']; ?></strong></span>
                </div>
                <div class="mt-1"></div>
                <div class="col-md-12" id="provider">
                  <span
                    class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_provider_name_client'); ?></span>
                  <span class="col-md-5"><strong><?php echo $order->customer['provider_name_client']; ?></strong></span>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="row">
            <div class="mainTitle"><?php echo $CLICSHOPPING_Orders->getDef('title_orders_paiement'); ?></div>
            <div class="adminformTitle">
              <div class="row" id="tab1ContentRow2">
                <div class="col-md-12">
                  <span class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_payment_method'); ?></span>
                  <span class="col-md-5"><strong><?php echo $order->info['payment_method']; ?></strong></span>
                </div>
                <?php
                if (!\is_null($order->info['cc_type']) || !\is_null($order->info['cc_owner']) || !\is_null($order->info['cc_number'])) {
                  ?>
                  <div class="col-md-12">
                    <span class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_credit_card_type'); ?></span>
                    <span class="col-md-5"><strong><?php echo $order->info['cc_type']; ?></strong></span>
                  </div>
                  <div class="col-md-12">
                    <span class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_credit_card_owner'); ?></span>
                    <span class="col-md-5"><strong><?php echo $order->info['cc_owner']; ?></strong></span>
                  </div>
                  <div class="col-md-12">
                    <span
                      class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_credit_card_number'); ?></span>
                    <span class="col-md-5"><strong><?php echo $order->info['cc_number']; ?></strong></span>
                  </div>
                  <div class="col-md-12">
                    <span
                      class="col-md-3"><?php echo $CLICSHOPPING_Orders->getDef('entry_credit_card_express'); ?></span>
                    <span class="col-md-5"><strong><?php echo $order->info['cc_expires']; ?></strong></span>
                  </div>
                  <?php
                }
                ?>
                <div class="mt-1"></div>
                <div class="mt-1"></div>
                <div id="contentTab1"></div>

              </div>
            </div>
          </div>
          <?php echo $CLICSHOPPING_Hooks->output('Orders', 'PageContentTab1', null, 'display'); ?>
        </div>
        <!-- //###########################################//-->
        <!--          Order informations  Tab2                                                   //-->
        <!-- //###########################################//-->

        <div class="tab-pane" id="tab2">
          <?php if ($order_is_invoice_locked): ?>
          <!-- ── Read-only notice when invoice is validated ── -->
          <div class="alert alert-warning d-flex align-items-center gap-2 my-2" role="alert">
            <i class="bi bi-lock-fill"></i>
            <span><?php echo $CLICSHOPPING_Orders->getDef('alert_invoice_locked_tab2'); ?></span>
          </div>
          <?php endif; ?>

          <table width="100%" border="0" cellspacing="0" cellpadding="5" class="adminformTitle">
            <tr class="dataTableHeadingRow">
              <?php if (!$order_is_invoice_locked): ?><td></td><?php endif; ?>
              <td></td>
              <td></td>

              <td></td>
              <td></td>
              <td class="text-center"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_products'); ?></td>
              <td></td>
              <td><?php echo $CLICSHOPPING_Orders->getDef('table_heading_products_model'); ?></td>


              <td class="text-end"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_tax'); ?></td>
              <td class="text-end"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_price_excluding_tax'); ?></td>
              <td class="text-end"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_price_including_tax'); ?></td>

              <?php if(!$order_is_invoice_locked) {
                ?>
                <td class="text-center"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_qty_edit'); ?></td>
                <td class="text-center"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_price_edit'); ?></td>
                <?php
              }
              ?>

              <td class="text-end"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_total_excluding_tax'); ?></td>
              <td class="text-end"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_total_including_tax'); ?></td>


              <td></td>

            </tr>
            <?php
            for ($i = 0, $n = \count($order->products); $i < $n; $i++) {
              $products_id         = $order->products[$i]['products_id'];
              $orders_products_id  = (int)$order->products[$i]['orders_products_id'];

              echo '    <tr class="dataTableRow">' . "\n";


              echo
                '      <td class="dataTableContent" vertical-align="top">' . HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&Preview&pID=' . $products_id . '?page=' . $page), '<h4><i class="bi bi-easil3" title="' . $CLICSHOPPING_Orders->getDef('icon_preview') . '"></i></h4>') . '</td>' . "\n" .
                '      <td class="dataTableContent" vertical-align="top">' . HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&Edit&pID=' . $products_id), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Orders->getDef('icon_edit') . '"></i></h4>') . '</td>' . "\n" .
                '      <td class="dataTableContent" vertical-align="top">' . $CLICSHOPPING_Image->getSmallImageAdmin($products_id) . '</td>' . "\n" .
                '      <td class="dataTableContent" vertical-align="top">' . $order->products[$i]['qty'] . '&nbsp;x</td>' . "\n" .
                '      <td class="dataTableContent" colspan="3" vertical-align="top">' . $order->products[$i]['name'];

              if (isset($order->products[$i]['attributes']) && (\count($order->products[$i]['attributes']) > 0)) {
                for ($j = 0, $k = \count($order->products[$i]['attributes']); $j < $k; $j++) {
// attributes reference
                  if ($order->products[$i]['attributes'][$j]['reference'] != '' || $order->products[$i]['attributes'][$j]['reference'] != 'null') {
                    $attributes_reference = '<strong> ' . $order->products[$i]['attributes'][$j]['reference'] . '</strong> - ';
                  }

                  echo '<br /><small>&nbsp;<i> - ' . $attributes_reference . $order->products[$i]['attributes'][$j]['option'] . ': ' . $order->products[$i]['attributes'][$j]['value'];

                  if ($order->products[$i]['attributes'][$j]['price'] != 0) echo ' (' . $order->products[$i]['attributes'][$j]['prefix'] . $CLICSHOPPING_Currencies->format($order->products[$i]['attributes'][$j]['price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . ')';
                  echo '</i></small>';
                }
              }

              echo      '      <td class="dataTableContent" vertical-align="top">' . $order->products[$i]['model'] . '</td>' . "\n";
              // ── Inline edit fields (qty + price) — editable mode only ──
              echo '      </td>' . "\n" .
                '      <td class="text-end dataTableContent">' . $order->products[$i]['tax'] . '</td>' . "\n" .
                '      <td class="text-end dataTableContent"><strong>' . $CLICSHOPPING_Currencies->format($order->products[$i]['final_price'], true, $order->info['currency'], $order->info['currency_value']) . '</strong></td>' . "\n" .
                '      <td class="text-end dataTableContent"><strong>' . $CLICSHOPPING_Currencies->format(Tax::addTax($order->products[$i]['final_price'], $order->products[$i]['tax'], true), true, $order->info['currency'], $order->info['currency_value']) . '</strong></td>' . "\n";


              if (!$order_is_invoice_locked) {
                echo '      <td class="dataTableContent text-center" vertical-align="top">'
                  . HTML::form('edit_prod_' . $orders_products_id, $CLICSHOPPING_Orders->link('Orders&UpdateOrderProduct&UpdateProduct&oID=' . $oID), 'post', 'class="d-inline"')
                  . HTML::hiddenField('orders_products_id', $orders_products_id)
                  . '<input type="number" name="new_qty" value="' . (int)$order->products[$i]['qty'] . '" min="1" step="1" class="form-control form-control-sm d-inline" style="width:70px" />'
                  . '</td>' . "\n"
                  . '      <td class="dataTableContent text-center" vertical-align="top">'
                  . '<input type="number" name="new_price" value="' . number_format((float)$order->products[$i]['final_price'], 4, '.', '') . '" min="0" step="0.0001" class="form-control form-control-sm d-inline" style="width:90px" />'
                  . '</td>' . "\n";

              }


               echo  '      <td class="text-end dataTableContent"><strong>' . $CLICSHOPPING_Currencies->format($order->products[$i]['final_price'] * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</strong></td>' . "\n" .
                '      <td class="text-end dataTableContent"><strong>' . $CLICSHOPPING_Currencies->format(Tax::addTax($order->products[$i]['final_price'], $order->products[$i]['tax'], true) * $order->products[$i]['qty'], true, $order->info['currency'], $order->info['currency_value']) . '</strong></td>' . "\n";


              if (!$order_is_invoice_locked) {
              echo  '      <td class="dataTableContent" vertical-align="top">'
              . '<button type="submit" class="btn btn-sm btn-outline-success p-1" title="' . $CLICSHOPPING_Orders->getDef('button_update') . '">'
              . '<i class="bi bi-check-lg"></i></button>'
              . '</form></td>' . "\n";
              }

              echo '    </tr>' . "\n";
            }
            ?>
          </table>





          <?php
            /*
            if (!$order_is_invoice_locked): ?>
          <!-- ── Add new product line ── -->
          <div class="mt-3 adminformTitle">
            <div class="mainTitle"><?php echo $CLICSHOPPING_Orders->getDef('title_add_product'); ?></div>
            <?php echo HTML::form('add_product', $CLICSHOPPING_Orders->link('Orders&UpdateOrder&AddProduct&oID=' . $oID), 'post', 'class="row g-2 align-items-end mt-1"'); ?>
              <?php echo HTML::hiddenField('order_id', $oID); ?>
              <div class="col-md-3">
                <label class="form-label small"><?php echo $CLICSHOPPING_Orders->getDef('entry_add_product_id'); ?></label>
                <input type="number" name="new_products_id" min="1" step="1" class="form-control form-control-sm" placeholder="<?php echo $CLICSHOPPING_Orders->getDef('placeholder_product_id'); ?>" required />
              </div>
              <div class="col-md-2">
                <label class="form-label small"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_qty_edit'); ?></label>
                <input type="number" name="new_qty" value="1" min="1" step="1" class="form-control form-control-sm" required />
              </div>
              <div class="col-md-3">
                <label class="form-label small"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_price_edit'); ?></label>
                <input type="number" name="new_price" value="0.0000" min="0" step="0.0001" class="form-control form-control-sm" required />
              </div>
              <div class="col-md-2">
                <?php echo HTML::button($CLICSHOPPING_Orders->getDef('button_add_product'), null, null, 'success btn-sm'); ?>
              </div>
            </form>
          </div>
          <?php endif;
            */
            ?>






          <div class="mt-1"></div>
          <table class="table">
            <?php
            foreach ($order->totals as $value) {
              echo '      <tr>' . "\n" .
                '          <td class="text-end"><strong>' . $value['title'] . '</strong></td>' . "\n" .
                '          <td class="text-end"><strong>' . strip_tags($value['text']) . '</strong></td>' . "\n" .
                '        </tr>' . "\n";
            }
            ?>
          </table>
          <div class="col-md-12" id="contentTab2"></div>
          <?php echo $CLICSHOPPING_Hooks->output('Orders', 'PageContentTab2', null, 'display'); ?>
        </div>


        <!-- //###########################################//-->
        <!--          ONGLET statut commande tab3                                        //-->
        <!-- //###########################################//-->
        <div class="tab-pane" id="tab3">
          <div class="mainTitle"><?php echo $CLICSHOPPING_Orders->getDef('table_heading_comments'); ?></div>
          <?php echo HTML::form('status', $CLICSHOPPING_Orders->link('Orders&Update&oID=' . $oID)); ?>
          <div class="adminformTitle" id="StatusOrder">
            <div class="row">
              <div class="col-md-12" id="orderComment">
                <div class="row">
                  <div
                    class="col-md-8"><?php echo HTML::textAreaField('comments', null, '60', '5', 'placeholder="' . $CLICSHOPPING_Orders->getDef('entry_notify_comments') . '"'); ?></div>
                  <div
                    class="col-md-4 text-end"><?php echo HTML::button($CLICSHOPPING_Orders->getDef('button_update'), null, null, 'success'); ?></div>
                </div>
              </div>
              <div class="mt-1"></div>
              <div class="row col-md-12" id="entryStatus"></div>
              <div class="mt-1"></div>
              <div class="col-md-12" id="contentTab3"></div>
              <div class="mt-1"></div>
              <div class="col-md-12" id="StatusNotify">
                <div class="row">
                  <div class="col-md-6">
                    <span id="notifyCustomer">
                      <div class="col-md-8">
                        <div class="form-group row">
                          <label for="<?php echo $CLICSHOPPING_Orders->getDef('entry_notify_customer'); ?>"
                                 class="col-5 col-form-label"><strong><?php echo $CLICSHOPPING_Orders->getDef('entry_notify_customer'); ?></strong></label>
                          <div class="col-md-5">
                            <ul class="list-group-slider list-group-flush">
                              <li class="list-group-item-slider">
                                <label class="switch">
                                  <?php echo HTML::checkboxField('notify_comments', '', true, 'class="success"'); ?>
                                  <span class="slider"></span>
                                </label>
                              </li>
                            </ul>
                          </div>
                        </div>
                      </div>
                    </span>
                  </div>
                  <div class="col-md-6">
                  <span id="notifyComments">
                    <div class="col-md-8">
                      <div class="form-group row">
                        <label for="<?php echo $CLICSHOPPING_Orders->getDef('entry_notify_comments'); ?>"
                               class="col-5 col-form-label"><strong><?php echo $CLICSHOPPING_Orders->getDef('entry_notify_comments'); ?></strong></label>
                        <div class="col-md-5">
                          <ul class="list-group-slider list-group-flush">
                            <li class="list-group-item-slider">
                              <label class="switch">
                                <?php echo HTML::checkboxField('notify_comments', '', true, 'class="success"'); ?>
                                <span class="slider"></span>
                              </label>
                            </li>
                          </ul>
                        </div>
                      </div>
                    </div>
                  </span>
                  </div>
                </div>

                <?php
                //**********************************
                // ── E-Invoice (Chorus Pro) slider — visible only when module is enabled ──
                $eInvoiceService = new EInvoiceService();

                if ($eInvoiceService->isEnabled()) {
                  // Retrieve current numeric invoice status directly from the orders table
                  $QinvoiceStatus = $CLICSHOPPING_Orders->db->prepare('select orders_status_invoice
                                                                        from :table_orders
                                                                        where orders_id = :orders_id
                                                                        ');
                  $QinvoiceStatus->bindInt(':orders_id', $oID);
                  $QinvoiceStatus->execute();
                  $current_invoice_status_id = $QinvoiceStatus->valueInt('orders_status_invoice');

                  $is_b2b = isset($order) ? $eInvoiceService->isB2B($order->customer) : false;
                  $already_sent = $eInvoiceService->isAlreadySent($oID);
                ?>
                <div class="row mt-2" id="notifyEInvoice">
                  <div class="col-md-12">
                    <div class="card border-secondary">

                      <!-- Card header: title + portal button -->
                      <div class="card-header d-flex align-items-center gap-2">
                        <strong>&#127963; <?php echo $CLICSHOPPING_Orders->getDef('card_title'); ?></strong>
                        <span class="ms-2 text-muted small">
                          <?php echo $CLICSHOPPING_Orders->getDef('label_invoice_status'); ?> :
                          <strong><?php echo htmlspecialchars($orders_status_invoice_array[$current_invoice_status_id] ?? '—'); ?></strong>
                        </span>
                        <a href="<?php echo EInvoiceService::CHORUS_PORTAL_URL; ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="btn btn-sm btn-primary ms-auto"
                           title="<?php echo $CLICSHOPPING_Orders->getDef('button_portal'); ?>">
                          <i class="bi bi-box-arrow-up-right me-1"></i><?php echo $CLICSHOPPING_Orders->getDef('button_portal'); ?>
                        </a>
                      </div>

                      <!-- Card body: contextual status message -->
                      <div class="card-body py-2">
                        <?php
                        if (!$is_b2b) {
                        ?>
                          <span class="badge bg-secondary"><?php echo $CLICSHOPPING_Orders->getDef('badge_b2c'); ?></span>
                          &nbsp;<span class="text-muted small"><?php echo $CLICSHOPPING_Orders->getDef('text_b2c_notice'); ?></span>

                        <?php
                        } elseif ($already_sent) {
                          ?>
                          <span class="badge bg-success"><?php echo $CLICSHOPPING_Orders->getDef('badge_transmitted'); ?></span>
                          &nbsp;<span class="text-success small"><?php echo $CLICSHOPPING_Orders->getDef('text_transmitted_notice'); ?></span>

                        <?php
                        } elseif (!in_array($current_invoice_status_id, [EInvoiceService::STATUS_INVOICE, EInvoiceService::STATUS_CANCEL, EInvoiceService::STATUS_CREDIT_NOTE])) {
                          ?>

                          <span class="badge bg-warning text-dark"><?php echo $CLICSHOPPING_Orders->getDef('badge_pending'); ?></span>
                          &nbsp;<span class="text-muted small"><?php echo $CLICSHOPPING_Orders->getDef('text_pending_notice'); ?></span>

                        <?php
                        } else {
                          ?>
                          <span class="badge bg-info"><?php echo $CLICSHOPPING_Orders->getDef('badge_ready'); ?></span>
                          &nbsp;<span class="text-muted small"><?php echo $CLICSHOPPING_Orders->getDef('text_ready_notice'); ?></span>
                        <?php
                        }
                        ?>

                        <?php
                        // Show the E-Invoice slider only if B2B and not yet transmitted
                        if ($is_b2b && !$already_sent) {
                        ?>
                        <div class="mt-2">
                          <div class="form-group row">
                            <label class="col-5 col-form-label">
                              <strong><?php echo $CLICSHOPPING_Orders->getDef('entry_notify_einvoice'); ?></strong>
                            </label>
                            <div class="col-md-5">
                              <ul class="list-group-slider list-group-flush">
                                <li class="list-group-item-slider">
                                  <label class="switch">
                                    <input type="checkbox" name="notify_einvoice" value="1" class="success">
                                    <span class="slider"></span>
                                  </label>
                                </li>
                              </ul>
                            </div>
                          </div>
                        </div>
                        <?php
                        }
                        ?>
                      </div><!-- /card-body -->
                    </div><!-- /card -->
                  </div><!-- /col -->
                </div><!-- /row -->
                <?php
                }
                ?>

              </div>
            </div>
            <div class="mt-1"></div>
            <div id="ErpOrder"></div>
            </form>
          </div>
          <div class="mt-1"></div>
          <div class="mainTitle"><?php echo $CLICSHOPPING_Orders->getDef('title_orders_history'); ?></div>
          <div class="adminformTitle">
            <table class="table table-sm table-hover">
              <thead>
              <tr>
                <td class="smallText text-center">
                  <strong><?php echo $CLICSHOPPING_Orders->getDef('table_heading_date_added'); ?></strong></td>
                <td class="smallText text-center">
                  <strong><?php echo $CLICSHOPPING_Orders->getDef('table_heading_customer_notified'); ?></strong></td>
                <td class="smallText text-center">
                  <strong><?php echo $CLICSHOPPING_Orders->getDef('table_heading_status'); ?></strong></td>
                <td class="smallText text-center">
                  <strong><?php echo $CLICSHOPPING_Orders->getDef('table_heading_comments'); ?></strong></td>
              </tr>
              </thead>
              <tbody>
              <?php
              $QordersHistory = $CLICSHOPPING_Orders->db->prepare('select orders_status_id,
                                                                             orders_status_invoice_id,
                                                                             admin_user_name,
                                                                             date_added,
                                                                             customer_notified,
                                                                             comments,
                                                                             evidence
                                                                      from :table_orders_status_history
                                                                      where orders_id = :orders_id
                                                                      order by date_added
                                                                     ');
              $QordersHistory->bindInt(':orders_id', $oID);
              $QordersHistory->execute();

              if ($QordersHistory->rowCount() > 0) {
                while ($QordersHistory->fetch()) {
                  echo '      <tr>' . "\n" .
                    '        <td class="text-center">' . DateTime::toLong($QordersHistory->value('date_added')) . '</td>' . "\n" .
                    '        <td class="text-center">';

                  if ($QordersHistory->valueInt('customer_notified') === 1) {
                    echo '<i class="bi-check text-success"></i>' . "\n";
                  } else {
                    echo '<i class="bi bi-x text-danger"></i>' . "\n";
                  }

                  echo '        </td>';

                  $content = '<td class="text-center">' . $orders_status_array[$QordersHistory->valueInt('orders_status_id')] . '</td>' . "\n";
                  $content .= '<td id="orderCommentcontentTab3">';
                  $content .= $CLICSHOPPING_Orders->getDef('entry_status_comment_invoice') . $orders_status_invoice_array[$QordersHistory->valueInt('orders_status_invoice_id')] . '<br />';
                  $content .= $CLICSHOPPING_Orders->getDef('entry_status_invoice_realised') . $QordersHistory->value('admin_user_name') . '<br />';
                  $content .= $CLICSHOPPING_Orders->getDef('entry_status_invoice_note') . '<br />';
                  $content .= nl2br(HTML::sanitize($QordersHistory->value('comments'))) . '<br />';

                  echo $content;

                  if (!\is_null($QordersHistory->value('evidence'))) {
                    echo $CLICSHOPPING_Orders->getDef('entry_status_evidence') . '<br />' . HTML::link(CLICSHOPPING::link('../sources/Download/Evidence/' . $QordersHistory->value('evidence')), $QordersHistory->value('evidence')) . '<br />';
                  }

                  echo '        </td>' . "\n" .
                    '      </tr>' . "\n";

                  echo $CLICSHOPPING_Hooks->output('Orders', 'OrderContentHistory');
                }
              } else {
                echo '      <tr>' . "\n" .
                  '        <td colspan="5">' . $CLICSHOPPING_Orders->getDef('text_no_order_history') . '</td>' . "\n" .
                  '      </tr>' . "\n";
              }
              ?>
              </tbody>
            </table>
          </div>

          <?php echo $CLICSHOPPING_Hooks->output('Orders', 'PageContentTab3', null, 'display'); ?>
        </div>
        <!-- //################################################################################################################ -->
        <!-- //                                              Other Tab                                                -->
        <!-- //################################################################################################################ -->
        <?php echo $CLICSHOPPING_Hooks->output('Orders', 'PageTab', null, 'display'); ?>

        <script
          src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/modal_popup.js'); ?>"></script>
      </div>
    </div>
  </div>
  <div class="py-4"></div>
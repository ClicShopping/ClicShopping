<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\Apps\Orders\ReturnOrders\Classes\Shop\ReturnProduct;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\Sites\Shop\Tax;

?>
<div class="col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div class="page-title AccountCustomersHistoryInfo">
    <h3><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_invoice_heading_billing_information'); ?></h3>
  </div>
  <div class="card">
    <div class="card-header">
      <strong><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_invoice_order_number', ['order_id' => (int)$_GET['order_id']]) . ' <span class="badge text-bg-info primary float-end">' . $CLICSHOPPING_Order->info['orders_status'] . '</span>'; ?></strong>
    </div>
    <div class="card-block">
      <div class="mt-1"></div>
      <table border="0" width="100%" cellspacing="0" cellpadding="2" class="table-hover order_confirmation">
        <thead>
        <?php
        if (\count($CLICSHOPPING_Order->info['tax_groups']) > 1) {
          ?>
          <tr>
            <th colspan="2">
              <strong><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_invoice_products'); ?></strong>
            </th>
            <th class="text-end">
              <strong><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_invoice_tax'); ?></strong>
            </th>
            <th class="text-end">
              <strong><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_invoice_order_total'); ?></strong>
            </th>
          </tr>
          <?php
        } else {
        ?>
        <tr>
          <td>
            <strong><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_invoice_action'); ?></strong>
          </td>
          <th colspan="2">
            <strong><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_invoice_products'); ?></strong>
          </th>
          <th class="text-end">
            <strong><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_invoice_order_total'); ?></strong>
          </th>
        </tr>
        </thead>
        <tbody>
        <?php
        }

        for ($i = 0, $n = \count($CLICSHOPPING_Order->products); $i < $n; $i++) {
          $link_product_return = CLICSHOPPING::link(null, 'Account&ProductReturn&product_id=' . $CLICSHOPPING_Order->products[$i]['id'] . '&order_id=' . HTML::sanitize($_GET['order_id']));
          $link_product_return_history = CLICSHOPPING::link(null, 'Account&ProductReturnHistory&order_id=' . HTML::sanitize($_GET['order_id']));
          $result = ReturnProduct::removeButtonHistoryInfo($_GET['order_id'], $CLICSHOPPING_Order->products[$i]['id']);

          echo '       <tr>' . "\n";

          if ($result['opened'] == 1) {
            echo '        <td>' . HTML::button(CLICSHOPPING::getDef('button_return_product_closed'), 'bi bi-question-diamond', null, 'danger', null, 'sm') . '</td>' . "\n";
          } else {
            if ($result['return_status_id'] != 0) {
              echo '        <td>' . HTML::button(CLICSHOPPING::getDef('button_return_product_waiting'), 'bi bi-question-diamond', $link_product_return_history, 'warning', null, 'sm') . '</td>' . "\n";
            } else {
              echo '        <td>' . HTML::button(CLICSHOPPING::getDef('button_return_product'), 'bi bi-question-diamond', $link_product_return, 'primary', null, 'sm') . '</td>' . "\n";
            }
          }

          echo '            <td class="text-end" valign="top" width="30">' . $CLICSHOPPING_Order->products[$i]['qty'] . '&nbsp;x&nbsp;</td>' . "\n" .
            '            <td valign="top">' . $CLICSHOPPING_Order->products[$i]['name'];

          if ((isset($CLICSHOPPING_Order->products[$i]['attributes'])) && (\count($CLICSHOPPING_Order->products[$i]['attributes']) > 0)) {
            for ($j = 0, $n2 = \count($CLICSHOPPING_Order->products[$i]['attributes']); $j < $n2; $j++) {

              if (!empty($CLICSHOPPING_Order->products[$j]['attributes'][$j]['reference'])) {
                $reference = $CLICSHOPPING_Order->products[$j]['attributes'][$j]['reference'] . ' / ';
              } else {
                $reference = '';
              }

              if (!empty($CLICSHOPPING_Order->products[$i]['attributes'][$j]['price'])) {
                $price = '( ' . $CLICSHOPPING_Order->products[$i]['attributes'][$j]['prefix'] . ' ' . $CLICSHOPPING_Order->products[$i]['attributes'][$j]['price'] . ' )';
              } else {
                $price = '';
              }

              echo '<br /><small>&nbsp;<i> - <strong>' . $reference . '</strong>' . $CLICSHOPPING_Order->products[$i]['attributes'][$j]['option'] . ' : ' . $CLICSHOPPING_Order->products[$i]['attributes'][$j]['value'] . $price . '</i></small>';
            }
          }

          echo '</td>' . "\n";

          if (\count($CLICSHOPPING_Order->info['tax_groups']) > 1) {
            echo '            <td class="text-end" valign="top">' . Tax::displayTaxRateValue($CLICSHOPPING_Order->products[$i]['tax']) . '</td>' . "\n";
          }

          echo '            <td class="text-end" valign="top">' . $CLICSHOPPING_Currencies->format(Tax::addTax($CLICSHOPPING_Order->products[$i]['final_price'], $CLICSHOPPING_Order->products[$i]['tax']) * $CLICSHOPPING_Order->products[$i]['qty'], true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value']) . '</td>' . "\n";
          echo '          </tr>' . "\n";
        }
        ?>
        </tbody>
      </table>
      <div style="height:10px;"></div>
      <div class="hr"></div>
      <?php
      // ----------------------
      // --- Total order   -----
      // ----------------------
      ?>
      <table width="100%" class="float-end">
        <?php
        for ($i = 0, $n = \count($CLICSHOPPING_Order->totals); $i < $n; $i++) {
          echo '              <tr>' . "\n" .
            '                   <td class="text-end"  width="80%">' . $CLICSHOPPING_Order->totals[$i]['title'] . '&nbsp;</td>' . "\n" .
            '                   <td class="text-end" width=20%">' . $CLICSHOPPING_Order->totals[$i]['text'] . '</td>' . "\n" .
            '                 </tr>' . "\n";
        }
        ?>
      </table>
    </div>
    <div class="clearfix"></div>
    <div class="card-footer">
      <?php echo CLICSHOPPING::getDef('module_account_customers_history_info_invoice_order_date') . ' ' . DateTime::toLong($CLICSHOPPING_Order->info['date_purchased']); ?>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="hr"></div>
</div>

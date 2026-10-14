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

?>
<div class="col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div class="card">
    <div class="card-header">
      <div>
        <h3><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_download_text_heading_title'); ?></h3>
      </div>
    </div>
    <div class="card-block">
      <div class="mt-1"></div>
      <div class="card-text">
        <table border="0" width="100%" cellspacing="1" cellpadding="2">
          <?php
          do {
// MySQL 3.22 does not have INTERVAL
            list($dt_year, $dt_month, $dt_day) = explode('-', $Qdownloads->value('date_purchased_day'));
            $download_timestamp = mktime(23, 59, 59, $dt_month, $dt_day + $Qdownloads->valueInt('download_maxdays'), $dt_year);
            $download_expiry = date('Y-m-d H:i:s', $download_timestamp);

            echo '      <tr>' . "\n";

// The link will appear only if:
// - Download remaining count is > 0, AND
// - The file is present in the DOWNLOAD directory, AND EITHER
// - No expiry date is enforced (maxdays == 0), OR
// - The expiry date is not reached

            if (($Qdownloads->valueInt('download_count') > 0) && (is_file($CLICSHOPPING_Template->getPathDownloadShopDirectory('Private') . $Qdownloads->value('orders_products_filename'))) && (($Qdownloads->valueInt('download_maxdays') == 0) || ($download_timestamp > time()))) {
              echo '        <td>' . HTML::link(CLICSHOPPING::link(null, 'Products&Download&order=' . $last_order . '&id=' . $Qdownloads->valueInt('orders_products_download_id')), '<strong>' . $Qdownloads->value('products_name') . '</strong>') . '</td>' . "\n";
            } else {
              echo '        <td>' . $Qdownloads->value('products_name') . '</td>' . "\n";
            }

            echo '        <td>' . CLICSHOPPING::getDef('module_account_customers_history_info_download_table_heading_download_date') . ' ' . DateTime::toLong($download_expiry) . '</td>' . "\n" .
              '        <td align="text-end">' . $Qdownloads->valueInt('download_count') . ' ' . CLICSHOPPING::getDef('module_account_customers_history_info_download_able_heading_download_count') . '</td>' . "\n" .
              '      </tr>' . "\n";
          } while ($Qdownloads->fetch());
          ?>
        </table>

        <?php
        if (isset($_GET['Account'], $_GET['HistoryInfo'])) {
          ?>
          <div class="mt-1"></div>
          <p><?php CLICSHOPPING::getDef('module_account_customers_history_info_download_text_download_header_footer_download', ['url' => HTML::link(CLICSHOPPING::link(null, 'Account&Main'), CLICSHOPPING::getDef('module_account_customers_history_info_download_text_download_header_title_my_account'))]); ?></p>
          <?php
        }
        ?>
      </div>
    </div>
  </div>
</div>
<div class="clearfix"></div>
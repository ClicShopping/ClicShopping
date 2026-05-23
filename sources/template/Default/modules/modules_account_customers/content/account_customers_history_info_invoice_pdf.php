<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<div class="col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div class="card">
    <div class="card-header">
      <div class="row">
        <div class="col-md-10 mdouleAccountCustomersHistoryInfoInvoicePdfText">
          <h3><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_invoice_pdf_text'); ?></h3></div>
        <div class="col-md-2 text-end"><?php echo $print_invoice_pdf; ?></div>
      </div>
    </div>
  </div>
</div>

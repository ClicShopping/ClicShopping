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

  $CLICSHOPPING_CompliancePolicyRules = Registry::get('CompliancePolicyRules');
?>
<div class="col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div class="col-md-12">
    <div class="mt-1"></div>
    <div class="modulesTellAFriendCustomerAgreement">
      <?php
        if ($CLICSHOPPING_CompliancePolicyRules->displayPrivacyConditions() === true) {
      ?>
      <ul class="list-group list-group-flush">
        <li class="list-group-item-slider">
          <?php echo CLICSHOPPING::getDef('text_privacy_conditions_description', ['store_name' => STORE_NAME, 'privacy_url' => CLICSHOPPING::link(CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_URL_PRIVACY)]); ?>
          <div class="mt-1"></div>
          <?php echo CLICSHOPPING::getDef('text_privacy_conditions_agree'); ?>
          <label class="switch">
            <?php echo HTML::checkboxField('customer_agree_privacy', null, null, 'id="conditions" required aria-required="true" class="success"'); ?>
            <span class="slider"></span>
          </label>
        </li>
      </ul>
    </div>
    <?php
    }
    ?>
  </div>
</div>

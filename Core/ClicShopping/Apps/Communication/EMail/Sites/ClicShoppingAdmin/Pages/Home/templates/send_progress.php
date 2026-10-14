<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\Apps\Communication\EMail\Classes\ClicShoppingAdmin\SendEmail;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_EMail = Registry::get('EMail');

$CLICSHOPPING_EMail->loadDefinitions('Sites/ClicShoppingAdmin/email');
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span class="col-md-6 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_EMail->getDef('heading_title'); ?></span>
          <span class="col-md-6 text-end"><?php echo HTML::button($CLICSHOPPING_EMail->getDef('button_back'), null, $CLICSHOPPING_EMail->link('EMail'), 'primary'); ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-1"></div>

  <div class="text-center"><strong><?php echo $CLICSHOPPING_EMail->getDef('text_please_wait'); ?></strong></div>

  <?php
  flush();

  // Process one time-bounded slice. When false, recipients remain: re-enter the
  // SendProgress action to continue the resumable send. When true, it is complete.
  $send_complete = (new SendEmail())->sendEmailBatch();

  if ($send_complete === false) {
    echo '<meta http-equiv="refresh" content="2; URL=' . $CLICSHOPPING_EMail->link('SendProgress') . '">';
  } else {
    echo '<p class="text-center text-success"><strong>' . $CLICSHOPPING_EMail->getDef('success_email_sent') . '</strong></p>';
  }
  ?>
</div>

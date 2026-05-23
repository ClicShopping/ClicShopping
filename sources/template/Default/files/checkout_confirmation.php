<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));

echo HTML::form('checkout_confirmation', $form_action_url, 'post', 'id="checkout_confirmation" role="form" onsubmit="return checkCheckBox(this)"');
?>
<section class="checkout_confirmation" id="checkout_confirmation">
  <div class="contentContainer">
    <div class="contentText">
      <div class="page-title"><h1><?php echo CLICSHOPPING::getDef('heading_title_Confirmation'); ?></h1></div>
      <div>
        <?php echo $CLICSHOPPING_Template->getBlocks('modules_checkout_confirmation'); ?>
      </div>
    </div>
    <div class="mt-1"></div>
  </div>
</section>
</form>

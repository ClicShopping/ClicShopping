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

$CLICSHOPPING_Customer = Registry::get('Customer');
$CLICSHOPPING_Currencies = Registry::get('Currencies');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Payment = Registry::get('Payment');
$CLICSHOPPING_Template = Registry::get('Template');

echo $CLICSHOPPING_Payment->javascript_validation();

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));

echo HTML::form('checkout_payment', CLICSHOPPING::link(null, 'Checkout&Confirmation'), 'post', 'role="form" id ="checkout_payment" onsubmit="return check_form();"', ['tokenize' => true]);
?>
<section class="checkout_payment" id="checkout_payment">
  <div class="contentContainer">
    <div class="contentText">
      <?php
      if (isset($_GET['payment_error'])) {
//pb here better with new payment registry : Registry('payment', new Payment($_GET['$_GET['payment_error']']) create a registry error
        $error = $CLICSHOPPING_Payment->get_error();
        ?>
        <div class="alert alert-danger" role="alert">
          <div>
            <?php
            if (!\is_null($error)) {
              echo '<strong>' . HTML::outputProtected($error['title']) . '</strong> ';
              echo HTML::outputProtected($error['error']);
            } else {
              echo CLICSHOPPING::getDef('error_payment_obscur');
            }
            ?>
          </div>
          <div>
            <?php
            if (DISPLAY_CONDITIONS_ON_CHECKOUT == 'true') {
              echo CLICSHOPPING::getDef('text_conditions_description') . '<br />';
            }
            ?>
          </div>
        </div>
        <?php
      }
      ?>
      <div class="page-title"><h1><?php echo CLICSHOPPING::getDef('heading_title_Payment'); ?></h1></div>
      <div>
        <?php echo $CLICSHOPPING_Template->getBlocks('modules_checkout_payment'); ?>
      </div>
    </div>
    <div class="mt-1"></div>
  </div>
</section>
</form>

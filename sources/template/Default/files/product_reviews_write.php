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

$CLICSHOPPING_Template = Registry::get('Template');
$CLICSHOPPING_Customer = Registry::get('Customer');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

if (!$CLICSHOPPING_Customer->isLoggedOn()) {
  CLICSHOPPING::redirect(null, 'Account&LogIn');
}

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

$message_alert = CLICSHOPPING::getDef('text_alert_products_reviews');
$min_caracters_to_write = (int)REVIEW_TEXT_MIN_LENGTH;

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));

echo HTML::form('product_reviews_write', CLICSHOPPING::link(null, 'Products&ReviewsWrite&Process&products_id=' . $CLICSHOPPING_ProductsCommon->getID()), 'post', 'onsubmit="var text = document.getElementById(\'productsReview\').value; if(text.length < ' . $min_caracters_to_write . ') { alert(\'' . $message_alert . '\'); return false; } return true;"', ['tokenize' => true, 'action' => 'process']);
?>
<section class="product_reviews_write" id="product_reviews_write">
  <div class="contentContainer">
    <div class="contentText">
      <div class="page-title"><h1><?php echo CLICSHOPPING::getDef('heading_title_reviews'); ?></h1></div>
      <?php echo $CLICSHOPPING_Template->getBlocks('modules_products_reviews'); ?>
    </div>
  </div>
</section>
</form>
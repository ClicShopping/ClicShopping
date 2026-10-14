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

$CLICSHOPPING_Recommendations = Registry::get('Recommendations');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

if ($CLICSHOPPING_MessageStack->exists('Recommendations')) {
  echo $CLICSHOPPING_MessageStack->get('Recommendations');
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/product_recommendations.png', $CLICSHOPPING_Recommendations->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Recommendations->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="alert alert-warning" role="alert">
    <?php echo $CLICSHOPPING_Recommendations->getDef('text_intro_pr'); ?>
    <?php echo $CLICSHOPPING_Recommendations->getDef('return_url', ['return_url_pr' => CLICSHOPPING::getConfig('http_server') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'index.php?Products&Recommendations']); ?>
  </div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Recommendations->getDef('text_products_recommendations'); ?></strong></div>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>

      <div class="col-md-12">
        <div>
          <div class="col-md-12">
            <?php echo $CLICSHOPPING_Recommendations->getDef('text_intro'); ?>
          </div>
        </div>
        <div class="mt-1"></div>
        <div class="col-md-12">
          <div>
            <div class="col-md-12 text-center">
              <?php
              echo HTML::form('configure', CLICSHOPPING::link(null, 'A&Marketing\Recommendations&Configure'));
              echo HTML::button($CLICSHOPPING_Recommendations->getDef('button_configure'), null, null, 'primary');
              echo '</form>';
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

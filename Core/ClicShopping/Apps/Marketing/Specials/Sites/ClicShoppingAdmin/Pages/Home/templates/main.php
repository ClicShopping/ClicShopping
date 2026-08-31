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

$CLICSHOPPING_Specials = Registry::get('Specials');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

if ($CLICSHOPPING_MessageStack->exists('Specials')) {
  echo $CLICSHOPPING_MessageStack->get('Specials');
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/specials.gif', $CLICSHOPPING_Specials->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Specials->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="alert alert-warning" role="alert">
    <?php echo $CLICSHOPPING_Specials->getDef('text_intro_sp'); ?>
    <?php echo $CLICSHOPPING_Specials->getDef('return_url', ['return_url_fa' => CLICSHOPPING::getConfig('http_server') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'index.php?Products&Specials']); ?>
  </div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Specials->getDef('text_products_specials'); ?></strong></div>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>

      <div class="col-md-12">
        <div>
          <div class="col-md-12">
            <?php echo $CLICSHOPPING_Specials->getDef('text_intro'); ?>
          </div>
        </div>
        <div class="mt-1"></div>
        <div class="col-md-12">
          <div>
            <div class="col-md-12 text-center">
              <?php
              echo HTML::form('configure', CLICSHOPPING::link(null, 'A&Marketing\Specials&Configure'));
              echo HTML::button($CLICSHOPPING_Specials->getDef('button_configure'), null, null, 'primary');
              echo '</form>';
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

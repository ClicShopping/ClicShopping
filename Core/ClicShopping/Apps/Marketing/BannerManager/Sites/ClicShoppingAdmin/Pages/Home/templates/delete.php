<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_BannerManager = Registry::get('BannerManager');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$Qbanner = $CLICSHOPPING_BannerManager->db->get('banners', ['banners_title',
  'banners_id',
  'languages_id'
],
  [
    'banners_id' => (int)$_GET['bID']
  ]
);

$bInfo = new ObjectInfo($Qbanner->toArray());
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/banner_manager.gif', $CLICSHOPPING_BannerManager->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_BannerManager->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle"><strong><?php echo $bInfo->banners_title; ?></strong></div>
  <?php echo HTML::form('banners', $CLICSHOPPING_BannerManager->link('BannerManager&DeleteConfirm&' . (isset($page) ? 'page=' . $page . '&' : '') . 'bID=' . $bInfo->banners_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_BannerManager->getDef('text_info_delete_intro'); ?><br/><br/>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12">
        <span class="col-md-3"><?php echo $bInfo->banners_title; ?></span>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12">
        <span
          class="col-md-3"><?php echo HTML::checkboxField('delete_image', 'on', true) . ' ' . $CLICSHOPPING_BannerManager->getDef('text_info_delete_image'); ?></span>
      </div>
      <div class="col-md-12 text-center">
        <br/><?php echo HTML::button($CLICSHOPPING_BannerManager->getDef('button_delete'), null, null, 'danger', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_BannerManager->getDef('button_cancel'), null, $CLICSHOPPING_BannerManager->link('BannerManager&page=' . $page . '&bID=' . $_GET['bID']), 'warning', null, 'sm'); ?>
      </div>
    </div>
  </div>
  </form>
</div>
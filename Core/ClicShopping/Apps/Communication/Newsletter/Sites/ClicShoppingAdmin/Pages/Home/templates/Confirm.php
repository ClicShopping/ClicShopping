<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\Apps\Communication\Newsletter\Module\ClicShoppingAdmin\Newsletter\Newsletter as NewsletterModule;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Newsletter = Registry::get('Newsletter');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$action = $_GET['action'] ?? '';

if (isset($_GET['nID'])) {
  $nID = HTML::sanitize($_GET['nID']);
}

$Qnewsletter = $CLICSHOPPING_Newsletter->db->get('newsletters', [
  'title',
  'content',
  'module',
  'languages_id',
  'customers_group_id',
  'newsletters_accept_file',
  'newsletters_twitter',
  'newsletters_customer_no_account'
], [
    'newsletters_id' => (int)$nID
  ]
);


$nInfo = new ObjectInfo($Qnewsletter->toArray());

$module_name = $nInfo->module;
$module = new NewsletterModule($nInfo->title, $nInfo->content);
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/newsletters.gif', $CLICSHOPPING_Newsletter->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Newsletter->getDef('heading_title'); ?></span>
          <span class="col-md-6 text-end"><span
              class="col-md-1 text-end"><?php echo HTML::button($CLICSHOPPING_Newsletter->getDef('button_cancel'), null, $CLICSHOPPING_Newsletter->link('Newsletter&page=' . $page . '&nID=' . $_GET['nID']), 'danger', null, 'xs'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div>
    <?php echo $module->confirm(); ?>
  </div>
</div>
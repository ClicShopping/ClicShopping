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

use ClicShopping\Apps\Communication\Newsletter\Module\ClicShoppingAdmin\Newsletter\Newsletter as NewsletterModule;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Newsletter = Registry::get('Newsletter');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$nID = (int)$_GET['nID'];
$nlID = (int)$_GET['nlID'];
$cgID = (int)$_GET['cgID'];
$ac = (int)$_GET['ac'];

$Qnewsletter = $CLICSHOPPING_Newsletter->db->get('newsletters', [
  'newsletters_id',
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

// Resolve the newsletter module class declared on the record (defaults to the
// standard Newsletter module when the stored value is unknown).
$module_name = preg_replace('/[^A-Za-z0-9_]/', '', (string)$nInfo->module);
$module_class = 'ClicShopping\\Apps\\Communication\\Newsletter\\Module\\ClicShoppingAdmin\\Newsletter\\' . $module_name;

if ($module_name === '' || !class_exists($module_class)) {
  $module_class = NewsletterModule::class;
}

$module = new $module_class($nInfo->title, $nInfo->content);
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
          <span
            class="col-md-6 text-end"><?php echo HTML::button($CLICSHOPPING_Newsletter->getDef('button_cancel'), null, $CLICSHOPPING_Newsletter->link('Newsletter&page=' . $page . '&nID=' . $_GET['nID']), 'danger', null, 'xs'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div>&nbsp;</div>
  <div class="text-center"><strong><?php echo $CLICSHOPPING_Newsletter->getDef('text_please_wait'); ?></strong></div>

  <?php
  flush();

  // Process one batch. When false, recipients remain: re-enter ConfirmSend to
  // continue the resumable send. When true, the send is complete.
  $send_complete = $module->sendCkeditor((int)$nInfo->newsletters_id);

  if ($send_complete === false) {
    $ana = (int)($_GET['ana'] ?? 0);
    $continue_url = $CLICSHOPPING_Newsletter->link('ConfirmSend&page=' . $page . '&nID=' . $nID . '&nlID=' . $nlID . '&cgID=' . $cgID . '&ac=' . $ac . '&ana=' . $ana);
    echo '<meta http-equiv="refresh" content="2; URL=' . $continue_url . '">';
  } else {
    echo '<meta http-equiv="refresh" content="3; URL=' . $CLICSHOPPING_Newsletter->link('ConfirmSendValid') . '">';
  }
  ?>
</div>
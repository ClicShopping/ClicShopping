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

$action = $_GET['action'] ?? '';

if (isset($_GET['nID'])) {
  $nID = HTML::sanitize($_GET['nID']);

  $Qnewsletter = $CLICSHOPPING_Newsletter->db->get('newsletters', [
    'title',
    'content',
    'module'
  ], [
      'newsletters_id' => (int)$nID
    ]
  );

  $nInfo = new ObjectInfo($Qnewsletter->toArray());

  $module_name = $nInfo->module;
  $module = new NewsletterModule($nInfo->title, $nInfo->content);

  if ($module->show_chooseAudience) {
    echo $module->chooseAudience();
  } else {
    echo $module->confirm();
  }
}
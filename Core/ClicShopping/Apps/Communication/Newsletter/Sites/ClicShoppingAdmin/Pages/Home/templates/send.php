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

  // Resolve the newsletter module class declared on the record (defaults to the
  // standard Newsletter module when the stored value is unknown).
  $module_name = preg_replace('/[^A-Za-z0-9_]/', '', (string)$nInfo->module);
  $module_class = 'ClicShopping\\Apps\\Communication\\Newsletter\\Module\\ClicShoppingAdmin\\Newsletter\\' . $module_name;

  if ($module_name === '' || !class_exists($module_class)) {
    $module_class = NewsletterModule::class;
  }

  $module = new $module_class($nInfo->title, $nInfo->content);

  if ($action === 'confirm') {
    echo $module->confirm();
  } elseif ($module->show_chooseAudience) {
    echo $module->chooseAudience();
  } else {
    echo $module->confirm();
  }
}
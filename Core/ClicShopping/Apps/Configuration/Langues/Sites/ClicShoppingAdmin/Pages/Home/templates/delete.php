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

$CLICSHOPPING_Langues = Registry::get('Langues');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

$lID = HTML::sanitize($_GET['lID']);

$Qlanguages = $CLICSHOPPING_Langues->db->prepare('select  *
                                                    from :table_languages
                                                    where languages_id = :languages_id
                                                    ');
$Qlanguages->bindInt(':languages_id', $lID);

$Qlanguages->execute();

$lInfo = new ObjectInfo($Qlanguages->toArray());

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/languages.gif', $CLICSHOPPING_Langues->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Langues->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  if ($Qlanguages->value('code') == DEFAULT_LANGUAGE) {
    echo '<div class="alert alert-warning" role="alert">' . $CLICSHOPPING_Langues->getDef('error_remove_default_language') . '</div>';
  }
  ?>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Langues->getDef('text_info_heading_delete_language'); ?></strong></div>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_Langues->getDef('text_info_delete_info'); ?><br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo '<strong>' . $lInfo->name . '</strong>'; ?><br/><br/></div>
      <div class="col-md-12 text-center">
        <span><br/>
<?php


if ($Qlanguages->value('code') != DEFAULT_LANGUAGE) {
  echo '<span><br />' . HTML::button($CLICSHOPPING_Langues->getDef('button_delete'), null, $CLICSHOPPING_Langues->link('Langues&DeleteConfirm&page=' . $page . '&lID=' . $lInfo->languages_id), 'primary', null, 'sm') . ' </span>';
}

echo '<span>' . HTML::button($CLICSHOPPING_Langues->getDef('button_cancel'), null, $CLICSHOPPING_Langues->link('Langues&page=' . $page . '&lID=' . $lInfo->languages_id), 'warning', null, 'sm');
?>
        </span>
      </div>
    </div>
  </div>
</div>
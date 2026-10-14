<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\AiModelsAdmin;

$CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$id = (isset($_GET['cID']) && is_numeric($_GET['cID'])) ? (int)$_GET['cID'] : 0;
$m = AiModelsAdmin::getModel($id);
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/chatgpt.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title'), '40', '40'); ?></span>
          <span class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
            <?php
            echo HTML::form('delete', $CLICSHOPPING_ChatGpt->link('ChatGpt&Delete&cID=' . $id));
            echo HTML::button($CLICSHOPPING_ChatGpt->getDef('text_delete_confirm'), null, null, 'danger') . ' ';
            echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back'), null, $CLICSHOPPING_ChatGpt->link('ChatGpt'), 'primary');
            echo '</form>';
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="adminformTitle">
    <div class="alert alert-warning">
      <?php echo $CLICSHOPPING_ChatGpt->getDef('text_delete_intro'); ?>
      <strong><?php echo HTML::outputProtected($m['model_display_name'] ?? ''); ?></strong>
      (<code><?php echo HTML::outputProtected($m['model_technical_name'] ?? ''); ?></code>)
    </div>
  </div>
</div>
<div class="py-4"></div>

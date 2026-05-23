<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/chatgpt.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
          <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back'), null, $CLICSHOPPING_ChatGpt->link('ChatGpt'), 'primary'); ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="mt-1"></div>
  <?php
  $gpt_id = HTML::sanitize($_GET['cID']);

  $QchatGpt = $CLICSHOPPING_ChatGpt->db->prepare('select gpt_id,
                                                           question,
                                                           response,
                                                           date_added
                                                    from :table_gpt
                                                    where gpt_id = :gpt_id
                                                  ');
  $QchatGpt->bindInt(':gpt_id', $gpt_id);
  $QchatGpt->execute();
  ?>
  <div id="categoriesTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_ChatGpt->getDef('tab_general') . '</a>'; ?></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <?php
        // -------------------------------------------------------------------
        //          ONGLET General sur la description
        // -------------------------------------------------------------------
        ?>
        <div class="tab-pane active" id="tab1">
          <div class="col-md-12 mainTitle">
            <div class="float-start"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_description'); ?></div>
          </div>
          <div class="adminformTitle" id="categoriesLanguage">
            <div class="col-md-12" id="geptDateAdded">
              <?php echo DateTime::toShort($QchatGpt->value('date_added')); ?>
            </div>
            <div class="mt-1"></div>
            <div class="col-md-12" id="gptQuestion">
              <?php echo $QchatGpt->value('question'); ?>
            </div>
            <div class="mt-1"></div>
            <div class="col-md-12" id="gptResponse">
              <?php
              $response = $QchatGpt->value('response');
              $decoded_response_Html = html_entity_decode($response);

              echo $decoded_response_Html;
              ?>
            </div>
          </div>
          <div class="separator">
            <?php echo $CLICSHOPPING_Hooks->output('chatGpt', 'chatGptContent', null, 'display'); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="py-4"></div>
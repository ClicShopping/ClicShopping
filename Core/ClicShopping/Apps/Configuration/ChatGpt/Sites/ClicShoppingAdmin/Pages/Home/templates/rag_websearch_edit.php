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

$CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

if (isset($_GET['cID']) && is_numeric($_GET['cID'])) {
  $id = HTML::sanitize($_GET['cID']);
  $save = 'Update';
  $QragWebSearch = $CLICSHOPPING_ChatGpt->db->prepare('select  id,
                                                                 site_domain,
                                                                 authority_score,
                                                                 status,
                                                                 description,
                                                                 search_pattern
                                                            from :table_rag_websearch
                                                            where id = :id
                                                       ');
  $QragWebSearch->bindInt(':id', $id);
  $QragWebSearch->execute();

  $webSearchInfo = new ObjectInfo($QragWebSearch->toArray());
} else {
  $save  = 'Insert';
  $webSearchInfo = '';
  $id = '';
}

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}
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
             <?php
             echo HTML::form('save', $CLICSHOPPING_ChatGpt->link('RagWebSearch&' . $save .'&page=' . (int)$_GET['page'] . '&cID=' . $id));
             echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_save'), null, null, 'success') . ' ';
             echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back'), null, $CLICSHOPPING_ChatGpt->link('RagWebSearch'), 'primary'); ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="mt-1"></div>
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
            <div class="mt-1"></div>
            <div class="adminformTitle" id="websearch">
              <div class="mt-1"></div>

              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_ChatGpt->getDef('text_site_domain'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_site_domain'); ?></label>
                <div class="col-md-5"><?php echo HTML::inputField('site_domain', $webSearchInfo->site_domain ?? '', 'placeholder=' . $CLICSHOPPING_ChatGpt->getDef('text_ino_authority_score')); ?></div>
              </div>
              <div class="mt-1"></div>

              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_ChatGpt->getDef('text_authority_score'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_authority_score'); ?></label>
                <div class="col-md-5"><?php echo HTML::inputField('authority_score', $webSearchInfo->authority_score ?? '', 'placeholder=' . $CLICSHOPPING_ChatGpt->getDef('text_info_authority_score')); ?></div>
              </div>
              <div class="mt-1"></div>

              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_ChatGpt->getDef('text_status'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_status'); ?></label>
                <div class="col-md-5">
                  <ul class="list-group-slider list-group-flush">
                    <li class="list-group-item-slider">
                      <label class="switch">
                        <?php
                        $checked = (isset($webSearchInfo->status) && (int)$webSearchInfo->status === 1);
                        echo HTML::checkboxField('status', '1', $checked, 'class="success"'); ?>
                        <span class="slider"></span>
                      </label>
                    </li>
                  </ul>
                </div>
              </div>
              <div class="mt-1"></div>
              <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_ChatGpt->getDef('text_search_pattern'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_search_pattern'); ?></label>
                  <div class="col-md-5"><?php echo HTML::inputField('search_pattern', $webSearchInfo->search_pattern ?? '', 'placeholder=' . $CLICSHOPPING_ChatGpt->getDef('text_info_search_pattern')); ?></div>
              </div>
             <div class="mt-1"></div>
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_ChatGpt->getDef('text_description'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_description'); ?></label>
                <div class="col-md-5"> <?php echo HTML::textAreaField('description', $webSearchInfo->description ?? ''); ?></div>
              </div>

              <div class="mt-1"></div>
            </div>
            <div class="separator">
              <?php echo $CLICSHOPPING_Hooks->output('websearch', 'webSearchContent', null, 'display'); ?>
            </div>

              <!-- Pattern Usage Information -->
              <div class="form-group row">
                  <div class="col-md-12">
                      <div class="alert alert-info">
                          <h6><i class="bi bi-info-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_pattern_usage_title'); ?></h6>
                          <p><?php echo $CLICSHOPPING_ChatGpt->getDef('text_pattern_usage_info'); ?></p>
                          <ul class="mb-0">
                              <li><?php echo $CLICSHOPPING_ChatGpt->getDef('text_pattern_usage_example1'); ?></li>
                              <li><?php echo $CLICSHOPPING_ChatGpt->getDef('text_pattern_usage_example2'); ?></li>
                              <li><?php echo $CLICSHOPPING_ChatGpt->getDef('text_pattern_usage_example3'); ?></li>
                              <li><?php echo $CLICSHOPPING_ChatGpt->getDef('text_pattern_usage_example4'); ?></li>
                          </ul>
                          <p class="mt-2 mb-0"><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('text_pattern_usage_note'); ?></strong></p>
                      </div>
                  </div>
              </div>
              <div class="mt-1"></div>
          </div>
          <?php
          // -------------------------------------------------------------------
          //   Next tab
          // -------------------------------------------------------------------
          ?>
        </div>
      </div>
    </div>
  </div>
  <div class="py-4"></div>
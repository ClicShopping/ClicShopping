<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Dashboard selector: landing page routing to the three metier dashboards
 * (Manager / Developper / Data Scientist). Kept lightweight — no heavy data load.
 */
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

// Safe configuration state detection (graceful degradation, no Dashboard data load)
$config = [
    'chatgpt_installed' => defined('CLICSHOPPING_APP_CHATGPT_CH_STATUS'),
    'chatgpt_enabled' => defined('CLICSHOPPING_APP_CHATGPT_CH_STATUS') && CLICSHOPPING_APP_CHATGPT_CH_STATUS == 'True',
    'rag_installed' => defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS'),
    'rag_enabled' => defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True',
    'rag_cache_enabled' => defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER == 'True',
];
?>
   <div class="contentBody">
    <div class="row">
      <div class="col-md-12">
        <div class="card card-block headerCard">
          <div class="row">
          <span class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title'), '40', '40'); ?></span>
            <span class="col-md-3 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title'); ?></span>
            <span class="col-md-8 text-end">
            <?php
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_configure'), null, $CLICSHOPPING_ChatGpt->link('Configure'), 'primary') . ' ';
              if ($config['chatgpt_enabled']) {
                echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_help'), null, $CLICSHOPPING_ChatGpt->link('Help'), 'info') . ' ';
              }
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back'), null, $CLICSHOPPING_ChatGpt->link('ChatGpt'), 'primary');
            ?>
          </span>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-1"></div>
   <?php
       // ============================================================================
       // INFORMATIONAL MESSAGES FOR DISABLED FEATURES
       // ============================================================================
       // Display actionable guidance when features are not installed or disabled
       // Requirements: 1.4, 5.1, 5.4
       
       // ChatGPT Module Not Installed (Requirement 1.4)
       if (!$config['chatgpt_installed']) {
   ?>
     <div class="alert alert-warning" role="alert">
         <h5><i class="bi bi-exclamation-triangle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_title'); ?></h5>
         <p><?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_message'); ?></p>
         <hr>
         <p class="mb-0">
           <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_action'); ?></strong><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_step1'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_step2'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_step3'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_step4'); ?>
         </p>
     </div>
   <?php
       // ChatGPT Module Disabled (Requirement 1.4)
       } elseif (!$config['chatgpt_enabled']) {
   ?>
     <div class="alert alert-info" role="alert">
         <h5><i class="bi bi-info-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_title'); ?></h5>
         <p><?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_message'); ?></p>
         <hr>
         <p class="mb-0">
           <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_action'); ?></strong><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_step1'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_step2'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_step3'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_step4'); ?>
         </p>
     </div>
   <?php
       }
       
       // RAG BI Not Installed (Requirement 5.1)
       if ($config['chatgpt_enabled'] && !$config['rag_installed']) {
   ?>
     <div class="alert alert-warning" role="alert">
         <h5><i class="bi bi-exclamation-triangle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_title'); ?></h5>
         <p><?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_message'); ?></p>
         <hr>
         <p class="mb-0">
           <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_action'); ?></strong><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_step1'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_step2'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_step3'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_step4'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_step5'); ?>
         </p>
     </div>
   <?php
       // RAG BI Disabled (Requirement 5.1)
       } elseif ($config['chatgpt_enabled'] && !$config['rag_enabled']) {
   ?>
     <div class="alert alert-info" role="alert">
         <h5><i class="bi bi-info-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_title'); ?></h5>
         <p><?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_message'); ?></p>
         <hr>
         <p class="mb-0">
           <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_action'); ?></strong><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_step1'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_step2'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_step3'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_step4'); ?>
         </p>
     </div>
   <?php
       }
       
       // Display informational alert when RAG cache is disabled (existing alert)
       if ($config['rag_enabled'] && !$config['rag_cache_enabled']) {
   ?>
     <div class="alert alert-info text-center" role="alert">
         <i class="bi bi-info-circle"></i>
         <?php echo $CLICSHOPPING_ChatGpt->getDef('text_alert_dashboard'); ?>
     </div>
   <?php
    }
   ?>

    <div class="row mt-3">
      <div class="col-md-4 mb-3">
        <div class="card h-100">
          <div class="card-body text-center">
            <h2 class="mb-3">📊</h2>
            <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('dashboard_manager_title'); ?></h5>
            <p class="card-text text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('dashboard_manager_desc'); ?></p>
            <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('dashboard_open'), null, $CLICSHOPPING_ChatGpt->link('DashboardManager'), 'primary', ['params' => 'style="width: 100%;"']); ?>
          </div>
        </div>
      </div>
      <div class="col-md-4 mb-3">
        <div class="card h-100">
          <div class="card-body text-center">
            <h2 class="mb-3">🛠️</h2>
            <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('dashboard_developper_title'); ?></h5>
            <p class="card-text text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('dashboard_developper_desc'); ?></p>
            <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('dashboard_open'), null, $CLICSHOPPING_ChatGpt->link('DashboardDevelopper'), 'success', ['params' => 'style="width: 100%;"']); ?>
          </div>
        </div>
      </div>
      <div class="col-md-4 mb-3">
        <div class="card h-100">
          <div class="card-body text-center">
            <h2 class="mb-3">🔬</h2>
            <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('dashboard_data_scientist_title'); ?></h5>
            <p class="card-text text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('dashboard_data_scientist_desc'); ?></p>
            <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('dashboard_open'), null, $CLICSHOPPING_ChatGpt->link('DashboardDataScientist'), 'info', ['params' => 'style="width: 100%;"']); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="py-4"></div>

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
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

if (isset($_GET['cID']) && is_numeric($_GET['cID'])) {
  $id = (int)$_GET['cID'];
  $save = 'Update';
  $m = AiModelsAdmin::getModel($id);
  $cred = AiModelsAdmin::getProviderCredential((int)($m['ai_model_provider_id'] ?? 0));
} else {
  $id = '';
  $save = 'Insert';
  $m = [];
  $cred = ['api_key_plain' => '', 'organisation' => null];
}

$providerOptions = [];
foreach (AiModelsAdmin::getProviders() as $p) {
  $providerOptions[] = ['id' => (string)$p['id'], 'text' => $p['code']];
}

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}
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
            echo HTML::form('save', $CLICSHOPPING_ChatGpt->link('ChatGpt&' . $save . ($id !== '' ? '&cID=' . $id : '')));
            echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_save'), null, null, 'success') . ' ';
            echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back'), null, $CLICSHOPPING_ChatGpt->link('ChatGpt'), 'primary');
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="adminformTitle">
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_provider'); ?></label>
      <div class="col-md-5"><?php echo HTML::selectField('ai_model_provider_id', $providerOptions, (string)($m['ai_model_provider_id'] ?? ''), 'required'); ?></div>
    </div>
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_technical_name'); ?></label>
      <div class="col-md-5"><?php echo HTML::inputField('model_technical_name', $m['model_technical_name'] ?? '', 'required'); ?></div>
    </div>
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_display_name'); ?></label>
      <div class="col-md-5"><?php echo HTML::inputField('model_display_name', $m['model_display_name'] ?? '', 'required'); ?></div>
    </div>
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_model_description'); ?></label>
      <div class="col-md-5"><?php echo HTML::inputField('ai_model_description', $m['ai_model_description'] ?? ''); ?></div>
    </div>
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_context_window'); ?></label>
      <div class="col-md-5"><?php echo HTML::inputField('ai_model_context_window', $m['ai_model_context_window'] ?? '0', 'placeholder=' . $CLICSHOPPING_ChatGpt->getDef('text_info_context')); ?></div>
    </div>
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_cost_input'); ?></label>
      <div class="col-md-5"><?php echo HTML::inputField('ai_model_token_input_price', $m['ai_model_token_input_price'] ?? '0.0000'); ?></div>
    </div>
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_cost_output'); ?></label>
      <div class="col-md-5"><?php echo HTML::inputField('ai_model_token_output_price', $m['ai_model_token_output_price'] ?? '0.0000'); ?></div>
    </div>
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_ai_capable'); ?></label>
      <div class="col-md-5"><?php echo HTML::checkboxField('ai_model_ai_capable', '1', ((int)($m['ai_model_ai_capable'] ?? 1) === 1)); ?></div>
    </div>
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_status'); ?></label>
      <div class="col-md-5"><?php echo HTML::checkboxField('ai_model_status', '1', ((int)($m['ai_model_status'] ?? 0) === 1)); ?></div>
    </div>
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_status_default'); ?></label>
      <div class="col-md-5"><?php echo HTML::checkboxField('ai_model_status_default', '1', ((int)($m['ai_model_status_default'] ?? 0) === 1)); ?></div>
    </div>
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_status_fallback'); ?></label>
      <div class="col-md-5"><?php echo HTML::checkboxField('ai_model_status_fallback', '1', ((int)($m['ai_model_status_fallback'] ?? 0) === 1)); ?></div>
    </div>
    <div class="separator"></div>
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_api_key'); ?></label>
      <div class="col-md-5"><?php echo HTML::inputField('provider_api_key', $cred['api_key_plain'], '', 'password'); ?></div>
    </div>
    <div class="form-group row">
      <label class="col-5 col-form-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_organisation'); ?></label>
      <div class="col-md-5"><?php echo HTML::inputField('provider_organisation', $cred['organisation'] ?? ''); ?></div>
    </div>
    <div class="form-group row">
      <div class="col-md-12"><div class="alert alert-info"><i class="bi bi-info-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_help_note'); ?></div></div>
    </div>
    <?php echo '</form>'; ?>
  </div>
</div>
<div class="py-4"></div>

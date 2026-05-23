<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Products = Registry::get('Products');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');

$rID = (isset($_GET['rID']) && is_numeric($_GET['rID'])) ? (int)$_GET['rID'] : null;

if (!is_null($rID)) {
  $Qrules = $CLICSHOPPING_Products->db->prepare('select rules_id,
                                                      rules_name,
                                                      rules_condition,
                                                      rules_type,
                                                      rules_value,
                                                      rules_priority,
                                                      rules_status,
                                                      rules_status_special,
                                                      rules_status_promotion,
                                                      customers_group
                                               from :table_dynamic_pricing_rules
                                               where rules_id = :rules_id
                                              ');
  $Qrules->bindInt(':rules_id', $rID);
  $Qrules->execute();
  $rules = $Qrules->fetch();

  $button_text = $CLICSHOPPING_Products->getDef('button_update');
} else {
  $button_text = $CLICSHOPPING_Products->getDef('button_insert');
}

$form_action = 'DynamicPricingRules&SaveDynamicPricingRules';

if (!is_null($rID)) {
$form_action .= '&rID=' . $rID;
}

$page_title = $CLICSHOPPING_Products->getDef('text_info_heading_rules');
echo HTML::form('dynamic_pricing_rule_form', $CLICSHOPPING_Products->link($form_action));
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/produit.gif', $page_title, '40', '40'); ?></span>
          <span class="col-md-5 pageHeading"><?php echo '&nbsp;' . $page_title; ?></span>
          <span class="col-md-6">
            <div class="form-group text-end">
              <?php echo HTML::button($button_text, null, null, 'success'); ?>
              <?php echo HTML::button($CLICSHOPPING_Products->getDef('button_cancel'), null, $CLICSHOPPING_Products->link('DynamicPricingRules'), 'warning'); ?>
            </div>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-1">
    <div class="col-md-12 mainTitle"><?php echo $CLICSHOPPING_Products->getDef('text_info_heading'); ?></div>
  </div>

  <div class="row mt-1">
    <div class="col-md-12">
      <div class="form-group row">
        <label for="rules_name" class="col-2 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_name'); ?></label>
        <div class="col-md-5">
          <?php echo HTML::inputField('rules_name', $rules['rules_name'] ?? '', 'class="form-control" required="required"'); ?>
        </div>
      </div>

      <div class="form-group row mt-1">
        <label for="rules_condition" class="col-2 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_condition'); ?></label>
        <div class="col-md-5">
          <?php echo HTML::inputField('rules_condition', $rules['rules_condition'] ?? '', 'class="form-control" required="required" placeholder="' . $CLICSHOPPING_Products->getDef('table_help_rules_condition') . '"'); ?>
        </div>
      </div>

      <div class="form-group row mt-1">
        <label for="rules_type" class="col-2 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_type'); ?></label>
        <div class="col-md-5">
          <?php
          $rules_type_array = [
            ['id' => 'percentage_decrease', 'text' => $CLICSHOPPING_Products->getDef('text_rules_type_percentage_decrease')],
            ['id' => 'percentage_increase', 'text' => $CLICSHOPPING_Products->getDef('text_rules_type_percentage_increase')],
            ['id' => 'fixed_price', 'text' => $CLICSHOPPING_Products->getDef('text_rules_type_fixed_price')]
          ];
          echo HTML::selectField('rules_type', $rules_type_array, $rules['rules_type'] ?? '', 'class="form-control" required="required"');
          ?>
        </div>
      </div>

      <div class="form-group row mt-1">
        <label for="rules_value" class="col-2 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_value'); ?></label>
        <div class="col-md-5">
          <?php echo HTML::inputField('rules_value', $rules['rules_value'] ?? '', 'class="form-control" required="required"'); ?>
        </div>
      </div>

      <div class="form-group row mt-1">
        <label for="rules_priority" class="col-2 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_priority'); ?></label>
        <div class="col-md-5">
          <?php echo HTML::inputField('rules_priority', $rules['rules_priority'] ?? '0', 'class="form-control" required="required"'); ?>
        </div>
      </div>

      <div class="form-group row mt-1">
        <label class="col-2 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_status'); ?></label>
        <div class="col-md-5">
          <ul class="list-group-slider list-group-flush">
            <li class="list-group-item-slider">
              <label class="switch">
                <?php echo HTML::checkboxField('rules_status', '1', $rules['rules_status']  ?? '', 'class="success"'); ?>
                <span class="slider"></span>
              </label>
            </li>
          </ul>
        </div>
      </div>

      <div class="form-group row mt-1">
        <label class="col-2 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_rules_status_special'); ?></label>
        <div class="col-md-5">
          <ul class="list-group-slider list-group-flush">
            <li class="list-group-item-slider">
              <label class="switch">
                <?php echo HTML::checkboxField('rules_status_special', '1', $rules['rules_status_special'] ?? '', 'class="success"'); ?>
                <span class="slider"></span>
              </label>
            </li>
          </ul>
        </div>
      </div>

      <div class="form-group row mt-1">
        <label class="col-2 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_rules_status_promotion'); ?></label>
        <div class="col-md-5">
          <ul class="list-group-slider list-group-flush">
            <li class="list-group-item-slider">
              <label class="switch">
                <?php echo HTML::checkboxField('rules_status_promotion', '1', $rules['rules_status_promotion'] ?? '', 'class="success"'); ?>
                <span class="slider"></span>
              </label>
            </li>
          </ul>
        </div>
      </div>

      <div class="form-group row mt-1">
        <label class="col-2 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('table_heading_rules_customer_group'); ?></label>
        <div class="col-md-5">
          <ul class="list-group-slider list-group-flush">
            <li class="list-group-item-slider">
              <label class="switch">
                <?php echo HTML::checkboxField('customers_group', '1', $rules['customers_group']  ?? '', 'class="success"'); ?>
                <span class="slider"></span>
              </label>
            </li>
          </ul>
        </div>
      </div>

      <div class="row mt-2">
        <div class="col-md-12 text-left">
          <div class="alert alert-info" role="alert">
            <?php echo $CLICSHOPPING_Products->getDef('text_info'); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</form>
